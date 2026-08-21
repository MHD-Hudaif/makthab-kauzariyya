<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Count total students enrolled in this teacher's classes
$stmtCount = $pdo->prepare("
    SELECT COUNT(DISTINCT s.user_id) 
    FROM students s
    JOIN class_teachers ct ON s.class_id = ct.class_id
    WHERE ct.teacher_id = ?
");
$stmtCount->execute([$teacherId]);
$studentCount = (int)$stmtCount->fetchColumn();

$classCount = count($teacherClasses);
?>

<!-- ================= TEACHER OVERVIEW PAGE ================= -->
<div class="space-y-8">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-2xl">
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-emerald-500/30">
            <span class="block text-xs text-slate-400 font-semibold uppercase tracking-wider">Assigned Classes</span>
            <span class="block text-4xl font-extrabold text-white mt-2"><?= $classCount ?></span>
            <i class="fa-solid fa-school absolute right-4 bottom-4 text-emerald-400/10 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-blue-500/30">
            <span class="block text-xs text-slate-400 font-semibold uppercase tracking-wider">Total Enrolled Students</span>
            <span class="block text-4xl font-extrabold text-white mt-2"><?= $studentCount ?></span>
            <i class="fa-solid fa-user-graduate absolute right-4 bottom-4 text-blue-400/10 text-5xl"></i>
        </div>
    </div>

    <!-- Welcome Panel -->
    <div class="glass-panel rounded-3xl p-8 md:p-12 space-y-4">
        <h2 class="text-3xl font-bold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Ahlan Wa Sahlan, Ustad <?= htmlspecialchars($currentUser['full_name']) ?>!</h2>
        <p class="text-slate-350 leading-relaxed text-sm max-w-3xl">
            Welcome to your academic classroom cockpit. From here, you can launch the live class session runner, record student progress benchmarks (Qaida lessons, Nazira pages, or Hifz logs), and submit your weekly reports directly to the Academic Board.
        </p>
        <div class="flex gap-4 pt-2">
            <a href="classroom.php" class="bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 px-6 py-3.5 rounded-xl font-bold hover:shadow-lg hover:shadow-emerald-400/20 transition text-sm flex items-center gap-2">
                <i class="fa-solid fa-chalkboard-user"></i> Go to Live Classroom
            </a>
        </div>
    </div>

    <!-- Active Assigned Classes List -->
    <div class="space-y-4">
        <h3 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-chalkboard text-emerald-400"></i> Your Assigned Classes
        </h3>
        
        <?php if (empty($teacherClasses)): ?>
            <div class="glass-panel rounded-2xl p-10 text-center">
                <i class="fa-solid fa-calendar-xmark text-4xl text-slate-650 mb-3 block"></i>
                <p class="text-slate-500 font-medium">You are not currently assigned to teach any classes. Contact the Academic Coordinator.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($teacherClasses as $cls): ?>
                    <?php
                    // Get student count for this class
                    $stmtCountClass = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
                    $stmtCountClass->execute([$cls['id']]);
                    $clsStudents = (int)$stmtCountClass->fetchColumn();
                    ?>
                    <div class="glass-card rounded-2xl p-6 border border-white/5 flex flex-col justify-between space-y-4 hover:border-emerald-500/25 transition">
                        <div class="space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20"><?= htmlspecialchars($cls['course_code']) ?></span>
                                <span class="text-[9px] uppercase font-bold text-slate-400"><?= htmlspecialchars($cls['type']) ?> Class</span>
                            </div>
                            <h4 class="text-md font-bold text-white pt-1"><?= htmlspecialchars($cls['name']) ?></h4>
                            <p class="text-xs text-slate-400 font-medium"><?= htmlspecialchars($cls['course_name']) ?></p>
                        </div>

                        <div class="flex justify-between items-center pt-2 border-t border-white/5 text-xs">
                            <span class="text-slate-350 font-medium">Students: <span class="text-white font-bold"><?= $clsStudents ?></span></span>
                            <a href="classroom.php?class_id=<?= $cls['id'] ?>" class="text-[11px] font-bold text-emerald-400 hover:text-emerald-300 flex items-center gap-1">
                                Launch Class <i class="fa-solid fa-arrow-right text-[9px]"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
