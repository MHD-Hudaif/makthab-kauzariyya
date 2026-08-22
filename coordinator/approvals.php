<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // 1. APPROVE STUDENT & ASSIGN CLASS
        if ($action === 'approve_student') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $classId = (int)($_POST['class_id'] ?? 0);
            
            if ($classId <= 0) {
                throw new Exception("Please select a classroom to assign the student.");
            }
            
            $pdo->beginTransaction();
            
            // Check student existence
            $chk = $pdo->prepare("SELECT u.id, u.roster_id FROM users u JOIN students s ON u.id = s.user_id WHERE u.id = ?");
            $chk->execute([$userId]);
            $userRow = $chk->fetch();
            if (!$userRow) {
                throw new Exception("Student details not found.");
            }
            
            // Update user status to active
            $updUser = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $updUser->execute([$userId]);
            
            // Assign class_id in students table
            $updStudent = $pdo->prepare("UPDATE students SET class_id = ? WHERE user_id = ?");
            $updStudent->execute([$classId, $userId]);

            // If linked to verification roster, mark roster as verified & claimed
            if (!empty($userRow['roster_id'])) {
                $updRoster = $pdo->prepare("UPDATE verification_roster SET is_claimed = 1, claimed_user_id = ?, claimed_at = NOW() WHERE id = ?");
                $updRoster->execute([$userId, $userRow['roster_id']]);
            }
            
            $pdo->commit();
            
            $_SESSION['msg_success'] = "Student admission approved, activated, and assigned to classroom successfully.";
            header("Location: approvals");
            exit;
        }

        // 2. APPROVE TEACHER VERIFICATION
        if ($action === 'approve_teacher') {
            $userId = (int)($_POST['user_id'] ?? 0);

            $pdo->beginTransaction();

            $chk = $pdo->prepare("SELECT id, roster_id FROM users WHERE id = ? AND role = 'teacher'");
            $chk->execute([$userId]);
            $teacherRow = $chk->fetch();
            if (!$teacherRow) {
                throw new Exception("Teacher record not found.");
            }

            // Update user status to active
            $updUser = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $updUser->execute([$userId]);

            // If linked to verification roster, mark roster as verified & claimed
            if (!empty($teacherRow['roster_id'])) {
                $updRoster = $pdo->prepare("UPDATE verification_roster SET is_claimed = 1, claimed_user_id = ?, claimed_at = NOW() WHERE id = ?");
                $updRoster->execute([$userId, $teacherRow['roster_id']]);
            }

            $pdo->commit();

            $_SESSION['msg_success'] = "Teacher verification approved and account activated.";
            header("Location: approvals");
            exit;
        }
        
        // 3. REJECT / DELETE REGISTRATION
        if ($action === 'reject_application') {
            $userId = (int)($_POST['user_id'] ?? 0);

            $pdo->beginTransaction();

            // Fetch user to reset roster if applicable
            $stmtFetch = $pdo->prepare("SELECT id, roster_id FROM users WHERE id = ?");
            $stmtFetch->execute([$userId]);
            $userRow = $stmtFetch->fetch();

            if ($userRow && !empty($userRow['roster_id'])) {
                $resetRoster = $pdo->prepare("UPDATE verification_roster SET is_claimed = 0, claimed_user_id = NULL, claimed_at = NULL WHERE id = ?");
                $resetRoster->execute([$userRow['roster_id']]);
            }
            
            // Delete user record (foreign keys cascade to students/teachers)
            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$userId]);

            $pdo->commit();
            
            $_SESSION['msg_success'] = "Registration application has been rejected and removed.";
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

// --- FETCH PENDING STUDENTS ---
$stmtStudents = $pdo->prepare("
    SELECT u.*, s.parent_name, s.parent_phone, s.dob, s.admission_no,
           vr.name AS roster_name, vr.assigned_teacher_name AS roster_teacher
    FROM users u
    JOIN students s ON u.id = s.user_id
    LEFT JOIN verification_roster vr ON u.roster_id = vr.id
    WHERE u.role = 'student' AND u.status = 'inactive'
    ORDER BY u.created_at ASC
");
$stmtStudents->execute();
$pendingStudents = $stmtStudents->fetchAll();

// --- FETCH PENDING TEACHERS ---
$stmtTeachers = $pdo->prepare("
    SELECT u.*, vr.name AS roster_name
    FROM users u
    LEFT JOIN verification_roster vr ON u.roster_id = vr.id
    WHERE u.role = 'teacher' AND u.status = 'inactive'
    ORDER BY u.created_at ASC
");
$stmtTeachers->execute();
$pendingTeachers = $stmtTeachers->fetchAll();

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
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div class="space-y-1">
        <h1 class="text-2xl font-extrabold text-white">Pending Approvals & Verifications</h1>
        <p class="text-xs text-slate-400">Review newly registered students and existing roster claims for activation.</p>
    </div>
    
    <div class="flex gap-2">
        <span class="text-xs px-3.5 py-1.5 rounded-full border bg-emerald-500/10 text-emerald-300 border-emerald-500/20 font-semibold">
            <?= count($pendingStudents) ?> Student(s) Pending
        </span>
        <span class="text-xs px-3.5 py-1.5 rounded-full border bg-cyan-500/10 text-cyan-300 border-cyan-500/20 font-semibold">
            <?= count($pendingTeachers) ?> Teacher(s) Pending
        </span>
    </div>
</div>

<!-- Section 1: Pending Student Admissions & Claims -->
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-graduation-cap text-emerald-400"></i> Student Applications (<?= count($pendingStudents) ?>)
        </h2>
    </div>

    <div class="glass-panel rounded-3xl overflow-hidden border border-white/10">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b text-[10px] font-bold uppercase tracking-widest text-slate-400" style="background:rgba(18,59,71,0.3); border-color:rgba(109,204,141,0.08);">
                        <th class="py-4 px-6">Student Info</th>
                        <th class="py-4 px-6">Verification Match</th>
                        <th class="py-4 px-6">Guardian & Phone</th>
                        <th class="py-4 px-6">Date</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-xs" style="divide-color:rgba(109,204,141,0.05);">
                    <?php if (empty($pendingStudents)): ?>
                        <tr>
                            <td colspan="5" class="py-10 text-center text-slate-400 font-semibold">
                                <i class="fa-solid fa-user-check text-2xl mb-2 text-slate-500 block"></i>
                                No pending student applications.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingStudents as $student): ?>
                            <tr class="hover:bg-white/[0.02] transition duration-200">
                                <!-- Student Info -->
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
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-white truncate max-w-[170px]"><?= htmlspecialchars($student['full_name']) ?></span>
                                                <?php if (($student['gender'] ?? '') === 'female'): ?>
                                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-pink-500/15 text-pink-300 border border-pink-500/30">Female</span>
                                                <?php else: ?>
                                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-blue-500/15 text-blue-300 border border-blue-500/30">Male</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono">@<?= htmlspecialchars($student['username']) ?> &bull; <?= htmlspecialchars($student['phone']) ?></span>
                                            <span class="text-[10px] text-slate-500"><?= htmlspecialchars($student['place'] ?? 'No Place') ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Verification Match / Source -->
                                <td class="py-4 px-6">
                                    <?php if (!empty($student['roster_id'])): ?>
                                        <div class="flex flex-col space-y-0.5">
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-300 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded w-fit">
                                                <i class="fa-solid fa-link text-[9px]"></i> Roster Claim
                                            </span>
                                            <span class="text-xs font-semibold text-slate-200"><?= htmlspecialchars($student['roster_name']) ?></span>
                                            <?php if (!empty($student['roster_teacher'])): ?>
                                                <span class="text-[10px] text-slate-400">Assigned: <strong class="text-emerald-300"><?= htmlspecialchars($student['roster_teacher']) ?></strong></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-300 bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 rounded">
                                            <i class="fa-solid fa-sparkles text-[9px]"></i> Direct Admission
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Guardian & Contact -->
                                <td class="py-4 px-6">
                                    <div class="flex flex-col space-y-0.5">
                                        <span class="text-slate-200 font-semibold"><?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?></span>
                                        <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($student['parent_phone'] ?? $student['phone']) ?></span>
                                    </div>
                                </td>

                                <!-- Submitted Date -->
                                <td class="py-4 px-6 text-slate-400 text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($student['created_at'])) ?>
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-4 px-6 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick="openApprovalModal(<?= $student['id'] ?>, '<?= htmlspecialchars(addslashes($student['full_name'])) ?>')" class="px-3.5 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 border border-emerald-500/20 text-emerald-400 hover:text-slate-950 font-bold transition flex items-center gap-1.5" title="Approve & Assign Class">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>
                                        
                                        <form action="" method="POST" onsubmit="return confirm('Are you sure you want to reject this student application?');" class="inline">
                                            <input type="hidden" name="action" value="reject_application">
                                            <input type="hidden" name="user_id" value="<?= $student['id'] ?>">
                                            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-xl bg-rose-500/10 hover:bg-rose-500 border border-rose-500/20 text-rose-400 hover:text-white transition" title="Reject Application">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Section 2: Pending Teacher Verifications -->
<div class="space-y-4 pt-6">
    <div class="flex items-center justify-between">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-chalkboard-user text-cyan-400"></i> Teacher Verifications (<?= count($pendingTeachers) ?>)
        </h2>
    </div>

    <div class="glass-panel rounded-3xl overflow-hidden border border-white/10">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b text-[10px] font-bold uppercase tracking-widest text-slate-400" style="background:rgba(18,59,71,0.3); border-color:rgba(109,204,141,0.08);">
                        <th class="py-4 px-6">Teacher Details</th>
                        <th class="py-4 px-6">Claimed Roster Record</th>
                        <th class="py-4 px-6">Location & Email</th>
                        <th class="py-4 px-6">Date</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-xs" style="divide-color:rgba(109,204,141,0.05);">
                    <?php if (empty($pendingTeachers)): ?>
                        <tr>
                            <td colspan="5" class="py-10 text-center text-slate-400 font-semibold">
                                <i class="fa-solid fa-user-tie text-2xl mb-2 text-slate-500 block"></i>
                                No pending teacher verifications.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingTeachers as $teacher): ?>
                            <tr class="hover:bg-white/[0.02] transition duration-200">
                                <!-- Teacher identity -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full border border-white/10 overflow-hidden flex-shrink-0 bg-slate-950 flex items-center justify-center p-0.5">
                                            <?php if (!empty($teacher['profile_photo'])): ?>
                                                <img src="<?= htmlspecialchars($teacher['profile_photo']) ?>" alt="Avatar" class="w-full h-full object-cover rounded-full">
                                            <?php else: ?>
                                                <div class="w-full h-full brand-gradient flex items-center justify-center text-xs font-bold text-slate-950 rounded-full">
                                                    <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-white truncate max-w-[170px]"><?= htmlspecialchars($teacher['full_name']) ?></span>
                                                <?php if (($teacher['gender'] ?? '') === 'female'): ?>
                                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-pink-500/15 text-pink-300 border border-pink-500/30">Female</span>
                                                <?php else: ?>
                                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-cyan-500/15 text-cyan-300 border border-cyan-500/30">Male</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono">@<?= htmlspecialchars($teacher['username']) ?> &bull; <?= htmlspecialchars($teacher['phone']) ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Claimed Roster Record -->
                                <td class="py-4 px-6">
                                    <div class="flex flex-col space-y-0.5">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-cyan-300 bg-cyan-500/10 border border-cyan-500/20 px-2 py-0.5 rounded w-fit">
                                            <i class="fa-solid fa-user-check text-[9px]"></i> <?= htmlspecialchars($teacher['roster_name'] ?? 'Manual Registration') ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- Contact Info -->
                                <td class="py-4 px-6">
                                    <div class="flex flex-col space-y-0.5">
                                        <span class="text-slate-300"><?= htmlspecialchars($teacher['email']) ?></span>
                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($teacher['place'] ?? 'No Place') ?></span>
                                    </div>
                                </td>

                                <!-- Date -->
                                <td class="py-4 px-6 text-slate-400 text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($teacher['created_at'])) ?>
                                </td>

                                <!-- Actions -->
                                <td class="py-4 px-6 text-right">
                                    <div class="flex justify-end gap-2">
                                        <form action="" method="POST" class="inline">
                                            <input type="hidden" name="action" value="approve_teacher">
                                            <input type="hidden" name="user_id" value="<?= $teacher['id'] ?>">
                                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500 border border-cyan-500/20 text-cyan-400 hover:text-slate-950 font-bold transition flex items-center gap-1.5" title="Approve Teacher">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>

                                        <form action="" method="POST" onsubmit="return confirm('Are you sure you want to reject this teacher registration?');" class="inline">
                                            <input type="hidden" name="action" value="reject_application">
                                            <input type="hidden" name="user_id" value="<?= $teacher['id'] ?>">
                                            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-xl bg-rose-500/10 hover:bg-rose-500 border border-rose-500/20 text-rose-400 hover:text-white transition" title="Reject Application">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Classroom Allocation Modal for Students -->
<div id="approval-modal" class="fixed inset-0 z-50 backdrop-blur-sm bg-black/60 hidden items-center justify-center p-4">
    <div class="w-full max-w-md glass-panel rounded-3xl p-6 border border-white/10 space-y-6 text-left" style="background: rgba(14, 46, 56, 0.98);">
        <!-- Modal Heading -->
        <div class="flex justify-between items-center pb-3 border-b border-white/10">
            <h3 class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-emerald-400"></i> Assign Classroom & Approve
            </h3>
            <button onclick="closeApprovalModal()" class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p class="text-xs text-slate-350 leading-relaxed">
            Select a classroom section for <strong id="modal-student-name" class="text-white"></strong>. Once approved, their account is activated and they can log into the Student Portal.
        </p>

        <!-- Form submission -->
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="approve_student">
            <input type="hidden" id="modal-user-id" name="user_id" value="">
            
            <div class="space-y-1.5">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Select Classroom Stream <span class="text-red-400">*</span></label>
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
                    Approve & Activate
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
