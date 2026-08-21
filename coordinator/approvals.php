<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // 1. APPROVE ADMISSION & ASSIGN CLASS
        if ($action === 'approve_student') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $classId = (int)($_POST['class_id'] ?? 0);
            
            if ($classId <= 0) {
                throw new Exception("Please select a classroom to assign the student.");
            }
            
            $pdo->beginTransaction();
            
            // Check student existence
            $chk = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
            $chk->execute([$userId]);
            if (!$chk->fetch()) {
                throw new Exception("Student details not found.");
            }
            
            // Update users status to active
            $updUser = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $updUser->execute([$userId]);
            
            // Assign class_id in students table
            $updStudent = $pdo->prepare("UPDATE students SET class_id = ? WHERE user_id = ?");
            $updStudent->execute([$classId, $userId]);
            
            $pdo->commit();
            
            $_SESSION['msg_success'] = "Student admission approved and assigned to classroom successfully.";
            header("Location: approvals");
            exit;
        }
        
        // 2. REJECT / DELETE REGISTRATION
        if ($action === 'reject_student') {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            // Cascade constraint will delete student record automatically
            $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $del->execute([$userId]);
            
            $_SESSION['msg_success'] = "Admission registration rejected and deleted.";
            header("Location: approvals");
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: approvals");
        exit;
    }
}

// --- FETCH PENDING ADMISSIONS ---
$stmtPending = $pdo->prepare("
    SELECT u.*, s.parent_name, s.dob, s.admission_no
    FROM users u
    JOIN students s ON u.id = s.user_id
    WHERE u.role = 'student' AND u.status = 'inactive'
    ORDER BY u.created_at ASC
");
$stmtPending->execute();
$pendingStudents = $stmtPending->fetchAll();

// --- FETCH ACTIVE CLASSROOMS FOR DROPDOWN ---
$stmtClasses = $pdo->prepare("
    SELECT c.id, c.name, co.name as course_name, co.code as course_code
    FROM classes c
    JOIN courses co ON c.course_id = co.id
    ORDER BY co.code ASC, c.name ASC
");
$stmtClasses->execute();
$classrooms = $stmtClasses->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Tab Heading -->
<div class="flex justify-between items-center mb-6">
    <div class="space-y-1">
        <h1 class="text-2xl font-extrabold text-white">Pending Admission Approvals</h1>
        <p class="text-xs text-slate-400">Review newly registered students, assign them to class streams, and activate accounts.</p>
    </div>
</div>

<!-- Pending Applications List -->
<div class="glass-panel rounded-3xl overflow-hidden border border-white/10">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b text-[10px] font-bold uppercase tracking-widest text-slate-400" style="background:rgba(18,59,71,0.3); border-color:rgba(109,204,141,0.08);">
                    <th class="py-4.5 px-6">Student Info</th>
                    <th class="py-4.5 px-6">Parent & Place</th>
                    <th class="py-4.5 px-6">Admission No</th>
                    <th class="py-4.5 px-6">Registration Date</th>
                    <th class="py-4.5 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y text-xs" style="divide-color:rgba(109,204,141,0.05);">
                <?php if (empty($pendingStudents)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400 font-semibold">
                            <i class="fa-solid fa-user-check text-3xl mb-3 text-slate-500 block"></i>
                            No pending registrations require review.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingStudents as $student): ?>
                        <tr class="hover:bg-white/[0.02] transition duration-200">
                            <!-- Student identity -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full border border-white/10 overflow-hidden flex-shrink-0 bg-slate-950 flex items-center justify-center p-0.5">
                                        <?php if (!empty($student['profile_photo'])): ?>
                                            <img src="<?= htmlspecialchars($student['profile_photo']) ?>" alt="Avatar" class="w-full h-full object-cover rounded-full">
                                        <?php else: ?>
                                            <div class="w-full h-full brand-gradient flex items-center justify-center text-xs font-bold text-slate-950 rounded-full">
                                                <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-bold text-white truncate max-w-[180px]"><?= htmlspecialchars($student['full_name']) ?></span>
                                        <span class="text-[10px] text-slate-400 font-mono">@<?= htmlspecialchars($student['username']) ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact info -->
                            <td class="py-4 px-6">
                                <div class="flex flex-col space-y-1">
                                    <span class="text-slate-200"><?= htmlspecialchars($student['phone']) ?></span>
                                    <span class="text-[10px] text-slate-400">Parent: <?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?> | <?= htmlspecialchars($student['place'] ?? 'No Place') ?></span>
                                </div>
                            </td>

                            <!-- Admission code -->
                            <td class="py-4 px-6 font-mono text-slate-300 font-bold">
                                <?= htmlspecialchars($student['admission_no']) ?>
                            </td>

                            <!-- Submitted Date -->
                            <td class="py-4 px-6 text-slate-400">
                                <?= date('d M Y, h:i A', strtotime($student['created_at'])) ?>
                            </td>

                            <!-- Action buttons -->
                            <td class="py-4 px-6 text-right flex justify-end gap-2">
                                <!-- Approve form -->
                                <button onclick="openApprovalModal(<?= $student['id'] ?>, '<?= htmlspecialchars($student['full_name']) ?>')" class="px-3.5 py-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 border border-emerald-500/20 text-emerald-400 hover:text-slate-950 font-bold transition flex items-center gap-1.5" title="Approve Student">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                                
                                <!-- Reject form -->
                                <form action="" method="POST" onsubmit="return confirm('Are you sure you want to reject and delete this registration?');" class="inline">
                                    <input type="hidden" name="action" value="reject_student">
                                    <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                    <button type="submit" class="w-9 h-9 flex items-center justify-center rounded-xl bg-rose-500/10 hover:bg-rose-500 border border-rose-500/20 text-rose-400 hover:text-white transition" title="Reject / Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Classroom Allocation Modal overlay -->
<div id="approval-modal" class="fixed inset-0 z-50 backdrop-blur-sm bg-black/60 hidden items-center justify-center p-4">
    <div class="w-full max-w-md glass-panel rounded-3xl p-6 border border-white/10 space-y-6 text-left">
        <!-- Modal Heading -->
        <div class="flex justify-between items-center pb-3 border-b border-white/10">
            <h3 class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-emerald-400"></i> Classroom Allocation
            </h3>
            <button onclick="closeApprovalModal()" class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p class="text-xs text-slate-350 leading-relaxed">
            Select a classroom section for <span id="modal-student-name" class="font-bold text-white"></span>. Upon approval, their status is set to active and they can immediately access the Student Portal.
        </p>

        <!-- Form submission -->
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="approve_student">
            <input type="hidden" id="modal-user-id" name="user_id" value="">
            
            <div class="space-y-1.5">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Select Classroom <span class="text-red-400">*</span></label>
                <select name="class_id" required class="glass-input w-full px-4 py-3 rounded-xl text-sm" style="background:#123b47;">
                    <option value="">-- Choose Classroom Section --</option>
                    <?php foreach ($classrooms as $room): ?>
                        <option value="<?= $room['id'] ?>">
                            <?= htmlspecialchars($room['name']) ?> (<?= htmlspecialchars($room['course_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeApprovalModal()" class="w-1/2 border border-white/10 hover:bg-white/5 text-slate-300 font-semibold py-3 rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit" class="w-1/2 bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold py-3 rounded-xl text-xs hover:shadow-lg transition">
                    Approve & Active
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openApprovalModal(userId, studentName) {
        document.getElementById('modal-user-id').value = userId;
        document.getElementById('modal-student-name').textContent = studentName;
        
        const modal = document.getElementById('approval-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    
    function closeApprovalModal() {
        const modal = document.getElementById('approval-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
