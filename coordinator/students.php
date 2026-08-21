<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE STUDENT
        if ($action === 'save_student') {
            $userId = $_POST['student_id'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? null);
            $phone = trim($_POST['phone'] ?? null);
            $admission_no = trim($_POST['admission_no'] ?? '');
            $parent_name = trim($_POST['parent_name'] ?? '');
            $dob = trim($_POST['dob'] ?? null);
            $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;

            if (empty($userId)) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    throw new Exception("Username '{$username}' already exists.");
                }

                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, 'student', 'active')");
                $stmt->execute([$username, $hashedPass, $full_name, $email, $phone]);
                $userId = $pdo->lastInsertId();

                $stmt2 = $pdo->prepare("INSERT INTO students (user_id, admission_no, parent_name, dob, class_id) VALUES (?, ?, ?, ?, ?)");
                $stmt2->execute([$userId, $admission_no, $parent_name, $dob ?: null, $class_id]);
                $_SESSION['msg_success'] = "Student '{$full_name}' created successfully.";
            } else {
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $phone, $userId]);
                }

                // Check profile
                $chkProf = $pdo->prepare("SELECT user_id FROM students WHERE user_id = ?");
                $chkProf->execute([$userId]);
                if ($chkProf->fetch()) {
                    $stmt2 = $pdo->prepare("UPDATE students SET admission_no = ?, parent_name = ?, dob = ?, class_id = ? WHERE user_id = ?");
                    $stmt2->execute([$admission_no, $parent_name, $dob ?: null, $class_id, $userId]);
                } else {
                    $stmt2 = $pdo->prepare("INSERT INTO students (user_id, admission_no, parent_name, dob, class_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->execute([$userId, $admission_no, $parent_name, $dob ?: null, $class_id]);
                }
                $_SESSION['msg_success'] = "Student '{$full_name}' updated successfully.";
            }

            header("Location: students");
            exit;
        }

        // 2. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'student') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Student profile deleted successfully.";
            }
            header("Location: students");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: students");
        exit;
    }
}

// --- FETCH DATA ---
$studentsRaw = $pdo->query("
    SELECT s.user_id, u.username as student_username, u.full_name as student_name, 
           u.email as student_email, u.phone as student_phone,
           s.admission_no, s.parent_name, s.dob, s.class_id,
           c.name as class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY u.full_name ASC
")->fetchAll();

$classes = $pdo->query("SELECT id, name FROM classes ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Controls bar -->
<div class="flex flex-wrap gap-4 items-center justify-between">
    <h3 class="text-lg font-bold text-white">Students Directory</h3>
    <button onclick="openStudentModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
        <i class="fa-solid fa-user-plus"></i> Add Student
    </button>
</div>

<!-- Students Table -->
<div class="glass-panel rounded-2xl overflow-hidden border border-white/10">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-white/15 bg-white/[0.02] text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="py-4 px-6">Name</th>
                    <th class="py-4 px-6">Admission No</th>
                    <th class="py-4 px-6">Class</th>
                    <th class="py-4 px-6">Parent / Guardian</th>
                    <th class="py-4 px-6">Date of Birth</th>
                    <th class="py-4 px-6">Contact</th>
                    <th class="py-4 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-sm">
                <?php if (empty($studentsRaw)): ?>
                    <tr>
                        <td colspan="7" class="py-8 px-6 text-center text-slate-500 font-medium">No students registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($studentsRaw as $student): ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-4 px-6">
                                <div class="font-bold text-white"><?= htmlspecialchars($student['student_name']) ?></div>
                                <div class="text-[10px] text-slate-400">@<?= htmlspecialchars($student['student_username']) ?></div>
                            </td>
                            <td class="py-4 px-6 font-mono text-xs text-slate-300"><?= htmlspecialchars($student['admission_no'] ?? 'N/A') ?></td>
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded bg-white/5 border border-white/10 text-xs">
                                    <?= htmlspecialchars($student['class_name'] ?? 'Unassigned') ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-slate-300"><?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?></td>
                            <td class="py-4 px-6 text-slate-300 text-xs"><?= htmlspecialchars($student['dob'] ?? 'N/A') ?></td>
                            <td class="py-4 px-6 text-xs text-slate-400 space-y-0.5">
                                <div><i class="fa-regular fa-envelope mr-1.5 opacity-60"></i><?= htmlspecialchars($student['student_email'] ?? 'N/A') ?></div>
                                <div><i class="fa-solid fa-mobile-screen mr-1.5 opacity-60"></i><?= htmlspecialchars($student['student_phone'] ?? 'N/A') ?></div>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="openStudentModal(<?= htmlspecialchars(json_encode([
                                        'student_id' => $student['user_id'],
                                        'username' => $student['student_username'],
                                        'full_name' => $student['student_name'],
                                        'email' => $student['student_email'],
                                        'phone' => $student['student_phone'],
                                        'admission_no' => $student['admission_no'],
                                        'parent_name' => $student['parent_name'],
                                        'dob' => $student['dob'],
                                        'class_id' => $student['class_id']
                                    ])) ?>)" class="p-1.5 text-xs bg-white/5 hover:bg-white/10 text-slate-300 rounded border border-white/8 transition" title="Edit Student">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button onclick="confirmDelete('student', <?= $student['user_id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Student">
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
<!-- Student Modal -->
<div id="modal-student" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
    <div class="w-full max-w-xl glass-panel rounded-3xl p-8 relative space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center">
            <h3 id="student-modal-title" class="text-xl font-bold text-white">Add New Student</h3>
            <button onclick="closeModal('student')" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form action="students" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_student">
            <input type="hidden" name="student_id" id="student_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username</label>
                    <input type="text" name="username" id="student_username" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. student_ali" required>
                </div>
                <div class="space-y-1.5">
                    <label id="lbl-student-password" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Password</label>
                    <input type="password" name="password" id="student_password" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="••••••••" required>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name</label>
                    <input type="text" name="full_name" id="student_fullname" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Ahmad Ali" required>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Admission No</label>
                    <input type="text" name="admission_no" id="student_adm" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. ADM-2026-001" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Parent / Guardian</label>
                    <input type="text" name="parent_name" id="student_parent" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="Guardian name" required>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Date of Birth</label>
                    <input type="date" name="dob" id="student_dob" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Gmail / Email</label>
                    <input type="email" name="email" id="student_email" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="student@gmail.com">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Mobile</label>
                    <input type="text" name="phone" id="student_phone" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="Phone">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Assign to Class</label>
                <select name="class_id" id="student_class_id" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    <option value="">Unassigned</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex justify-end gap-3 pt-2 border-t border-white/10">
                <button type="button" onclick="closeModal('student')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs hover:shadow-lg hover:shadow-emerald-400/20 transition">Save Student</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStudentModal(data = null) {
        const modal = document.getElementById('modal-student');
        const title = document.getElementById('student-modal-title');
        const form = modal.querySelector('form');
        const passLabel = document.getElementById('lbl-student-password');
        const passInput = document.getElementById('student_password');

        form.reset();
        document.getElementById('student_id').value = '';

        if (data) {
            title.innerText = 'Edit Student';
            document.getElementById('student_id').value = data.student_id;
            document.getElementById('student_username').value = data.username;
            document.getElementById('student_fullname').value = data.full_name;
            document.getElementById('student_email').value = data.email || '';
            document.getElementById('student_phone').value = data.phone || '';
            document.getElementById('student_adm').value = data.admission_no || '';
            document.getElementById('student_parent').value = data.parent_name || '';
            document.getElementById('student_dob').value = data.dob || '';
            document.getElementById('student_class_id').value = data.class_id || '';
            passLabel.innerText = 'New Password (leave blank to keep)';
            passInput.removeAttribute('required');
        } else {
            title.innerText = 'Add New Student';
            passLabel.innerText = 'Password';
            passInput.setAttribute('required', 'required');
        }
        modal.classList.remove('hidden');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
