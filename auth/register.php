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
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

$googlePending = $_SESSION['google_pending_registration'] ?? null;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $place = trim($_POST['place'] ?? '');
        $parentName = trim($_POST['parent_name'] ?? '');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $password = $_POST['password'] ?? '';

        if (empty($fullName) || empty($username) || empty($email) || empty($phone) || empty($place)) {
            throw new Exception("Please fill in all required fields.");
        }

        // If manual registration without Google, password is required
        if (!$googlePending && empty($password)) {
            throw new Exception("Please enter a password for your account.");
        }

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception("Username '@{$username}' is already taken. Please choose another.");
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("An account with email '{$email}' already exists. Please log in.");
        }

        // Determine credentials & Google linkage
        $googleId = $googlePending['google_id'] ?? null;
        $profilePhoto = $googlePending['profile_photo'] ?? null;
        $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        // 1. Insert into users
        $stmtUser = $pdo->prepare("
            INSERT INTO users (username, email, phone, place, password, full_name, role, status, google_id, profile_photo) 
            VALUES (?, ?, ?, ?, ?, ?, 'student', 'active', ?, ?)
        ");
        $stmtUser->execute([$username, $email, $phone, $place, $passwordHash, $fullName, $googleId, $profilePhoto]);
        $userId = $pdo->lastInsertId();

        // 2. Generate admission number & insert into students table
        $admissionNo = 'ADM-' . date('Y') . '-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
        $stmtStudent = $pdo->prepare("
            INSERT INTO students (user_id, admission_no, parent_name, dob) 
            VALUES (?, ?, ?, ?)
        ");
        $stmtStudent->execute([$userId, $admissionNo, $parentName, $dob]);

        $pdo->commit();

        // Clear Google pending session
        unset($_SESSION['google_pending_registration']);

        // Log the user in
        $_SESSION['user'] = [
            'id'            => $userId,
            'username'      => $username,
            'full_name'     => $fullName,
            'email'         => $email,
            'role'          => 'student',
            'profile_photo' => $profilePhoto,
        ];

        $_SESSION['msg_success'] = "Welcome to Maktab Kauzariyya, {$fullName}! Your account has been registered.";
        header('Location: ' . base_url('student/'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Auto-populate default fields if coming from Google
$prefillName = $googlePending['full_name'] ?? '';
$prefillEmail = $googlePending['email'] ?? '';
$prefillUsername = !empty($prefillEmail) ? explode('@', $prefillEmail)[0] : '';
// Filter out non-alphanumeric characters for clean username
$prefillUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $prefillUsername);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kauzariyya Portal - Student Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6" style="background-color:#0e2e38; color:#ecf3d6;">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[10%] left-[20%] w-[45vw] h-[45vw] max-w-[500px] bg-emerald-500/10 blur-[100px] animate-blob-1"></div>
        <div class="absolute bottom-[10%] right-[20%] w-[40vw] h-[40vw] max-w-[450px] bg-blue-600/10 blur-[120px] animate-blob-2"></div>
    </div>

    <!-- Registration Card -->
    <div class="w-full max-w-xl glass-panel rounded-3xl p-8 space-y-6 relative border border-white/10 my-8">
        
        <!-- Logo / Heading -->
        <div class="text-center space-y-2">
            <div class="inline-flex w-14 h-14 rounded-full overflow-hidden bg-slate-950 border border-white/10 items-center justify-center p-1.5 shadow-lg">
                <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Student Registration</h1>
                <p class="text-xs text-slate-400 uppercase tracking-widest mt-1">Maktab Kauzariyya Admission</p>
            </div>
        </div>

        <!-- Google Welcome Banner -->
        <?php if ($googlePending): ?>
            <div class="p-4 rounded-2xl border flex items-center gap-3.5" style="background:rgba(109,204,141,0.08); border-color:rgba(109,204,141,0.25);">
                <?php if (!empty($googlePending['profile_photo'])): ?>
                    <img src="<?= htmlspecialchars($googlePending['profile_photo']) ?>" alt="Avatar" class="w-10 h-10 rounded-full border border-white/20 object-cover flex-shrink-0">
                <?php else: ?>
                    <i class="fa-brands fa-google text-2xl text-emerald-400 flex-shrink-0"></i>
                <?php endif; ?>
                <div class="text-xs text-slate-300">
                    <span class="font-bold text-white block">Connected with Google</span>
                    <span>Complete your mobile number and place to finish setting up your account.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-3.5 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="" method="POST" class="space-y-4">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Full Name -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-regular fa-user"></i></span>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? $prefillName) ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="e.g. MHD Hudaif">
                    </div>
                </div>

                <!-- Username -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-at"></i></span>
                        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? $prefillUsername) ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="e.g. hudaif">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Email -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Email Address <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $prefillEmail) ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="e.g. name@domain.com" <?= $googlePending ? 'readonly style="opacity:0.8;"' : '' ?>>
                    </div>
                </div>

                <!-- Phone / Mobile -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Mobile Number (WhatsApp) <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-phone"></i></span>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="e.g. +91 9876543210">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Place / City -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Place / City <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-location-dot"></i></span>
                        <input type="text" name="place" value="<?= htmlspecialchars($_POST['place'] ?? '') ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="e.g. Kochi, Kerala / Dubai">
                    </div>
                </div>

                <!-- Parent / Guardian Name (Optional) -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Parent / Guardian Name</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-people-roof"></i></span>
                        <input type="text" name="parent_name" value="<?= htmlspecialchars($_POST['parent_name'] ?? '') ?>" class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Parent/Guardian name">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Date of Birth (Optional) -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Date of Birth</label>
                    <input type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                </div>

                <!-- Password (Optional if Google user) -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">
                        Password <?= $googlePending ? '<span class="text-slate-500 text-[10px] font-normal">(Optional with Google)</span>' : '<span class="text-red-400">*</span>' ?>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Set account password" <?= $googlePending ? '' : 'required' ?>>
                    </div>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold py-3.5 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition duration-300">
                    <?= $googlePending ? 'Complete Google Sign Up' : 'Create Account' ?>
                </button>
            </div>
        </form>

        <div class="text-center pt-2 border-t border-white/10 flex justify-between items-center text-xs text-slate-400">
            <a href="<?= base_url('login') ?>" class="hover:text-white transition flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Already have an account? Log In
            </a>
            <a href="<?= base_url('index.php') ?>" class="hover:text-white transition">
                Homepage
            </a>
        </div>

    </div>

</body>
</html>
