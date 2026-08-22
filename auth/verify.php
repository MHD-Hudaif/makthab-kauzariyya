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

$error = '';
$preselectedType = $_GET['type'] ?? 'student';
if (!in_array($preselectedType, ['teacher', 'student'], true)) {
    $preselectedType = 'student';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $memberType = trim($_POST['member_type'] ?? '');
        $rosterId = (int)($_POST['roster_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $place = trim($_POST['place'] ?? '');
        $gender = trim($_POST['gender'] ?? 'male');
        if (!in_array($gender, ['male', 'female'], true)) {
            $gender = 'male';
        }
        $parentName = trim($_POST['parent_name'] ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!in_array($memberType, ['teacher', 'student'], true)) {
            throw new Exception("Please select whether you are a Teacher or a Student.");
        }

        if ($rosterId <= 0) {
            throw new Exception("Please search and select your name from our existing directory records.");
        }

        if (empty($fullName) || empty($username) || empty($email) || empty($phone) || empty($place) || empty($password)) {
            throw new Exception("Please fill in all required fields.");
        }

        if ($memberType === 'student' && (empty($parentName) || empty($parentPhone))) {
            throw new Exception("Please provide your Guardian / Parent name and mobile number.");
        }

        // Verify roster record existence and claim status
        $stmtRoster = $pdo->prepare("SELECT * FROM verification_roster WHERE id = ? AND type = ?");
        $stmtRoster->execute([$rosterId, $memberType]);
        $rosterEntry = $stmtRoster->fetch();

        if (!$rosterEntry) {
            throw new Exception("Selected directory profile could not be found.");
        }

        if ($rosterEntry['is_claimed']) {
            throw new Exception("The profile '{$rosterEntry['name']}' has already been verified and claimed. Please contact the Coordinator if this is an error.");
        }

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception("Username '@{$username}' is already taken. Please choose another username.");
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("An account with email '{$email}' already exists. Please log in or use another email.");
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        // 1. Insert into users as inactive (pending coordinator approval)
        $stmtUser = $pdo->prepare("
            INSERT INTO users (username, email, phone, place, password, full_name, gender, role, status, roster_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'inactive', ?)
        ");
        $stmtUser->execute([$username, $email, $phone, $place, $passwordHash, $fullName, $gender, $memberType, $rosterId]);
        $userId = (int)$pdo->lastInsertId();

        // 2. Insert into specific profile tables
        if ($memberType === 'student') {
            $admissionNo = 'ADM-OLD-' . date('Y') . '-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
            $stmtStudent = $pdo->prepare("
                INSERT INTO students (user_id, admission_no, parent_name, parent_phone) 
                VALUES (?, ?, ?, ?)
            ");
            $stmtStudent->execute([$userId, $admissionNo, $parentName, $parentPhone]);
        } elseif ($memberType === 'teacher') {
            $stmtTeacher = $pdo->prepare("
                INSERT INTO teachers (user_id, specialisation, date_of_joining) 
                VALUES (?, 'Quran Recitation & Islamic Studies', CURDATE())
            ");
            $stmtTeacher->execute([$userId]);
        }

        // 3. Mark roster claimed_user_id (pending status)
        $stmtUpdRoster = $pdo->prepare("UPDATE verification_roster SET claimed_user_id = ?, claimed_at = NOW() WHERE id = ?");
        $stmtUpdRoster->execute([$userId, $rosterId]);

        $pdo->commit();

        $_SESSION['msg_success'] = "Verification request submitted for {$rosterEntry['name']}! Your profile is pending Coordinator approval and activation.";
        header('Location: ' . base_url('login'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Old Student / Teacher Verification — Maktab Kauzariyya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .custom-radio:checked + label {
            background: rgba(109, 204, 141, 0.12);
            border-color: rgba(109, 204, 141, 0.4);
            color: #6dcc8d;
        }
        .autocomplete-dropdown {
            max-height: 220px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 md:p-8" style="background-color:#0e2e38; color:#ecf3d6;">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[10%] left-[20%] w-[45vw] h-[45vw] max-w-[500px] bg-emerald-500/10 blur-[100px] animate-blob-1"></div>
        <div class="absolute bottom-[10%] right-[20%] w-[40vw] h-[40vw] max-w-[450px] bg-blue-600/10 blur-[120px] animate-blob-2"></div>
    </div>

    <!-- Verification Card -->
    <div class="w-full max-w-2xl glass-panel rounded-3xl p-6 md:p-10 space-y-6 relative border border-white/10 my-8">
        
        <!-- Logo / Heading -->
        <div class="text-center space-y-2">
            <div class="inline-flex w-14 h-14 rounded-full overflow-hidden bg-slate-950 border border-white/10 items-center justify-center p-1.5 shadow-lg">
                <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Existing Member Verification</h1>
                <p class="text-xs text-slate-400 uppercase tracking-widest mt-1">Claim your Teacher / Student Profile</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-2xl text-xs flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-lg flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="" method="POST" id="verify-form" class="space-y-6">
            
            <!-- Step 1: Member Type Selector -->
            <div class="space-y-2">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Step 1: Select Member Role <span class="text-red-400">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <input type="radio" name="member_type" id="type-student" value="student" <?= $preselectedType === 'student' ? 'checked' : '' ?> class="hidden custom-radio" onchange="onTypeChange('student')">
                        <label for="type-student" class="w-full flex items-center justify-center gap-2 p-3.5 rounded-2xl border border-white/10 bg-white/5 font-bold text-xs cursor-pointer transition">
                            <i class="fa-solid fa-graduation-cap text-base"></i> Existing Student
                        </label>
                    </div>
                    <div>
                        <input type="radio" name="member_type" id="type-teacher" value="teacher" <?= $preselectedType === 'teacher' ? 'checked' : '' ?> class="hidden custom-radio" onchange="onTypeChange('teacher')">
                        <label for="type-teacher" class="w-full flex items-center justify-center gap-2 p-3.5 rounded-2xl border border-white/10 bg-white/5 font-bold text-xs cursor-pointer transition">
                            <i class="fa-solid fa-chalkboard-user text-base"></i> Teacher / Ustad
                        </label>
                    </div>
                </div>
            </div>

            <!-- Step 2: Live Search Directory -->
            <div class="space-y-2 relative">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">
                    Step 2: Search Your Name in Manual Directory <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="roster-search-input" autocomplete="off" class="glass-input w-full pl-10 pr-10 py-3.5 rounded-2xl text-sm" placeholder="Type your name (e.g. FATHIMA, NOOH, MIRZA)..." oninput="onSearchInput(this.value)">
                    <button type="button" id="clear-search-btn" onclick="clearSelectedRoster()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white hidden">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Autocomplete Dropdown List -->
                <div id="roster-dropdown" class="autocomplete-dropdown absolute left-0 right-0 top-full mt-1.5 z-30 rounded-2xl border border-white/15 shadow-2xl backdrop-blur-xl hidden" style="background: rgba(14, 46, 56, 0.98);"></div>

                <!-- Hidden Selected Roster ID -->
                <input type="hidden" name="roster_id" id="roster-id-input" value="<?= htmlspecialchars($_POST['roster_id'] ?? '') ?>">

                <!-- Selected Badge Preview -->
                <div id="selected-badge" class="hidden p-3.5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-white block" id="selected-name-text"></span>
                            <span class="text-[10px] text-emerald-300" id="selected-meta-text"></span>
                        </div>
                    </div>
                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-emerald-400/20 text-emerald-300 border border-emerald-400/30">Matched</span>
                </div>
            </div>

            <!-- Step 3: Profile Details Section -->
            <div id="phase2-section" class="space-y-4 pt-2 border-t border-white/10">
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-id-card text-emerald-400"></i> Step 3: Contact & Verification Details
                    </h3>
                    <p class="text-[11px] text-slate-400">Provide your active contact info to complete account linking.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Full Name -->
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-regular fa-user"></i></span>
                            <input type="text" name="full_name" id="full-name-input" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Your full name">
                        </div>
                    </div>

                    <!-- Username -->
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-at"></i></span>
                            <input type="text" name="username" id="username-input" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="e.g. fathima_z">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Email -->
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Email (Gmail) <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-regular fa-envelope"></i></span>
                            <input type="email" name="email" id="email-input" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="name@gmail.com">
                        </div>
                    </div>

                    <!-- Mobile Number -->
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Mobile Number (WhatsApp) <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-phone"></i></span>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="+91 9876543210">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Gender -->
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Gender <span class="text-red-400">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center justify-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer text-xs font-semibold text-slate-200 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 has-[:checked]:text-emerald-300 transition">
                                <input type="radio" name="gender" value="male" id="gender-male" <?= ($_POST['gender'] ?? 'male') === 'male' ? 'checked' : '' ?> class="accent-emerald-400">
                                <i class="fa-solid fa-mars text-sm"></i> Male
                            </label>
                            <label class="flex items-center justify-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer text-xs font-semibold text-slate-200 has-[:checked]:border-pink-400 has-[:checked]:bg-pink-500/10 has-[:checked]:text-pink-300 transition">
                                <input type="radio" name="gender" value="female" id="gender-female" <?= ($_POST['gender'] ?? '') === 'female' ? 'checked' : '' ?> class="accent-pink-400">
                                <i class="fa-solid fa-venus text-sm"></i> Female
                            </label>
                        </div>
                    </div>

                    <!-- Place / Location -->
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Place / City <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-location-dot"></i></span>
                            <input type="text" name="place" value="<?= htmlspecialchars($_POST['place'] ?? '') ?>" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="e.g. Kochi, Kerala / Dubai, UAE">
                        </div>
                    </div>
                </div>

                <!-- Student specific Guardian fields -->
                <div id="student-guardian-fields" class="space-y-4 pt-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Guardian Name -->
                        <div class="space-y-1.5">
                            <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Guardian / Parent Name <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-people-roof"></i></span>
                                <input type="text" name="parent_name" id="parent-name-input" value="<?= htmlspecialchars($_POST['parent_name'] ?? '') ?>" class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Parent or guardian name">
                            </div>
                        </div>

                        <!-- Guardian Mobile -->
                        <div class="space-y-1.5">
                            <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Guardian Mobile Number <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-mobile-screen-button"></i></span>
                                <input type="tel" name="parent_phone" id="parent-phone-input" value="<?= htmlspecialchars($_POST['parent_phone'] ?? '') ?>" class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Guardian phone">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Account Password <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" required class="glass-input w-full pl-10 pr-4 py-3 rounded-xl text-sm" placeholder="Create your login password">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-3">
                <button type="submit" class="w-full bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold py-4 rounded-2xl hover:shadow-lg hover:shadow-emerald-400/25 transition duration-300 text-sm">
                    Submit Profile for Coordinator Approval
                </button>
            </div>
        </form>

        <div class="text-center pt-3 border-t border-white/10 flex justify-between items-center text-xs text-slate-400">
            <a href="<?= base_url('login') ?>" class="hover:text-white transition flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Back to Login
            </a>
            <a href="<?= base_url('index.php') ?>" class="hover:text-white transition">
                Homepage
            </a>
        </div>

    </div>

    <!-- Autocomplete Javascript Logic -->
    <script>
        let currentType = '<?= $preselectedType ?>';
        let debounceTimer = null;

        function onTypeChange(newType) {
            currentType = newType;
            clearSelectedRoster();
            
            const guardianFields = document.getElementById('student-guardian-fields');
            const parentNameInput = document.getElementById('parent-name-input');
            const parentPhoneInput = document.getElementById('parent-phone-input');

            if (newType === 'student') {
                guardianFields.classList.remove('hidden');
                parentNameInput.required = true;
                parentPhoneInput.required = true;
            } else {
                guardianFields.classList.add('hidden');
                parentNameInput.required = false;
                parentPhoneInput.required = false;
            }

            const searchInput = document.getElementById('roster-search-input');
            searchInput.placeholder = newType === 'student' 
                ? 'Type student name (e.g. MIRZA, NOUFAL, SHANAVAS)...' 
                : 'Type teacher/ustad name (e.g. FATHIMA, NOOH, ASHIF)...';
        }

        function onSearchInput(val) {
            clearTimeout(debounceTimer);
            const query = val.trim();
            const dropdown = document.getElementById('roster-dropdown');

            if (query.length < 1) {
                dropdown.classList.add('hidden');
                dropdown.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`<?= base_url('api/lookup_roster.php') ?>?type=${currentType}&query=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!Array.isArray(data) || data.length === 0) {
                            dropdown.innerHTML = `
                                <div class="p-3 text-center text-xs text-slate-400">
                                    No unclaimed directory records matching "<strong>${escapeHtml(query)}</strong>".
                                </div>
                            `;
                            dropdown.classList.remove('hidden');
                            return;
                        }

                        let html = '';
                        data.forEach(item => {
                            const isClaimed = parseInt(item.is_claimed) === 1;
                            const subText = item.assigned_teacher_name ? `Teacher: ${escapeHtml(item.assigned_teacher_name)}` : (item.type === 'teacher' ? 'Faculty Directory' : 'Student Roster');
                            
                            if (isClaimed) {
                                html += `
                                    <div class="p-3 border-b border-white/5 opacity-50 cursor-not-allowed flex justify-between items-center text-xs">
                                        <div>
                                            <span class="font-bold text-slate-400">${escapeHtml(item.name)}</span>
                                            <span class="text-[10px] text-slate-500 block">${subText}</span>
                                        </div>
                                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300">Already Claimed</span>
                                    </div>
                                `;
                            } else {
                                html += `
                                    <div onclick="selectRosterItem(${item.id}, '${escapeJs(item.name)}', '${escapeJs(item.assigned_teacher_name || '')}')" class="p-3 border-b border-white/5 hover:bg-emerald-500/10 cursor-pointer flex justify-between items-center text-xs transition">
                                        <div>
                                            <span class="font-bold text-white hover:text-emerald-300">${escapeHtml(item.name)}</span>
                                            <span class="text-[10px] text-slate-400 block">${subText}</span>
                                        </div>
                                        <span class="text-[9px] uppercase px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Select</span>
                                    </div>
                                `;
                            }
                        });

                        dropdown.innerHTML = html;
                        dropdown.classList.remove('hidden');
                    })
                    .catch(err => {
                        console.error(err);
                        dropdown.classList.add('hidden');
                    });
            }, 250);
        }

        function selectRosterItem(id, name, assignedTeacher) {
            document.getElementById('roster-id-input').value = id;
            document.getElementById('roster-search-input').value = name;
            document.getElementById('roster-search-input').readOnly = true;
            document.getElementById('clear-search-btn').classList.remove('hidden');
            document.getElementById('roster-dropdown').classList.add('hidden');

            document.getElementById('selected-badge').classList.remove('hidden');
            document.getElementById('selected-name-text').textContent = name;
            document.getElementById('selected-meta-text').textContent = assignedTeacher ? `Assigned Teacher: ${assignedTeacher}` : `${currentType.toUpperCase()} Directory Record`;

            // Auto-populate full name & suggested username
            const fullNameInput = document.getElementById('full-name-input');
            const usernameInput = document.getElementById('username-input');
            
            fullNameInput.value = name;
            
            // Clean username format (lowercase, underscores)
            let suggestedUser = name.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
            usernameInput.value = suggestedUser;

            // Smart gender detection from name keywords
            const upper = name.toUpperCase();
            const femaleKeywords = ['TEACHER', 'FATHIMA', 'AYISHA', 'AISHA', 'SUMAYYA', 'RUSHDA', 'MANSOORA', 'FIDA', 'HALEEMA', 'HANAN', 'HIBA', 'AMINA', 'ADHILA', 'NISA', 'SWALIHA', 'ABINA', 'BEEMA', 'SHAHANA', 'FILLATH', 'AJMIYA', 'AIZA', 'HAZINE', 'IZZA', 'HANNA', 'ZULAIKHA', 'AMNA', 'NADIYA', 'ASIYA', 'NAJUMA', 'SUHAILA'];
            const isFemale = femaleKeywords.some(k => upper.includes(k));
            
            if (isFemale) {
                document.getElementById('gender-female').checked = true;
            } else {
                document.getElementById('gender-male').checked = true;
            }
        }

        function clearSelectedRoster() {
            document.getElementById('roster-id-input').value = '';
            document.getElementById('roster-search-input').value = '';
            document.getElementById('roster-search-input').readOnly = false;
            document.getElementById('clear-search-btn').classList.add('hidden');
            document.getElementById('selected-badge').classList.add('hidden');
            document.getElementById('roster-dropdown').classList.add('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeJs(text) {
            return (text || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
        }

        // Initialize view based on preselected type
        document.addEventListener('DOMContentLoaded', () => {
            onTypeChange(currentType);
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#roster-search-input') && !e.target.closest('#roster-dropdown')) {
                document.getElementById('roster-dropdown').classList.add('hidden');
            }
        });
    </script>
</body>
</html>
