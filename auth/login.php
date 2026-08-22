<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Redirect if already logged in
if (!empty($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    if ($role === 'admin') {
        header('Location: ' . base_url('admin/'));
        exit;
    } elseif ($role === 'coordinator') {
        header('Location: ' . base_url('coordinator/'));
        exit;
    } elseif ($role === 'teacher') {
        header('Location: ' . base_url('teacher/'));
        exit;
    } elseif ($role === 'student') {
        header('Location: ' . base_url('student/'));
        exit;
    } else {
        // This role does not have a dedicated portal yet. Do not show the
        // sign-in form again while the user already has a valid session.
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $loginInput = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($loginInput) || empty($password)) {
        $error = 'Please enter your username, email, or mobile number, and password.';
    } else {
        try {
            // Support login via username, email, or phone number
            $cleanPhone = preg_replace('/[^0-9]/', '', $loginInput);
            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE username = ? 
                   OR email = ? 
                   OR phone = ? 
                   OR (phone != '' AND REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?)
                LIMIT 1
            ");
            $stmt->execute([$loginInput, $loginInput, $loginInput, $cleanPhone]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'inactive') {
                    $error = 'Your admission is pending review and activation by the Coordinator.';
                } elseif ($user['status'] === 'suspended') {
                    $error = 'Access Denied: Your account has been suspended.';
                } else {
                    session_regenerate_id(true);

                // Check if user has multiple comma-separated roles
                $roles = array_filter(array_map('trim', explode(',', $user['role'])));
                if (count($roles) > 1) {
                    $_SESSION['temp_user_login'] = $user;
                    header('Location: ' . base_url('auth/role_select.php'));
                    exit;
                }

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
                } elseif ($user['role'] === 'student') {
                    header('Location: ' . base_url('student/'));
                    exit;
                } else {
                    // Default fallback
                    header('Location: ' . base_url('index.php'));
                    exit;
                }
                }
            } else {
                $error = 'Invalid username, email, mobile number, or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kauzariyya Portal - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6 overflow-hidden">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[20%] left-[25%] w-[45vw] h-[45vw] max-w-[500px] bg-emerald-500/10 blur-[100px] animate-blob-1"></div>
        <div class="absolute bottom-[20%] right-[25%] w-[40vw] h-[40vw] max-w-[450px] bg-blue-600/10 blur-[120px] animate-blob-2"></div>
    </div>

    <!-- Login Card -->
    <div class="w-full max-w-md glass-panel rounded-3xl p-8 space-y-6 relative">
        
        <!-- Logo / Heading -->
        <div class="text-center space-y-3">
            <div class="inline-flex w-14 h-14 rounded-full overflow-hidden bg-slate-950 border border-white/10 items-center justify-center p-1.5 shadow-lg">
                <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Portal Login</h1>
                <p class="text-xs text-slate-400 uppercase tracking-widest mt-1">Al Jamiathul Kauzariyya</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-3.5 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['msg_success'])): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-3.5 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-base"></i>
                <span><?= htmlspecialchars($_SESSION['msg_success']); unset($_SESSION['msg_success']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Form — posts to itself -->
        <form action="" method="POST" class="space-y-4">
            <div class="space-y-1.5">
                <label for="username" class="text-xs text-slate-300 font-semibold uppercase tracking-wider">Username, Email, or Mobile</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-regular fa-user text-sm"></i></span>
                    <input type="text" name="username" id="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="w-full bg-white text-slate-950 font-semibold placeholder:text-slate-400 pl-10 pr-4 py-3.5 rounded-xl text-sm border-2 border-slate-200 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15 transition shadow-sm" placeholder="Username, email, or mobile" required autocomplete="username">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="password" class="text-xs text-slate-300 font-semibold uppercase tracking-wider">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-lock text-sm"></i></span>
                    <input type="password" name="password" id="password" class="w-full bg-white text-slate-950 font-semibold placeholder:text-slate-400 pl-10 pr-4 py-3.5 rounded-xl text-sm border-2 border-slate-200 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15 transition shadow-sm" placeholder="Enter password" required autocomplete="current-password">
                </div>
            </div>

            <div class="pt-2 space-y-3">
                <button type="submit" class="w-full bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold py-3.5 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition duration-300">
                    Sign In
                </button>
                <div class="relative flex py-2 items-center">
                    <div class="flex-grow border-t border-white/10"></div>
                    <span class="flex-shrink mx-4 text-[10px] text-slate-500 uppercase tracking-widest font-semibold">Or</span>
                    <div class="flex-grow border-t border-white/10"></div>
                </div>

                <!-- Google Identity Services (GSI) 1-Click Sign-In -->
                <div class="flex justify-center w-full">
                    <div id="g_id_onload"
                        data-client_id="<?= htmlspecialchars(env('GOOGLE_CLIENT_ID', '978330457998-m4htfrb34sge1nr2fu2v6p0ncjicap58.apps.googleusercontent.com')) ?>"
                        data-login_uri="<?= htmlspecialchars(base_url('auth/google.php')) ?>"
                        data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin w-full flex justify-center"
                        data-type="standard"
                        data-size="large"
                        data-theme="outline"
                        data-text="sign_in_with"
                        data-shape="rectangular"
                        data-logo_alignment="left"
                        data-width="360">
                    </div>
                </div>
            </div>
        </form>

        <div class="text-center pt-2 border-t border-white/10 flex justify-between items-center text-xs text-slate-400">
            <a href="<?= base_url('register') ?>" class="text-emerald-400 font-bold hover:text-emerald-300 transition flex items-center gap-1">
                <i class="fa-solid fa-user-plus text-[10px]"></i> New Student? Sign Up
            </a>
            <a href="<?= base_url('index.php') ?>" class="hover:text-white transition">
                Homepage
            </a>
        </div>

    </div>

</body>
</html>
