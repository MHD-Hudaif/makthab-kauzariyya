<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE TEACHER
        if ($action === 'save_teacher') {
            $userId = $_POST['teacher_id'] ?? '';
            $rosterId = $_POST['roster_id'] ?? null;
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? null);
            $phone = trim($_POST['phone'] ?? null);
            $gender = $_POST['gender'] ?? 'male';
            $specialisation = trim($_POST['specialisation'] ?? '');
            $class_ids = $_POST['class_ids'] ?? [];

            if (empty($userId)) {
                // Validate Username
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    throw new Exception("Username '{$username}' already exists.");
                }

                // Insert User
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, gender, role, status, roster_id) VALUES (?, ?, ?, ?, ?, ?, 'teacher', 'active', ?)");
                $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $gender, $rosterId ?: null]);
                $userId = $pdo->lastInsertId();

                // If linked to roster entry, mark roster as claimed
                if (!empty($rosterId)) {
                    $pdo->prepare("UPDATE verification_roster SET is_claimed = 1, claimed_user_id = ?, claimed_at = NOW() WHERE id = ?")->execute([$userId, $rosterId]);
                }

                // Insert Teacher profile
                $stmt2 = $pdo->prepare("INSERT INTO teachers (user_id, specialisation, date_of_joining) VALUES (?, ?, NOW())");
                $stmt2->execute([$userId, $specialisation]);
                $msg = "Teacher '{$full_name}' created successfully.";
            } else {
                // Update User
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ?, gender = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $gender, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, gender = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $phone, $gender, $userId]);
                }

                // Check profile
                $chkProf = $pdo->prepare("SELECT user_id FROM teachers WHERE user_id = ?");
                $chkProf->execute([$userId]);
                if ($chkProf->fetch()) {
                    $stmt2 = $pdo->prepare("UPDATE teachers SET specialisation = ? WHERE user_id = ?");
                    $stmt2->execute([$specialisation, $userId]);
                } else {
                    $stmt2 = $pdo->prepare("INSERT INTO teachers (user_id, specialisation, date_of_joining) VALUES (?, ?, NOW())");
                    $stmt2->execute([$userId, $specialisation]);
                }
                $msg = "Teacher '{$full_name}' updated successfully.";
            }

            // Sync Many-to-Many classes
            $pdo->prepare("DELETE FROM class_teachers WHERE teacher_id = ?")->execute([$userId]);
            if (!empty($class_ids)) {
                $ins = $pdo->prepare("INSERT INTO class_teachers (class_id, teacher_id) VALUES (?, ?)");
                foreach ($class_ids as $cid) {
                    $ins->execute([$cid, $userId]);
                }
            }

            $_SESSION['msg_success'] = $msg;
            header("Location: teachers");
            exit;
        }

        // 2. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'teacher') {
                // Unlink roster if claimed
                $pdo->prepare("UPDATE verification_roster SET is_claimed = 0, claimed_user_id = NULL, claimed_at = NULL WHERE claimed_user_id = ?")->execute([$id]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Teacher profile deleted successfully.";
            }
            header("Location: teachers");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: teachers");
        exit;
    }
}

// --- FETCH DATA ---
// 1. Registered Teachers
$registeredTeachers = $pdo->query("
    SELECT u.id as teacher_id, u.username as teacher_username, u.full_name as teacher_name, 
           u.email as teacher_email, u.phone as teacher_phone, u.gender as teacher_gender, 
           u.status as teacher_status, t.specialisation, u.roster_id,
           'registered' as reg_type
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.role = 'teacher'
    ORDER BY u.full_name ASC
")->fetchAll();

// 2. Unregistered Roster Teachers (Not yet claimed or active in users)
$unregisteredTeachers = $pdo->query("
    SELECT NULL as teacher_id, NULL as teacher_username, vr.name as teacher_name,
           NULL as teacher_email, NULL as teacher_phone, 
           IF(vr.name LIKE '%TEACHER%' OR vr.name LIKE '%SUMAYYA%' OR vr.name LIKE '%FATHIMA%' OR vr.name LIKE '%ADHILA%' OR vr.name LIKE '%AYISHA%' OR vr.name LIKE '%RUSHDA%' OR vr.name LIKE '%MANSOORA%' OR vr.name LIKE '%AMINA%' OR vr.name LIKE '%FIDA%' OR vr.name LIKE '%HALEEMA%' OR vr.name LIKE '%HANAN%' OR vr.name LIKE '%HIBA%', 'female', 'male') as teacher_gender,
           'unregistered' as teacher_status, NULL as specialisation, vr.id as roster_id,
           'unregistered' as reg_type
    FROM verification_roster vr
    WHERE vr.type = 'teacher' 
      AND (vr.is_claimed = 0 OR vr.claimed_user_id IS NULL)
      AND vr.name NOT IN (SELECT full_name FROM users WHERE role = 'teacher')
    ORDER BY vr.name ASC
")->fetchAll();

// Combine
$allTeachers = array_merge($registeredTeachers, $unregisteredTeachers);

// Stats
$totalCount = count($allTeachers);
$registeredCount = count(array_filter($allTeachers, fn($t) => $t['reg_type'] === 'registered' && $t['teacher_status'] === 'active'));
$pendingCount = count(array_filter($allTeachers, fn($t) => $t['reg_type'] === 'registered' && $t['teacher_status'] === 'inactive'));
$unregisteredCount = count(array_filter($allTeachers, fn($t) => $t['reg_type'] === 'unregistered'));
$regPercent = $totalCount > 0 ? round(($registeredCount / $totalCount) * 100) : 0;

$classTeachersRaw = $pdo->query("
    SELECT ct.teacher_id, c.id as class_id, c.name as class_name 
    FROM class_teachers ct
    JOIN classes c ON ct.class_id = c.id
")->fetchAll();

$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();

// Group taught classes by teacher_id
$classesByTeacher = [];
foreach ($classTeachersRaw as $ct) {
    $classesByTeacher[$ct['teacher_id']][] = $ct;
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Top Header & Actions -->
<div class="flex flex-wrap gap-4 items-center justify-between">
    <div>
        <h3 class="text-xl font-bold text-white flex items-center gap-2.5">
            <i class="fa-solid fa-chalkboard-user text-emerald-400"></i> Teachers Registry
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">Comprehensive list of registered faculty and pending directory members</p>
    </div>
    <button onclick="openTeacherModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
        <i class="fa-solid fa-user-plus"></i> Add Teacher
    </button>
</div>

<!-- Status & Progress Dashboard Bar -->
<div class="glass-panel rounded-2xl p-5 border border-white/10 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-6">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Total Teachers</span>
                <span class="text-2xl font-black text-white"><?= $totalCount ?></span>
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
            <button onclick="filterTeachers('all')" id="btn-tab-all" class="px-3.5 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-300 transition">
                All (<?= $totalCount ?>)
            </button>
            <button onclick="filterTeachers('registered')" id="btn-tab-registered" class="px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-white transition">
                Registered (<?= $registeredCount ?>)
            </button>
            <button onclick="filterTeachers('unregistered')" id="btn-tab-unregistered" class="px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-white transition">
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

<!-- Teachers Table Grid -->
<div class="glass-panel rounded-2xl overflow-hidden border border-white/10">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-white/10 text-xs font-bold text-slate-400 uppercase tracking-wider" style="background:rgba(18,59,71,0.4);">
                    <th class="py-4 px-6">Name & Gender</th>
                    <th class="py-4 px-6">Username / Account</th>
                    <th class="py-4 px-6">Contact Info</th>
                    <th class="py-4 px-6">Specialisation</th>
                    <th class="py-4 px-6">Assigned Classes</th>
                    <th class="py-4 px-6">Status</th>
                    <th class="py-4 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y text-xs" style="divide-color:rgba(109,204,141,0.06);">
                <?php if (empty($allTeachers)): ?>
                    <tr>
                        <td colspan="7" class="py-8 px-6 text-center text-slate-500 font-medium">No teachers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allTeachers as $teacher): ?>
                        <?php 
                        $isReg = ($teacher['reg_type'] === 'registered');
                        $isInactive = ($isReg && $teacher['teacher_status'] === 'inactive');
                        $taughtClasses = $isReg ? ($classesByTeacher[$teacher['teacher_id']] ?? []) : [];
                        ?>
                        <tr class="teacher-row hover:bg-white/[0.02] transition-colors" data-status="<?= $isReg ? ($isInactive ? 'pending' : 'registered') : 'unregistered' ?>">
                            
                            <!-- Name & Gender -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center font-bold text-xs <?= $teacher['teacher_gender'] === 'female' ? 'bg-pink-500/20 text-pink-300 border border-pink-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' ?>">
                                        <?= strtoupper(substr($teacher['teacher_name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-white text-sm"><?= htmlspecialchars($teacher['teacher_name']) ?></span>
                                            <?php if ($teacher['teacher_gender'] === 'female'): ?>
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-pink-500/15 text-pink-300 border border-pink-500/30">Female</span>
                                            <?php else: ?>
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-cyan-500/15 text-cyan-300 border border-cyan-500/30">Male</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$isReg): ?>
                                            <span class="text-[10px] text-slate-400 font-mono">Directory Entry #<?= $teacher['roster_id'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Username / Account -->
                            <td class="py-4 px-6">
                                <?php if ($isReg): ?>
                                    <span class="font-mono text-slate-200 text-xs font-semibold bg-white/5 px-2 py-1 rounded border border-white/10">@<?= htmlspecialchars($teacher['teacher_username']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-500 italic text-[11px]">Unclaimed Profile</span>
                                <?php endif; ?>
                            </td>

                            <!-- Contact Info -->
                            <td class="py-4 px-6 space-y-0.5">
                                <?php if ($isReg): ?>
                                    <div class="text-slate-300"><i class="fa-regular fa-envelope mr-1.5 opacity-60"></i><?= htmlspecialchars($teacher['teacher_email'] ?? 'No Gmail') ?></div>
                                    <div class="text-slate-400 font-mono"><i class="fa-solid fa-mobile-screen mr-1.5 opacity-60"></i><?= htmlspecialchars($teacher['teacher_phone'] ?? 'No Mobile') ?></div>
                                <?php else: ?>
                                    <span class="text-slate-500 text-[11px]">— Pending self-verification —</span>
                                <?php endif; ?>
                            </td>

                            <!-- Specialisation -->
                            <td class="py-4 px-6">
                                <span class="text-xs px-2.5 py-1 rounded bg-slate-900/80 border border-white/10 text-slate-300">
                                    <?= htmlspecialchars($teacher['specialisation'] ?? 'General Studies') ?>
                                </span>
                            </td>

                            <!-- Assigned Classes -->
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1">
                                    <?php if (empty($taughtClasses)): ?>
                                        <span class="text-[11px] text-slate-500 italic">None assigned</span>
                                    <?php else: ?>
                                        <?php foreach ($taughtClasses as $tc): ?>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/25 rounded">
                                                <?= htmlspecialchars($tc['class_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
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
                                        <i class="fa-solid fa-check text-[9px]"></i> Registered
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php if ($isReg): ?>
                                        <?php 
                                        $teacherData = [
                                            'teacher_id' => $teacher['teacher_id'],
                                            'username' => $teacher['teacher_username'],
                                            'full_name' => $teacher['teacher_name'],
                                            'email' => $teacher['teacher_email'],
                                            'phone' => $teacher['teacher_phone'],
                                            'gender' => $teacher['teacher_gender'],
                                            'specialisation' => $teacher['specialisation'],
                                            'class_ids' => array_column($taughtClasses, 'class_id')
                                        ];
                                        ?>
                                        <button onclick="openTeacherModal(<?= htmlspecialchars(json_encode($teacherData)) ?>)" class="p-2 text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg border border-white/8 transition" title="Edit Teacher">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button onclick="confirmDelete('teacher', <?= $teacher['teacher_id'] ?>)" class="p-2 text-xs font-semibold bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded-lg border border-red-500/10 transition" title="Delete Teacher">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <!-- Quick Register / Claim for Unregistered Roster -->
                                        <?php 
                                        $quickData = [
                                            'teacher_id' => '',
                                            'roster_id' => $teacher['roster_id'],
                                            'username' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $teacher['teacher_name'])),
                                            'full_name' => $teacher['teacher_name'],
                                            'gender' => $teacher['teacher_gender'],
                                            'email' => '',
                                            'phone' => '',
                                            'specialisation' => '',
                                            'class_ids' => []
                                        ];
                                        ?>
                                        <button onclick="openTeacherModal(<?= htmlspecialchars(json_encode($quickData)) ?>)" class="px-2.5 py-1.5 text-[11px] font-bold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-lg border border-emerald-500/25 transition flex items-center gap-1.5" title="Direct Register">
                                            <i class="fa-solid fa-user-check"></i> Register
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
<!-- Teacher Modal -->
<div id="modal-teacher" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/70 backdrop-blur-sm">
    <div class="w-full max-w-lg glass-panel rounded-3xl p-8 relative space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center">
            <h3 id="teacher-modal-title" class="text-xl font-bold text-white">Add New Teacher</h3>
            <button onclick="closeModal('teacher')" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form action="teachers" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_teacher">
            <input type="hidden" name="teacher_id" id="teacher_id">
            <input type="hidden" name="roster_id" id="teacher_roster_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="teacher_username" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username <span class="text-red-400">*</span></label>
                    <input type="text" name="username" id="teacher_username" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. teacher_ali" required>
                </div>
                <div class="space-y-1.5">
                    <label for="teacher_password" id="lbl-teacher-password" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Password <span class="text-red-400">*</span></label>
                    <input type="password" name="password" id="teacher_password" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="••••••••" required>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 space-y-1.5">
                    <label for="teacher_fullname" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="full_name" id="teacher_fullname" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Ustad Ali" required>
                </div>
                <div class="space-y-1.5">
                    <label for="teacher_gender" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Gender</label>
                    <select name="gender" id="teacher_gender" class="glass-input w-full px-3 py-3 rounded-xl text-sm">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="teacher_email" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Gmail Address</label>
                    <input type="email" name="email" id="teacher_email" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="ali@gmail.com">
                </div>
                <div class="space-y-1.5">
                    <label for="teacher_phone" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Mobile Number</label>
                    <input type="tel" name="phone" id="teacher_phone" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="+91 999 555 1234">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="teacher_spec" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Specialisation / Subject</label>
                <input type="text" name="specialisation" id="teacher_spec" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Hifz / Quran Recitation">
            </div>

            <div class="space-y-2">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Assign Classes (Teaches in)</label>
                <div class="grid grid-cols-2 gap-3 max-h-40 overflow-y-auto bg-slate-950/40 p-4 rounded-xl border border-white/5">
                    <?php foreach ($classes as $cls): ?>
                        <label class="flex items-center gap-2.5 text-xs text-slate-300 hover:text-white cursor-pointer select-none">
                            <input type="checkbox" name="class_ids[]" value="<?= $cls['id'] ?>" class="teacher-class-checkbox w-4 h-4 rounded bg-white/5 border border-white/10 text-emerald-500 focus:ring-0 focus:ring-offset-0">
                            <span><?= htmlspecialchars($cls['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('teacher')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs hover:shadow-lg hover:shadow-emerald-400/20 transition">Save Teacher</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab Filter
    function filterTeachers(filter) {
        document.querySelectorAll('.teacher-row').forEach(row => {
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

    // Teacher Modal Manager
    function openTeacherModal(data = null) {
        const modal = document.getElementById('modal-teacher');
        const title = document.getElementById('teacher-modal-title');
        const form = modal.querySelector('form');
        const passwordLabel = document.getElementById('lbl-teacher-password');
        const passwordInput = document.getElementById('teacher_password');
        const usernameInput = document.getElementById('teacher_username');
        const genderSelect = document.getElementById('teacher_gender');
        
        form.reset();
        document.getElementById('teacher_id').value = '';
        document.getElementById('teacher_roster_id').value = '';
        
        // Uncheck all class checkboxes
        document.querySelectorAll('.teacher-class-checkbox').forEach(cb => cb.checked = false);

        if (data) {
            if (data.teacher_id) {
                title.innerText = 'Edit Teacher';
                document.getElementById('teacher_id').value = data.teacher_id;
                passwordLabel.innerText = 'New Password (leave blank to keep)';
                passwordInput.removeAttribute('required');
            } else {
                title.innerText = 'Direct Register Teacher (Roster Link)';
                if (data.roster_id) {
                    document.getElementById('teacher_roster_id').value = data.roster_id;
                }
                passwordLabel.innerText = 'Set Initial Password *';
                passwordInput.setAttribute('required', 'required');
            }

            usernameInput.value = data.username || '';
            document.getElementById('teacher_fullname').value = data.full_name || '';
            document.getElementById('teacher_email').value = data.email || '';
            document.getElementById('teacher_phone').value = data.phone || '';
            document.getElementById('teacher_spec').value = data.specialisation || '';
            if (genderSelect && data.gender) {
                genderSelect.value = data.gender;
            }

            // Check assigned classes
            if (data.class_ids && Array.isArray(data.class_ids)) {
                data.class_ids.forEach(cid => {
                    const cb = document.querySelector(`.teacher-class-checkbox[value="${cid}"]`);
                    if (cb) cb.checked = true;
                });
            }
        } else {
            title.innerText = 'Add New Teacher';
            passwordLabel.innerText = 'Password *';
            passwordInput.setAttribute('required', 'required');
        }
        modal.classList.remove('hidden');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
