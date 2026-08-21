<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Fetch Google OAuth settings from env
$googleClientId = env('GOOGLE_CLIENT_ID', '');
$googleClientSecret = env('GOOGLE_CLIENT_SECRET', '');

// Compute dynamic redirect URL if not explicitly defined in env
$googleRedirectUri = env('GOOGLE_REDIRECT_URL', '');
if (empty($googleRedirectUri) || $googleRedirectUri === 'auto') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $googleRedirectUri = $protocol . '://' . $host . base_url('auth/google.php');
}

$gsiCredential = $_POST['credential'] ?? '';
$authCode = $_POST['code'] ?? $_GET['code'] ?? '';
$authState = $_POST['state'] ?? $_GET['state'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// FLOW A: Google Identity Services (GSI) ID Token Submission (Zero Redirects)
// ─────────────────────────────────────────────────────────────────────────────
if (!empty($gsiCredential)) {
    // Decode Google JWT Token (header.payload.signature)
    $jwtParts = explode('.', $gsiCredential);
    if (count($jwtParts) !== 3) {
        die('Invalid Google ID Token format.');
    }

    $payloadJson = base64_decode(strtr($jwtParts[1], '-_', '+/'));
    $payload = json_decode($payloadJson, true);

    if (empty($payload) || empty($payload['sub']) || empty($payload['email'])) {
        die('Unable to extract Google account information.');
    }

    $googleId = $payload['sub'];
    $email = $payload['email'];
    $fullName = $payload['name'] ?? 'Google User';
    $profilePhoto = $payload['picture'] ?? null;

    authenticateUser($pdo, $googleId, $email, $fullName, $profilePhoto);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// FLOW B: Traditional OAuth 2.0 Authorization Code Exchange
// ─────────────────────────────────────────────────────────────────────────────
if (!empty($authCode)) {
    // Exchange auth code for access token via cURL
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postFields = [
        'code'          => $authCode,
        'client_id'     => $googleClientId,
        'client_secret' => $googleClientSecret,
        'redirect_uri'  => $googleRedirectUri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        die('OAuth token exchange failed: ' . htmlspecialchars($curlErr));
    }

    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? '';

    if (empty($accessToken)) {
        die('OAuth token error: ' . htmlspecialchars($response));
    }

    // Fetch User Profile from Google UserInfo endpoint
    $userinfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
    $ch = curl_init($userinfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($userInfoResponse, true);
    $googleId = $userInfo['sub'] ?? '';
    $email = $userInfo['email'] ?? '';
    $fullName = $userInfo['name'] ?? '';
    $profilePhoto = $userInfo['picture'] ?? null;

    if (empty($googleId) || empty($email)) {
        die('OAuth identity failed: Google did not return required profile scopes.');
    }

    authenticateUser($pdo, $googleId, $email, $fullName, $profilePhoto);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// FLOW C: Direct Visit without Credentials -> Redirect to Login Page
// ─────────────────────────────────────────────────────────────────────────────
header('Location: ' . base_url('login'));
exit;


// ─────────────────────────────────────────────────────────────────────────────
// Core Authentication & Session Handler
// ─────────────────────────────────────────────────────────────────────────────
function authenticateUser(PDO $pdo, string $googleId, string $email, string $fullName, ?string $profilePhoto): void {
    // Look up user in database by Google ID or by registered Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['status'] !== 'active') {
            die('Access Denied: Your account is suspended or inactive.');
        }

        // Link Google ID and update profile photo if not present
        if (empty($user['google_id']) || empty($user['profile_photo'])) {
            $upd = $pdo->prepare("UPDATE users SET google_id = ?, profile_photo = COALESCE(profile_photo, ?) WHERE id = ?");
            $upd->execute([$googleId, $profilePhoto, $user['id']]);
            $user['google_id'] = $googleId;
            if (empty($user['profile_photo'])) {
                $user['profile_photo'] = $profilePhoto;
            }
        }

        // Store user in session
        $_SESSION['user'] = [
            'id'            => $user['id'],
            'username'      => $user['username'],
            'full_name'     => $user['full_name'],
            'email'         => $user['email'],
            'role'          => $user['role'],
            'profile_photo' => $user['profile_photo'],
        ];

        // Redirect based on user role
        if ($user['role'] === 'admin') {
            header('Location: ' . base_url('admin/'));
            exit;
        } elseif ($user['role'] === 'coordinator') {
            header('Location: ' . base_url('coordinator/'));
            exit;
        } elseif ($user['role'] === 'teacher') {
            header('Location: ' . base_url('teacher/'));
            exit;
        } else {
            header('Location: ' . base_url('index.php'));
            exit;
        }
    } else {
        // Unregistered user block screen
        die('<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Account Not Registered</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="../assets/css/style.css">
        </head>
        <body class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6" style="background-color:#0e2e38; color:#ecf3d6;">
            <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>
            <div class="w-full max-w-md glass-panel rounded-3xl p-8 space-y-6 relative border border-white/10 text-center">
                <div class="space-y-2">
                    <i class="fa-solid fa-user-xmark text-red-400 text-5xl mb-2"></i>
                    <h1 class="text-xl font-bold text-white">Google Login Access Denied</h1>
                    <p class="text-xs text-slate-400">Account Not Registered</p>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed">
                    No account matching <span class="text-emerald-400 font-semibold">' . htmlspecialchars($email) . '</span> was found in our registration directory.<br><br>
                    Please contact your Academic Coordinator or Administrator to register your profile first.
                </p>
                <div class="pt-4">
                    <a href="' . base_url('login') . '" class="inline-flex items-center gap-1.5 text-xs bg-white/5 border border-white/15 px-4 py-2.5 rounded-xl text-slate-200 hover:text-white transition">
                        Back to Login
                    </a>
                </div>
            </div>
        </body>
        </html>');
    }
}
