<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Verify there is a pending multi-role user session
if (empty($_SESSION['temp_user_login'])) {
    header('Location: ' . base_url('login'));
    exit;
}

$tempUser = $_SESSION['temp_user_login'];
$roles = array_filter(array_map('trim', explode(',', $tempUser['role'])));

// Handle role selection submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $selectedRole = $_POST['role'] ?? '';
    
    if (in_array($selectedRole, $roles, true)) {
        // Complete registration/session with the chosen role
        $_SESSION['user'] = [
            'id'            => $tempUser['id'],
            'username'      => $tempUser['username'],
            'full_name'     => $tempUser['full_name'],
            'email'         => $tempUser['email'],
            'role'          => $selectedRole,
            'profile_photo' => $tempUser['profile_photo'],
        ];

        // Clear temporary session
        unset($_SESSION['temp_user_login']);

        // Redirect based on selected role
        if ($selectedRole === 'admin') {
            header('Location: ' . base_url('admin/'));
            exit;
        } elseif ($selectedRole === 'coordinator') {
            header('Location: ' . base_url('coordinator/'));
            exit;
        } elseif ($selectedRole === 'teacher') {
            header('Location: ' . base_url('teacher/'));
            exit;
        } elseif ($selectedRole === 'student') {
            header('Location: ' . base_url('student/'));
            exit;
        } else {
            header('Location: ' . base_url('index.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kauzariyya Portal - Choose Role</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6 overflow-hidden" style="background-color:#0e2e38; color:#ecf3d6;">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[20%] left-[25%] w-[45vw] h-[45vw] max-w-[500px] bg-emerald-500/10 blur-[100px]"></div>
        <div class="absolute bottom-[20%] right-[25%] w-[40vw] h-[40vw] max-w-[450px] bg-blue-600/10 blur-[120px]"></div>
    </div>

    <!-- Choose Role Card -->
    <div class="w-full max-w-md glass-panel rounded-3xl p-8 space-y-6 relative border border-white/10 text-center">
        
        <!-- Logo / Heading -->
        <div class="space-y-3">
            <div class="inline-flex w-14 h-14 rounded-full overflow-hidden bg-slate-950 border border-white/10 items-center justify-center p-1.5 shadow-lg">
                <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Login As</h1>
                <p class="text-xs text-slate-400 mt-1">Select a role workspace to continue</p>
            </div>
        </div>

        <!-- Greeting -->
        <div class="text-xs text-slate-350">
            Welcome back, <span class="text-emerald-400 font-bold"><?= htmlspecialchars($tempUser['full_name']) ?></span>. You hold multiple accounts:
        </div>

        <!-- Roles form list -->
        <form action="" method="POST" class="space-y-3">
            <?php foreach ($roles as $role): ?>
                <?php
                $roleLabel = ucfirst($role);
                $icon = 'fa-user-circle';
                $color = 'text-emerald-400';
                
                if ($role === 'admin') {
                    $icon = 'fa-user-shield';
                    $color = 'text-rose-450';
                } elseif ($role === 'coordinator') {
                    $icon = 'fa-network-wired';
                    $color = 'text-cyan-400';
                } elseif ($role === 'teacher') {
                    $icon = 'fa-chalkboard-user';
                    $color = 'text-amber-400';
                } elseif ($role === 'student') {
                    $icon = 'fa-graduation-cap';
                    $color = 'text-emerald-400';
                }
                ?>
                <button type="submit" name="role" value="<?= htmlspecialchars($role) ?>" 
                    class="w-full flex items-center justify-between p-4 rounded-xl border border-white/10 hover:border-emerald-400/30 bg-white/5 hover:bg-emerald-500/5 transition text-left">
                    <span class="flex items-center gap-3">
                        <i class="fa-solid <?= $icon ?> <?= $color ?> text-lg"></i>
                        <span class="text-xs font-bold text-white"><?= $roleLabel ?></span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-500"></i>
                </button>
            <?php endforeach; ?>
        </form>

        <div class="text-center pt-2 border-t border-white/10">
            <a href="<?= base_url('login') ?>" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-arrow-left"></i> Return to Login
            </a>
        </div>
    </div>

</body>
</html>
