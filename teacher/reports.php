<?php
require_once __DIR__ . '/includes/header.php';

// Dynamic migration: auto-create weekly_reports table if it doesn't exist
try {
    $pdo->query("SELECT 1 FROM `weekly_reports` LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `weekly_reports` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `teacher_id` INT UNSIGNED NOT NULL,
                `class_id` INT UNSIGNED NOT NULL,
                `report_week` DATE NOT NULL,
                `topics_covered` TEXT,
                `slow_learners` TEXT,
                `attendance_remarks` TEXT,
                `general_feedback` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
}

// --- POST HANDLING: SUBMIT REPORT ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $class_id = $_POST['class_id'] ?? '';
        $report_week = $_POST['report_week'] ?? '';
        $topics_covered = trim($_POST['topics_covered'] ?? '');
        $slow_learners = trim($_POST['slow_learners'] ?? '');
        $attendance_remarks = trim($_POST['attendance_remarks'] ?? '');
        $general_feedback = trim($_POST['general_feedback'] ?? '');

        if (empty($class_id) || empty($report_week)) {
            throw new Exception("Please select a class and reporting week.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO weekly_reports (teacher_id, class_id, report_week, topics_covered, slow_learners, attendance_remarks, general_feedback)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$teacherId, $class_id, $report_week, $topics_covered, $slow_learners, $attendance_remarks, $general_feedback]);

        $_SESSION['msg_success'] = "Weekly report for the week ending " . htmlspecialchars($report_week) . " submitted successfully.";
        header("Location: reports.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['msg_error'] = "Failed to submit report: " . $e->getMessage();
        header("Location: reports.php");
        exit;
    }
}

// Fetch historical reports submitted by this teacher
$stmtHistory = $pdo->prepare("
    SELECT r.*, c.name as class_name 
    FROM weekly_reports r
    JOIN classes c ON r.class_id = c.id
    WHERE r.teacher_id = ?
    ORDER BY r.report_week DESC, r.created_at DESC
");
$stmtHistory->execute([$teacherId]);
$reportsHistory = $stmtHistory->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- ================= WEEKLY REPORTS PAGE ================= -->
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <h3 class="text-lg font-bold text-white">Weekly Progress Report Card</h3>
            <p class="text-xs text-slate-400">Rule 21: Submit a weekly review report for your classes every Saturday.</p>
        </div>
        <span class="text-xs px-3 py-1.5 rounded-full border text-emerald-400" style="background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.15);">
            Saturdays Reporting
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Form Panel -->
        <div class="lg:col-span-2 glass-panel rounded-3xl p-6 border border-white/10 space-y-6">
            <h4 class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-file-pen text-emerald-400"></i> Submit New Report
            </h4>

            <form action="" method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Select Class</label>
                        <select name="class_id" required class="glass-input w-full px-4 py-3 rounded-xl text-sm cursor-pointer">
                            <option value="">-- Choose Class --</option>
                            <?php foreach ($teacherClasses as $tc): ?>
                                <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Report Ending Date (Saturday)</label>
                        <input type="date" name="report_week" required class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Syllabus Topics Covered</label>
                    <textarea name="topics_covered" required class="glass-input w-full px-4 py-3 rounded-xl text-xs h-24" placeholder="List the specific lessons, surahs, or textbook chapters taught this week..."></textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Slow Learners Progress (Rule 7)</label>
                    <textarea name="slow_learners" class="glass-input w-full px-4 py-3 rounded-xl text-xs h-24" placeholder="Detail attention given to weak students, extra Saturday classes conducted, or progress updates..."></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Attendance Remarks</label>
                        <textarea name="attendance_remarks" class="glass-input w-full px-4 py-3 rounded-xl text-xs h-24" placeholder="Log absences or persistent student leaves..."></textarea>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">General Feedback / Challenges</label>
                        <textarea name="general_feedback" class="glass-input w-full px-4 py-3 rounded-xl text-xs h-24" placeholder="Share parent communication feedback or technical issues..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-white/10">
                    <button type="submit" class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs hover:shadow-lg hover:shadow-emerald-400/20 transition flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Submit Report Card
                    </button>
                </div>
            </form>
        </div>

        <!-- History Sidebar -->
        <div class="glass-panel rounded-3xl p-6 border border-white/10 space-y-6 max-h-[80vh] overflow-y-auto">
            <h4 class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-emerald-400"></i> History
            </h4>

            <?php if (empty($reportsHistory)): ?>
                <div class="text-center py-10">
                    <i class="fa-solid fa-file-invoice text-3xl text-slate-650 mb-2 block"></i>
                    <p class="text-xs text-slate-500">No reports submitted yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($reportsHistory as $rep): ?>
                        <div class="glass-card rounded-xl p-4 border border-white/5 space-y-2">
                            <div class="flex justify-between items-center pb-2 border-b border-white/5">
                                <span class="text-xs font-bold text-white truncate max-w-[120px]"><?= htmlspecialchars($rep['class_name']) ?></span>
                                <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($rep['report_week']) ?></span>
                            </div>
                            <div class="text-[11px] space-y-1.5 text-slate-300">
                                <div><span class="text-[9px] text-slate-500 font-semibold block uppercase">Topics:</span><?= htmlspecialchars(substr($rep['topics_covered'], 0, 80)) ?>...</div>
                                <?php if (!empty($rep['slow_learners'])): ?>
                                    <div><span class="text-[9px] text-slate-500 font-semibold block uppercase">Slow Learners:</span><?= htmlspecialchars(substr($rep['slow_learners'], 0, 80)) ?>...</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
