<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE STUDENT
        if ($action === 'save_student') {
            $userId = $_POST['student_id'] ?? '';
            $rosterId = $_POST['roster_id'] ?? null;
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? null);
            $phone = trim($_POST['phone'] ?? null);
            $gender = $_POST['gender'] ?? 'male';
            $place = trim($_POST['place'] ?? '');
            $admission_no = trim($_POST['admission_no'] ?? '');
            $parent_name = trim($_POST['parent_name'] ?? '');
            $parent_phone = trim($_POST['parent_phone'] ?? null);
            $dob = trim($_POST['dob'] ?? null);
            $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;

            if (empty($userId)) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    throw new Exception("Username '{$username}' already exists.");
                }

                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, gender, place, role, status, roster_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', 'active', ?)");
                $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $gender, $place, $rosterId ?: null]);
                $userId = $pdo->lastInsertId();

                if (!empty($rosterId)) {
                    $pdo->prepare("UPDATE verification_roster SET is_claimed = 1, claimed_user_id = ?, claimed_at = NOW() WHERE id = ?")->execute([$userId, $rosterId]);
                }

                $stmt2 = $pdo->prepare("INSERT INTO students (user_id, admission_no, parent_name, parent_phone, dob, class_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt2->execute([$userId, $admission_no, $parent_name, $parent_phone, $dob ?: null, $class_id]);
                $_SESSION['msg_success'] = "Student '{$full_name}' enrolled successfully.";
            } else {
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ?, gender = ?, place = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $gender, $place, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, gender = ?, place = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $phone, $gender, $place, $userId]);
                }

                // Check profile
                $chkProf = $pdo->prepare("SELECT user_id FROM students WHERE user_id = ?");
                $chkProf->execute([$userId]);
                if ($chkProf->fetch()) {
                    $stmt2 = $pdo->prepare("UPDATE students SET admission_no = ?, parent_name = ?, parent_phone = ?, dob = ?, class_id = ? WHERE user_id = ?");
                    $stmt2->execute([$admission_no, $parent_name, $parent_phone, $dob ?: null, $class_id, $userId]);
                } else {
                    $stmt2 = $pdo->prepare("INSERT INTO students (user_id, admission_no, parent_name, parent_phone, dob, class_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt2->execute([$userId, $admission_no, $parent_name, $parent_phone, $dob ?: null, $class_id]);
                }
                $_SESSION['msg_success'] = "Student '{$full_name}' updated successfully.";
            }

            header("Location: students.php");
            exit;
        }

        // 2. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'student') {
                $pdo->prepare("UPDATE verification_roster SET is_claimed = 0, claimed_user_id = NULL, claimed_at = NULL WHERE claimed_user_id = ?")->execute([$id]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Student record deleted successfully.";
            }
            header("Location: students.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: students.php");
        exit;
    }
}

// --- FETCH DATA ---
// 1. Registered Student Users
$registeredStudents = $pdo->query("
    SELECT s.user_id as student_id, u.username as student_username, u.full_name as student_name, 
           u.email as student_email, u.phone as student_phone, u.gender as student_gender,
           u.place as student_place, u.status as student_status,
           s.admission_no, s.parent_name, s.parent_phone, s.dob, s.class_id,
           c.name as class_name, vr.assigned_teacher_name, vr.id as roster_id,
           'registered' as reg_type
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN verification_roster vr ON u.roster_id = vr.id
    WHERE u.role = 'student'
    ORDER BY u.full_name ASC
")->fetchAll();

// 2. Unregistered Roster Students (Not claimed yet)
$unregisteredStudents = $pdo->query("
    SELECT NULL as student_id, NULL as student_username, vr.name as student_name,
           NULL as student_email, NULL as student_phone,
           IF(vr.name LIKE '%SUMAYYA%' OR vr.name LIKE '%NISA%' OR vr.name LIKE '%SWALIHA%' OR vr.name LIKE '%ABINA%' OR vr.name LIKE '%HAMRA%' OR vr.name LIKE '%BEEMA%' OR vr.name LIKE '%SHAHANA%' OR vr.name LIKE '%FILLATH%' OR vr.name LIKE '%AJMIYA%' OR vr.name LIKE '%AIZA%' OR vr.name LIKE '%HAZINE%' OR vr.name LIKE '%IZZA%' OR vr.name LIKE '%HANNA%' OR vr.name LIKE '%ZULAIKHA%' OR vr.name LIKE '%HIBA%' OR vr.name LIKE '%AMNA%' OR vr.name LIKE '%NADIYA%' OR vr.name LIKE '%ASIYA%' OR vr.name LIKE '%NAJUMA%' OR vr.name LIKE '%FATHIMA%' OR vr.name LIKE '%SUHAILA%', 'female', 'male') as student_gender,
           NULL as student_place, 'unregistered' as student_status,
           CONCAT('ROSTER-', vr.id) as admission_no, NULL as parent_name, NULL as parent_phone, NULL as dob, NULL as class_id,
           NULL as class_name, vr.assigned_teacher_name, vr.id as roster_id,
           'unregistered' as reg_type
    FROM verification_roster vr
    WHERE vr.type = 'student' 
      AND (vr.is_claimed = 0 OR vr.claimed_user_id IS NULL)
      AND vr.name NOT IN (SELECT full_name FROM users WHERE role = 'student')
    ORDER BY vr.name ASC
")->fetchAll();

// Combine
$allStudents = array_merge($registeredStudents, $unregisteredStudents);

// Stats
$totalStudents = count($allStudents);
$registeredCount = count(array_filter($allStudents, fn($s) => $s['reg_type'] === 'registered' && $s['student_status'] === 'active'));
$pendingCount = count(array_filter($allStudents, fn($s) => $s['reg_type'] === 'registered' && $s['student_status'] === 'inactive'));
$unregisteredCount = count(array_filter($allStudents, fn($s) => $s['reg_type'] === 'unregistered'));
$regPercent = $totalStudents > 0 ? round(($registeredCount / $totalStudents) * 100) : 0;

$classes = $pdo->query("SELECT id, name FROM classes ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Top Header & Actions -->
<div class="flex flex-wrap gap-4 items-center justify-between">
    <div>
        <h3 class="text-xl font-bold text-white flex items-center gap-2.5">
            <i class="fa-solid fa-graduation-cap text-emerald-400"></i> Students Registry
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">Directory of registered students and pending roster admissions</p>
    </div>
    <button onclick="openStudentModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
        <i class="fa-solid fa-user-plus"></i> Add Student
    </button>
</div>

<!-- Status & Progress Dashboard Bar -->
<div class="glass-panel rounded-2xl p-5 border border-white/10 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-6">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Total Students</span>
                <span class="text-2xl font-black text-white"><?= $totalStudents ?></span>
            </div>
            <div class="h-8 w-px bg-white/10"></div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-400 block">Registered & Active</span>
                <span class="text-2xl font-black text-emerald-300"><?= $registeredCount ?> <span class="text-xs font-normal text-slate-400">(<?= $regPercent ?>%)</span></span>
            </div>
            <div class="h-8 w-px bg-white/10"></div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Unregistered Roster</span>
                <span class="text-2xl font-black text-slate-300"><?= $unregisteredCount ?></span>
            </div>
            <?php if ($pendingCount > 0): ?>
                <div class="h-8 w-px bg-white/10"></div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-400 block">Pending Approval</span>
                    <span class="text-2xl font-black text-amber-300"><?= $pendingCount ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filter Pill Tabs -->
        <div class="flex items-center bg-slate-950/60 p-1 rounded-xl border border-white/10 text-xs font-semibold">
            <button onclick="filterStudents('all')" id="btn-tab-all" class="px-3.5 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-300 transition">
                All (<?= $totalStudents ?>)
            </button>
            <button onclick="filterStudents('registered')" id="btn-tab-registered" class="px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-white transition">
                Registered (<?= $registeredCount ?>)
            </button>
            <button onclick="filterStudents('unregistered')" id="btn-tab-unregistered" class="px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-white transition">
                Unregistered (<?= $unregisteredCount ?>)
            </button>
        </div>
    </div>

    <!-- Dual Color Progress Bar -->
    <div class="w-full bg-slate-900 rounded-full h-2.5 overflow-hidden flex border border-white/5">
        <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-2.5 transition-all duration-500" style="width: <?= $regPercent ?>%" title="Registered: <?= $registeredCount ?>"></div>
        <div class="bg-slate-700/60 h-2.5 transition-all duration-500" style="width: <?= 100 - $regPercent ?>%" title="Unregistered: <?= $unregisteredCount ?>"></div>
    </div>
</div>

<!-- Students Table Grid -->
<div class="glass-panel rounded-2xl overflow-hidden border border-white/10">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-white/10 text-xs font-bold text-slate-400 uppercase tracking-wider" style="background:rgba(18,59,71,0.4);">
                    <th class="py-4 px-6">Student & Gender</th>
                    <th class="py-4 px-6">Admission No / ID</th>
                    <th class="py-4 px-6">Assigned Ustad / Teacher</th>
                    <th class="py-4 px-6">Class / Stream</th>
                    <th class="py-4 px-6">Guardian & Phone</th>
                    <th class="py-4 px-6">Place</th>
                    <th class="py-4 px-6">Status</th>
                    <th class="py-4 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y text-xs" style="divide-color:rgba(109,204,141,0.06);">
                <?php if (empty($allStudents)): ?>
                    <tr>
                        <td colspan="8" class="py-8 px-6 text-center text-slate-500 font-medium">No students found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allStudents as $student): ?>
                        <?php 
                        $isReg = ($student['reg_type'] === 'registered');
                        $isInactive = ($isReg && $student['student_status'] === 'inactive');
                        ?>
                        <tr class="student-row hover:bg-white/[0.02] transition-colors" data-status="<?= $isReg ? ($isInactive ? 'pending' : 'registered') : 'unregistered' ?>">
                            
                            <!-- Name & Gender -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center font-bold text-xs <?= $student['student_gender'] === 'female' ? 'bg-pink-500/20 text-pink-300 border border-pink-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' ?>">
                                        <?= strtoupper(substr($student['student_name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-white text-sm"><?= htmlspecialchars($student['student_name']) ?></span>
                                            <?php if ($student['student_gender'] === 'female'): ?>
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-pink-500/15 text-pink-300 border border-pink-500/30">Female</span>
                                            <?php else: ?>
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-blue-500/15 text-blue-300 border border-blue-500/30">Male</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isReg && !empty($student['student_username'])): ?>
                                            <span class="text-[10px] text-slate-400 font-mono">@<?= htmlspecialchars($student['student_username']) ?></span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-500 font-mono">Roster Entry #<?= $student['roster_id'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Admission No / ID -->
                            <td class="py-4 px-6">
                                <span class="font-mono text-slate-300 text-xs bg-white/5 px-2.5 py-1 rounded border border-white/10 font-semibold">
                                    <?= htmlspecialchars($student['admission_no'] ?? '—') ?>
                                </span>
                            </td>

                            <!-- Assigned Ustad / Teacher -->
                            <td class="py-4 px-6">
                                <?php if (!empty($student['assigned_teacher_name'])): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-cyan-300 bg-cyan-500/10 border border-cyan-500/20 px-2 py-0.5 rounded">
                                        <i class="fa-solid fa-user-tie text-[10px]"></i> <?= htmlspecialchars($student['assigned_teacher_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-500 italic text-[11px]">Unassigned</span>
                                <?php endif; ?>
                            </td>

                            <!-- Class / Stream -->
                            <td class="py-4 px-6">
                                <?php if (!empty($student['class_name'])): ?>
                                    <span class="text-xs px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-semibold">
                                        <?= htmlspecialchars($student['class_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-500 italic text-[11px]">Not Allotted</span>
                                <?php endif; ?>
                            </td>

                            <!-- Guardian & Phone -->
                            <td class="py-4 px-6 space-y-0.5">
                                <?php if ($isReg): ?>
                                    <div class="text-slate-200 font-medium"><?= htmlspecialchars($student['parent_name'] ?? '—') ?></div>
                                    <div class="text-slate-400 font-mono text-[11px]"><?= htmlspecialchars($student['parent_phone'] ?? $student['student_phone'] ?? '—') ?></div>
                                <?php else: ?>
                                    <span class="text-slate-500 text-[11px]">— Pending verification —</span>
                                <?php endif; ?>
                            </td>

                            <!-- Place -->
                            <td class="py-4 px-6">
                                <span class="text-slate-300"><?= htmlspecialchars($student['student_place'] ?? '—') ?></span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-4 px-6">
                                <?php if (!$isReg): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 bg-slate-800/80 border border-slate-700 px-2.5 py-1 rounded-full">
                                        <i class="fa-regular fa-clock text-[9px]"></i> Unregistered
                                    </span>
                                <?php elseif ($isInactive): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-300 bg-amber-500/10 border border-amber-500/30 px-2.5 py-1 rounded-full">
                                        <i class="fa-solid fa-hourglass-half text-[9px]"></i> Pending Approval
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 px-2.5 py-1 rounded-full">
                                        <i class="fa-solid fa-check text-[9px]"></i> Enrolled
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php if ($isReg): ?>
                                        <?php 
                                        $studentData = [
                                            'student_id' => $student['student_id'],
                                            'username' => $student['student_username'],
                                            'full_name' => $student['student_name'],
                                            'email' => $student['student_email'],
                                            'phone' => $student['student_phone'],
                                            'gender' => $student['student_gender'],
                                            'place' => $student['student_place'],
                                            'admission_no' => $student['admission_no'],
                                            'parent_name' => $student['parent_name'],
                                            'parent_phone' => $student['parent_phone'],
                                            'dob' => $student['dob'],
                                            'class_id' => $student['class_id']
                                        ];
                                        ?>
                                        <button onclick="openStudentModal(<?= htmlspecialchars(json_encode($studentData)) ?>)" class="p-2 text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg border border-white/8 transition" title="Edit Student">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button onclick="confirmDelete('student', <?= $student['student_id'] ?>)" class="p-2 text-xs font-semibold bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded-lg border border-red-500/10 transition" title="Delete Student">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <!-- Quick Direct Enroll for Unregistered Roster -->
                                        <?php 
                                        $quickData = [
                                            'student_id' => '',
                                            'roster_id' => $student['roster_id'],
                                            'username' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $student['student_name'])),
                                            'full_name' => $student['student_name'],
                                            'gender' => $student['student_gender'],
                                            'admission_no' => 'KAUZ-' . date('y') . '-' . str_pad($student['roster_id'], 3, '0', STR_PAD_LEFT),
                                            'parent_name' => '',
                                            'parent_phone' => '',
                                            'place' => '',
                                            'email' => '',
                                            'phone' => '',
                                            'dob' => '',
                                            'class_id' => ''
                                        ];
                                        ?>
                                        <button onclick="openStudentModal(<?= htmlspecialchars(json_encode($quickData)) ?>)" class="px-2.5 py-1.5 text-[11px] font-bold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-lg border border-emerald-500/25 transition flex items-center gap-1.5" title="Direct Enroll">
                                            <i class="fa-solid fa-user-check"></i> Enroll
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODALS ================= -->
<!-- Student Modal -->
<div id="modal-student" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/70 backdrop-blur-sm">
    <div class="w-full max-w-lg glass-panel rounded-3xl p-8 relative space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center">
            <h3 id="student-modal-title" class="text-xl font-bold text-white">Add New Student</h3>
            <button onclick="closeModal('student')" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form action="students.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_student">
            <input type="hidden" name="student_id" id="student_id">
            <input type="hidden" name="roster_id" id="student_roster_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="student_username" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username <span class="text-red-400">*</span></label>
                    <input type="text" name="username" id="student_username" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. student_01" required>
                </div>
                <div class="space-y-1.5">
                    <label for="student_password" id="lbl-student-password" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Password <span class="text-red-400">*</span></label>
                    <input type="password" name="password" id="student_password" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="••••••••" required>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 space-y-1.5">
                    <label for="student_fullname" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="full_name" id="student_fullname" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Shanavas Kochi" required>
                </div>
                <div class="space-y-1.5">
                    <label for="student_gender" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Gender</label>
                    <select name="gender" id="student_gender" class="glass-input w-full px-3 py-3 rounded-xl text-sm">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="student_admission" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Admission No</label>
                    <input type="text" name="admission_no" id="student_admission" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. KAUZ-26-001">
                </div>
                <div class="space-y-1.5">
                    <label for="student_class" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Assign Class</label>
                    <select name="class_id" id="student_class" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="student_parent" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Parent / Guardian</label>
                    <input type="text" name="parent_name" id="student_parent" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="Guardian name">
                </div>
                <div class="space-y-1.5">
                    <label for="student_parent_phone" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Guardian Phone</label>
                    <input type="tel" name="parent_phone" id="student_parent_phone" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="+91 9876543210">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="student_place" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Place / Location</label>
                    <input type="text" name="place" id="student_place" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Kochi, Kerala">
                </div>
                <div class="space-y-1.5">
                    <label for="student_dob" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Date of Birth</label>
                    <input type="date" name="dob" id="student_dob" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('student')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs hover:shadow-lg hover:shadow-emerald-400/20 transition">Save Student</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab Filter
    function filterStudents(filter) {
        document.querySelectorAll('.student-row').forEach(row => {
            const status = row.getAttribute('data-status');
            if (filter === 'all') {
                row.style.display = '';
            } else if (filter === 'registered') {
                row.style.display = (status === 'registered' || status === 'pending') ? '' : 'none';
            } else if (filter === 'unregistered') {
                row.style.display = (status === 'unregistered') ? '' : 'none';
            }
        });

        // Tab button styles
        ['all', 'registered', 'unregistered'].forEach(tab => {
            const btn = document.getElementById(`btn-tab-${tab}`);
            if (tab === filter) {
                btn.className = 'px-3.5 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-300 transition';
            } else {
                btn.className = 'px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-white transition';
            }
        });
    }

    // Student Modal Manager
    function openStudentModal(data = null) {
        const modal = document.getElementById('modal-student');
        const title = document.getElementById('student-modal-title');
        const form = modal.querySelector('form');
        const passwordLabel = document.getElementById('lbl-student-password');
        const passwordInput = document.getElementById('student_password');
        const usernameInput = document.getElementById('student_username');
        const genderSelect = document.getElementById('student_gender');
        
        form.reset();
        document.getElementById('student_id').value = '';
        document.getElementById('student_roster_id').value = '';

        if (data) {
            if (data.student_id) {
                title.innerText = 'Edit Student Profile';
                document.getElementById('student_id').value = data.student_id;
                passwordLabel.innerText = 'New Password (leave blank to keep)';
                passwordInput.removeAttribute('required');
            } else {
                title.innerText = 'Direct Enroll Student (Roster Link)';
                if (data.roster_id) {
                    document.getElementById('student_roster_id').value = data.roster_id;
                }
                passwordLabel.innerText = 'Set Initial Password *';
                passwordInput.setAttribute('required', 'required');
            }

            usernameInput.value = data.username || '';
            document.getElementById('student_fullname').value = data.full_name || '';
            document.getElementById('student_admission').value = data.admission_no || '';
            document.getElementById('student_parent').value = data.parent_name || '';
            document.getElementById('student_parent_phone').value = data.parent_phone || '';
            document.getElementById('student_place').value = data.place || '';
            document.getElementById('student_dob').value = data.dob || '';
            document.getElementById('student_class').value = data.class_id || '';
            if (genderSelect && data.gender) {
                genderSelect.value = data.gender;
            }
        } else {
            title.innerText = 'Add New Student';
            passwordLabel.innerText = 'Password *';
            passwordInput.setAttribute('required', 'required');
        }
        modal.classList.remove('hidden');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
