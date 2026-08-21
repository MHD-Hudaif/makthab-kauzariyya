<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Fetch Google OAuth settings from env
$googleClientId = env('GOOGLE_CLIENT_ID', '');
$googleClientSecret = env('GOOGLE_CLIENT_SECRET', '');

// Compute dynamic redirect URL if not explicitly defined in env
$googleRedirectUri = env('GOOGLE_REDIRECT_URL', '');
if (empty($googleRedirectUri) || $googleRedirectUri === 'auto') {
    // Determine HTTP protocol
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $googleRedirectUri = $protocol . '://' . $host . base_url('auth/google.php');
}

// 1. Check if Google credentials are set up
if (empty($googleClientId) || empty($googleClientSecret) || $googleClientId === 'your-google-client-id') {
    die('<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Google OAuth Setup Required</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6" style="background-color:#0e2e38; color:#ecf3d6;">
        <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>
        <div class="w-full max-w-lg glass-panel rounded-3xl p-8 space-y-6 relative border border-white/10">
            <div class="text-center space-y-2">
                <i class="fa-solid fa-circle-info text-emerald-400 text-4xl mb-2"></i>
                <h1 class="text-xl font-bold text-white">Google OAuth Setup Required</h1>
                <p class="text-xs text-slate-400">Configure your Google Credentials in the .env file.</p>
            </div>
            <div class="bg-white/5 p-4 rounded-xl space-y-3 text-xs leading-relaxed text-slate-300 font-mono">
                <div>1. Go to Google Cloud Console Credentials page.</div>
                <div>2. Create an OAuth 2.0 Client ID.</div>
                <div>3. Set your Redirect URI to: <br><span class="text-emerald-400 break-all">' . htmlspecialchars($googleRedirectUri) . '</span></div>
                <div>4. Copy Client ID and Secret to your .env file:</div>
                <div class="bg-black/30 p-2.5 rounded border border-white/5 text-slate-400">
                    GOOGLE_CLIENT_ID=your_id_here<br>
                    GOOGLE_CLIENT_SECRET=your_secret_here<br>
                    GOOGLE_REDIRECT_URL=' . htmlspecialchars($googleRedirectUri) . '
                </div>
            </div>
            <div class="text-center">
                <a href="' . base_url('login') . '" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white transition">
                    Back to Login
                </a>
            </div>
        </div>
    </body>
    </html>');
}

// 2. INITIATE OAUTH FLOW (If no authorization code returned from Google yet)
if (!isset($_GET['code'])) {
    // Generate secure CSRF state
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    // Build authorization redirect link
    $params = [
        'response_type' => 'code',
        'client_id'     => $googleClientId,
        'redirect_uri'  => $googleRedirectUri,
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account'
    ];

    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit;
}

// 3. CALLBACK HANDLING (Google redirected user back with an authorization code)
if (isset($_GET['code'])) {
    // Verify state to prevent CSRF attacks
    $state = $_GET['state'] ?? '';
    if (empty($state) || empty($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
        die('OAuth validation error: State parameter mismatch. Please restart login.');
    }
    unset($_SESSION['oauth_state']);

    // Exchange auth code for access token via cURL
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postFields = [
        'code'          => $_GET['code'],
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

    // Call Google Userinfo API to fetch identity
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
    $profilePhoto = $userInfo['picture'] ?? '';

    if (empty($googleId) || empty($email)) {
        die('OAuth identity failed: Google did not return required profile scopes.');
    }

    // Find existing user in database by Google ID or by Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['status'] !== 'active') {
            die('Access Denied: Your account is suspended or inactive.');
        }

        // Auto-link Google ID if they have a matching email but google_id wasn't linked yet
        if (empty($user['google_id'])) {
            $upd = $pdo->prepare("UPDATE users SET google_id = ?, profile_photo = ? WHERE id = ?");
            $upd->execute([$googleId, $profilePhoto, $user['id']]);
            $user['google_id'] = $googleId;
            $user['profile_photo'] = $profilePhoto;
        }

        // Set session
        $_SESSION['user'] = [
            'id'            => $user['id'],
            'username'      => $user['username'],
            'full_name'     => $user['full_name'],
            'email'         => $user['email'],
            'role'          => $user['role'],
            'profile_photo' => $user['profile_photo'],
        ];

        // Redirect based on role
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
        // User not registered in database
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
