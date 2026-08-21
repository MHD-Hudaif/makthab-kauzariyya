<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Calculate Attendance metrics
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM progress_logs WHERE student_id = ?");
$stmtTotal->execute([$studentId]);
$totalSessions = (int)$stmtTotal->fetchColumn();

$stmtPresent = $pdo->prepare("SELECT COUNT(*) FROM progress_logs WHERE student_id = ? AND is_present = 1");
$stmtPresent->execute([$studentId]);
$presentSessions = (int)$stmtPresent->fetchColumn();

$attendanceRate = $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100) : 0;

// Fetch latest logged progress entry
$stmtLatest = $pdo->prepare("
    SELECT * FROM progress_logs 
    WHERE student_id = ? AND is_present = 1 
    ORDER BY logged_date DESC LIMIT 1
");
$stmtLatest->execute([$studentId]);
$latestProgress = $stmtLatest->fetch();

// Determine current level based on course type
$currentLevel = 0;
$progressText = 'No logs recorded yet.';
if ($studentInfo && $latestProgress) {
    if ($studentInfo['course_code'] === 'TJ' || $studentInfo['course_code'] === 'SPECIAL') {
        $currentLevel = (int)$latestProgress['current_lesson'];
        $progressText = "Lesson {$currentLevel} of {$studentInfo['total_targets']} (Noorani Qaida)";
    } elseif ($studentInfo['course_code'] === 'NZ' || $studentInfo['course_code'] === 'SHARIAH') {
        $currentLevel = (int)$latestProgress['current_juz'];
        $progressText = "Juz {$currentLevel}, Page " . ($latestProgress['current_page'] ?? 'N/A') . " of {$studentInfo['total_targets']} Juz";
    } elseif ($studentInfo['course_code'] === 'HZ' || $studentInfo['course_code'] === 'HIFZ') {
        // Query total completed Juz for Hifz from database logic or default to last logged
        $currentLevel = (int)($latestProgress['current_juz'] ?? 0);
        $progressText = "Memorized: {$latestProgress['sabak_lines']} lines today | Last Recited Juz: " . ($currentLevel > 0 ? $currentLevel : 'None');
    }
}
?>

<!-- ================= STUDENT DASHBOARD ================= -->
<div class="space-y-8">
    
    <!-- Welcome Profile Banner -->
    <div class="glass-panel rounded-3xl p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 border border-white/10">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full brand-gradient flex items-center justify-center text-2xl font-bold text-slate-950 relative">
                <?php if (!empty($currentUser['profile_photo'])): ?>
                    <img src="<?= htmlspecialchars($currentUser['profile_photo']) ?>" alt="Avatar" class="w-full h-full rounded-full object-cover">
                <?php else: ?>
                    <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                <?php endif; ?>
                <span class="absolute bottom-0 right-0 w-4 h-4 rounded-full border-2 border-slate-900 bg-emerald-400"></span>
            </div>
            <div class="space-y-1">
                <h2 class="text-xl font-bold text-white">Assalamu Alaikum, <?= htmlspecialchars($currentUser['full_name']) ?>!</h2>
                <div class="flex flex-wrap gap-2 text-xs text-slate-400 font-medium">
                    <span>Admission No: <span class="text-white font-mono"><?= $studentInfo ? htmlspecialchars($studentInfo['admission_no']) : 'N/A' ?></span></span>
                    <span>•</span>
                    <span>Place: <span class="text-white"><?= htmlspecialchars($currentUser['place'] ?? 'Not Set') ?></span></span>
                </div>
            </div>
        </div>

        <?php if ($studentInfo && !empty($studentInfo['class_name'])): ?>
            <div class="text-left md:text-right space-y-1">
                <span class="text-[9px] uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded font-bold"><?= htmlspecialchars($studentInfo['course_code']) ?> Program</span>
                <div class="text-sm font-bold text-white mt-1"><?= htmlspecialchars($studentInfo['class_name']) ?></div>
                <div class="text-xs text-slate-400"><?= htmlspecialchars($studentInfo['course_name']) ?></div>
            </div>
        <?php else: ?>
            <div class="bg-blue-500/10 text-blue-400 border border-blue-500/15 p-3.5 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-base"></i>
                <span>Pending Classroom Assignment. The Coordinator will assign your class stream shortly.</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($studentInfo && !empty($studentInfo['class_name'])): ?>
        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <!-- Attendance Rate -->
            <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-emerald-500/30">
                <span class="block text-xs text-slate-400 font-semibold uppercase tracking-wider">Attendance Rate</span>
                <span class="block text-3xl font-extrabold text-white mt-2"><?= $attendanceRate ?>%</span>
                <span class="block text-[10px] text-slate-500 mt-1"><?= $presentSessions ?> of <?= $totalSessions ?> Sessions Attended</span>
                <i class="fa-solid fa-calendar-check absolute right-4 bottom-4 text-emerald-400/10 text-4xl"></i>
            </div>

            <!-- Current Progress Log -->
            <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-blue-500/30 col-span-2">
                <span class="block text-xs text-slate-400 font-semibold uppercase tracking-wider">Academic Progress Milestone</span>
                <span class="block text-2xl font-extrabold text-white mt-2 truncate max-w-md"><?= htmlspecialchars($progressText) ?></span>
                <span class="block text-[10px] text-slate-500 mt-2 font-medium">Last updated: <?= $latestProgress ? date('d-M-Y', strtotime($latestProgress['logged_date'])) : 'Never' ?></span>
                <i class="fa-solid fa-graduation-cap absolute right-4 bottom-4 text-blue-400/10 text-4xl"></i>
            </div>
        </div>

        <!-- Visual Milestone Timeline (Phase 5 Spec) -->
        <div class="glass-panel rounded-3xl p-6 md:p-8 space-y-6 border border-white/10">
            <div class="space-y-1">
                <h3 class="text-md font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-route text-emerald-400"></i> Course Path Roadmap
                </h3>
                <p class="text-xs text-slate-450">Track your completed milestones chronologically towards course targets.</p>
            </div>

            <?php
            $targetCount = $studentInfo['total_targets'];
            $targetType = $studentInfo['target_type'];
            
            // Build progression timeline array
            $nodes = [];
            
            if ($studentInfo['course_code'] === 'HZ' || $studentInfo['course_code'] === 'HIFZ') {
                // Reverse 30 to 26 sequence, then 1 up to 25
                $seq = array_merge(range(30, 26), range(1, 25));
                foreach ($seq as $num) {
                    $nodes[] = [
                        'label' => "Juz {$num}",
                        'is_completed' => ($currentLevel > 0 && ($num >= 26 && $num >= $currentLevel)) // Simplified demo calculation
                    ];
                }
            } else {
                // Linear Lesson/Juz progression path (1 to total_targets)
                for ($n = 1; $n <= $targetCount; $n++) {
                    $nodes[] = [
                        'label' => ($targetType === 'juz') ? "Juz {$n}" : "Lesson {$n}",
                        'is_completed' => ($n <= $currentLevel)
                    ];
                }
            }
            ?>

            <!-- Horizontal Scrollable Timeline Wrapper -->
            <div class="overflow-x-auto pb-4 pt-6 scrollbar-thin">
                <div class="flex items-center space-x-6 min-w-max px-4">
                    <?php foreach ($nodes as $index => $node): ?>
                        <!-- Node Item -->
                        <div class="flex items-center">
                            <div class="flex flex-col items-center space-y-2.5">
                                <!-- Glowing circle indicator -->
                                <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-xs transition-all duration-300 relative
                                    <?= $node['is_completed'] 
                                        ? 'bg-emerald-500/10 text-emerald-400 border-2 border-emerald-400 shadow-[0_0_12px_rgba(109,204,141,0.25)]' 
                                        : 'bg-white/5 text-slate-500 border border-white/10' ?>">
                                    <?php if ($node['is_completed']): ?>
                                        <i class="fa-solid fa-check text-xs"></i>
                                    <?php else: ?>
                                        <?= $index + 1 ?>
                                    <?php endif; ?>
                                    
                                    <!-- Dynamic Active Pulse Indicator -->
                                    <?php if ($index === $currentLevel): ?>
                                        <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] font-bold tracking-wider uppercase <?= $node['is_completed'] ? 'text-white' : 'text-slate-500' ?>">
                                    <?= htmlspecialchars($node['label']) ?>
                                </span>
                            </div>

                            <!-- Connecting line (except last node) -->
                            <?php if ($index < count($nodes) - 1): ?>
                                <div class="w-12 h-0.5 transition-colors duration-300 <?= $node['is_completed'] && $nodes[$index+1]['is_completed'] ? 'bg-emerald-400 shadow-[0_0_8px_rgba(109,204,141,0.3)]' : 'bg-white/10' ?>"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
