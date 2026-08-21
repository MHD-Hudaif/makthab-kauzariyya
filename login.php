<?php
session_start();
require_once 'db.php';

// Redirect if already logged in
if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'], ['coordinator', 'admin'])) {
    header('Location: coordinator.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Verify role permission
                if (in_array($user['role'], ['coordinator', 'admin'])) {
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'profile_photo' => $user['profile_photo']
                    ];
                    
                    // Update last login timestamp
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);

                    header('Location: coordinator.php');
                    exit;
                } else {
                    $error = 'Access Denied: Only coordinators and administrators are permitted to enter.';
                }
            } else {
                $error = 'Invalid username or password.';
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
    <link rel="stylesheet" href="assets/css/style.css">
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
                <h1 class="text-2xl font-bold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Coordinator Login</h1>
                <p class="text-xs text-slate-450 uppercase tracking-widest mt-1">Al Jamiathul Kauzariyya</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-3.5 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="login.php" method="POST" class="space-y-4">
            <div class="space-y-1.5">
                <label for="username" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-550"><i class="fa-regular fa-user"></i></span>
                    <input type="text" name="username" id="username" class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Enter username" required autocomplete="username">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="password" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-550"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" id="password" class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Enter password" required autocomplete="current-password">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold py-3.5 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition duration-300">
                    Sign In
                </button>
            </div>
        </form>

        <div class="text-center">
            <a href="index.php" class="inline-flex items-center gap-1.5 text-xs text-slate-450 hover:text-white transition">
                <i class="fa-solid fa-arrow-left"></i> Back to Homepage
            </a>
        </div>

    </div>

</body>
</html>
