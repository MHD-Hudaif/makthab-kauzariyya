<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE TEACHER
        if ($action === 'save_teacher') {
            $userId = $_POST['teacher_id'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? null);
            $phone = trim($_POST['phone'] ?? null);
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
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, 'teacher', 'active')");
                $stmt->execute([$username, $hashedPass, $full_name, $email, $phone]);
                $userId = $pdo->lastInsertId();

                // Insert Teacher profile
                $stmt2 = $pdo->prepare("INSERT INTO teachers (user_id, specialisation, date_of_joining) VALUES (?, ?, NOW())");
                $stmt2->execute([$userId, $specialisation]);
                $msg = "Teacher '{$full_name}' created successfully.";
            } else {
                // Update User
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $phone, $userId]);
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
            header("Location: teachers.php");
            exit;
        }

        // 2. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'teacher') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Teacher profile deleted successfully.";
            }
            header("Location: teachers.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: teachers.php");
        exit;
    }
}

// --- FETCH DATA ---
$teachersRaw = $pdo->query("
    SELECT u.id as teacher_id, u.username as teacher_username, u.full_name as teacher_name, u.email as teacher_email, u.phone as teacher_phone, t.specialisation
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.role = 'teacher'
    ORDER BY u.full_name ASC
")->fetchAll();

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

<!-- Controls bar -->
<div class="flex flex-wrap gap-4 items-center justify-between">
    <h3 class="text-lg font-bold text-white">Teachers Registry</h3>
    <button onclick="openTeacherModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
        <i class="fa-solid fa-user-plus"></i> Add Teacher
    </button>
</div>

<!-- Teachers Table Grid -->
<div class="glass-panel rounded-2xl overflow-hidden border border-white/10">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-white/10 bg-white/[0.02] text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="py-4 px-6">Name</th>
                    <th class="py-4 px-6">Username</th>
                    <th class="py-4 px-6">Contact Info</th>
                    <th class="py-4 px-6">Specialisation</th>
                    <th class="py-4 px-6">Assigned Classes</th>
                    <th class="py-4 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-sm">
                <?php if (empty($teachersRaw)): ?>
                    <tr>
                        <td colspan="6" class="py-8 px-6 text-center text-slate-500 font-medium">No teachers registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachersRaw as $teacher): ?>
                        <tr class="hover:bg-white/[0.01] transition-colors">
                            <td class="py-4 px-6 font-bold text-white"><?= htmlspecialchars($teacher['teacher_name']) ?></td>
                            <td class="py-4 px-6 font-mono text-xs text-slate-300">@<?= htmlspecialchars($teacher['teacher_username']) ?></td>
                            <td class="py-4 px-6 space-y-0.5 text-xs">
                                <div class="text-slate-300"><i class="fa-regular fa-envelope mr-1.5 opacity-60"></i><?= htmlspecialchars($teacher['teacher_email'] ?? 'No Gmail') ?></div>
                                <div class="text-slate-400"><i class="fa-solid fa-mobile-screen mr-1.5 opacity-60"></i><?= htmlspecialchars($teacher['teacher_phone'] ?? 'No Mobile') ?></div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-xs px-2.5 py-1 rounded bg-slate-900 border border-white/10 text-slate-300">
                                    <?= htmlspecialchars($teacher['specialisation'] ?? 'General Studies') ?>
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $taughtClasses = $classesByTeacher[$teacher['teacher_id']] ?? [];
                                    if (empty($taughtClasses)): 
                                    ?>
                                        <span class="text-xs text-slate-500 font-medium">None assigned</span>
                                    <?php else: ?>
                                        <?php foreach ($taughtClasses as $tc): ?>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/25 rounded">
                                                <?= htmlspecialchars($tc['class_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php 
                                    $teacherData = $teacher;
                                    $teacherData['class_ids'] = array_column($taughtClasses, 'class_id');
                                    ?>
                                    <button onclick="openTeacherModal(<?= htmlspecialchars(json_encode($teacherData)) ?>)" class="p-2 text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg border border-white/8 transition" title="Edit Teacher">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button onclick="confirmDelete('teacher', <?= $teacher['teacher_id'] ?>)" class="p-2 text-xs font-semibold bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded-lg border border-red-500/10 transition" title="Delete Teacher">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
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
<div id="modal-teacher" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
    <div class="w-full max-w-lg glass-panel rounded-3xl p-8 relative space-y-6">
        <div class="flex justify-between items-center">
            <h3 id="teacher-modal-title" class="text-xl font-bold text-white">Add New Teacher</h3>
            <button onclick="closeModal('teacher')" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_teacher">
            <input type="hidden" name="teacher_id" id="teacher_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="teacher_username" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username</label>
                    <input type="text" name="username" id="teacher_username" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. teacher_ali" required>
                </div>
                <div class="space-y-1.5">
                    <label for="teacher_password" id="lbl-teacher-password" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Password</label>
                    <input type="password" name="password" id="teacher_password" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="••••••••">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="teacher_fullname" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name</label>
                <input type="text" name="full_name" id="teacher_fullname" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Ustad Ali" required>
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
    function openTeacherModal(data = null) {
        const modal = document.getElementById('modal-teacher');
        const title = document.getElementById('teacher-modal-title');
        const form = modal.querySelector('form');
        const passwordLabel = document.getElementById('lbl-teacher-password');
        const passwordInput = document.getElementById('teacher_password');
        const usernameInput = document.getElementById('teacher_username');
        
        form.reset();
        document.getElementById('teacher_id').value = '';
        
        document.querySelectorAll('.teacher-class-checkbox').forEach(cb => cb.checked = false);

        if (data) {
            title.innerText = 'Edit Teacher';
            document.getElementById('teacher_id').value = data.teacher_id;
            usernameInput.value = data.username;
            document.getElementById('teacher_fullname').value = data.full_name;
            document.getElementById('teacher_email').value = data.email || '';
            document.getElementById('teacher_phone').value = data.phone || '';
            document.getElementById('teacher_spec').value = data.specialisation || '';

            if (data.class_ids && Array.isArray(data.class_ids)) {
                data.class_ids.forEach(cid => {
                    const cb = document.querySelector(`.teacher-class-checkbox[value="${cid}"]`);
                    if (cb) cb.checked = true;
                });
            }

            passwordLabel.innerText = 'New Password (leave blank to keep)';
            passwordInput.removeAttribute('required');
        } else {
            title.innerText = 'Add New Teacher';
            passwordLabel.innerText = 'Password';
            passwordInput.setAttribute('required', 'required');
        }
        modal.classList.remove('hidden');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
