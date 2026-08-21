<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch extra admin stats
$recentAudits = $pdo->query("SHOW TABLES LIKE 'class_audits'")->fetch() 
    ? (int)$pdo->query("SELECT COUNT(*) FROM class_audits")->fetchColumn() 
    : 0;
?>

<!-- ================= ADMIN OVERVIEW PAGE ================= -->
<div class="space-y-8">
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-6">
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-emerald-500/30 hover:border-t-emerald-400 transition-all duration-300">
            <span class="block text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Coordinators</span>
            <span class="block text-3xl font-extrabold text-white mt-2"><?= $totalCoordinators ?></span>
            <i class="fa-solid fa-user-gear absolute right-4 bottom-4 text-emerald-400/10 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-blue-500/30 hover:border-t-blue-400 transition-all duration-300">
            <span class="block text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Supervisors</span>
            <span class="block text-3xl font-extrabold text-white mt-2"><?= $totalSupervisors ?></span>
            <i class="fa-solid fa-user-tie absolute right-4 bottom-4 text-blue-400/10 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-teal-500/30 hover:border-t-teal-400 transition-all duration-300">
            <span class="block text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Teachers</span>
            <span class="block text-3xl font-extrabold text-white mt-2"><?= $totalTeachers ?></span>
            <i class="fa-solid fa-chalkboard-user absolute right-4 bottom-4 text-teal-400/10 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-cyan-500/30 hover:border-t-cyan-400 transition-all duration-300">
            <span class="block text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Students</span>
            <span class="block text-3xl font-extrabold text-white mt-2"><?= $totalStudents ?></span>
            <i class="fa-solid fa-user-graduate absolute right-4 bottom-4 text-cyan-400/10 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden border-t-2 border-t-emerald-500/30 hover:border-t-emerald-400 transition-all duration-300">
            <span class="block text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Classes</span>
            <span class="block text-3xl font-extrabold text-white mt-2"><?= $totalClasses ?></span>
            <i class="fa-solid fa-school absolute right-4 bottom-4 text-emerald-400/10 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden col-span-2 lg:col-span-1 border-t-2 border-t-blue-500/30 hover:border-t-blue-400 transition-all duration-300">
            <span class="block text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Class Audits</span>
            <span class="block text-3xl font-extrabold text-white mt-2"><?= $recentAudits ?></span>
            <i class="fa-solid fa-clipboard-check absolute right-4 bottom-4 text-blue-400/10 text-5xl"></i>
        </div>
    </div>

    <!-- Welcome panel -->
    <div class="glass-panel rounded-3xl p-8 md:p-12 space-y-4">
        <h2 class="text-3xl font-bold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Hello, Administrator <?= htmlspecialchars($currentUser['full_name']) ?>!</h2>
        <p class="text-slate-300 max-w-3xl leading-relaxed text-sm">
            You are logged in with the highest access level. From this console, you can manage the entire system hierarchy. Configure coordinators, evaluate class audit logs, oversee curriculum structures, and manage the student registry.
        </p>
        <div class="flex flex-wrap gap-4 pt-2">
            <a href="coordinators.php" class="bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 px-6 py-3 rounded-xl font-bold hover:shadow-lg hover:shadow-emerald-400/20 transition text-sm flex items-center gap-2">
                <i class="fa-solid fa-user-gear"></i> Manage Coordinators
            </a>
            <a href="courses.php" class="border border-slate-600 hover:border-slate-400 text-slate-200 px-6 py-3 rounded-xl font-bold transition text-sm flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap"></i> View Classes
            </a>
        </div>
    </div>

    <!-- System Status Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glass-card rounded-2xl p-6 space-y-4">
            <h3 class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-server text-emerald-400"></i> Server & DB Environment
            </h3>
            <div class="text-xs space-y-2 text-slate-300 font-mono">
                <div class="flex justify-between border-b border-white/5 pb-2">
                    <span>PHP Version:</span>
                    <span><?= phpversion() ?></span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-2">
                    <span>DB Connection:</span>
                    <span class="text-emerald-400">Active (PDO)</span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-2">
                    <span>Host:</span>
                    <span><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Port:</span>
                    <span><?= htmlspecialchars(env('DB_PORT', '3306')) ?></span>
                </div>
            </div>
        </div>
        
        <div class="glass-card rounded-2xl p-6 space-y-4">
            <h3 class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-emerald-400"></i> Admin Security Status
            </h3>
            <div class="text-xs space-y-2 text-slate-300 leading-relaxed">
                <p class="flex items-center gap-2 text-emerald-400">
                    <i class="fa-solid fa-check"></i> Single Admin Session Guard Active
                </p>
                <p class="flex items-center gap-2 text-emerald-400">
                    <i class="fa-solid fa-check"></i> Zero-Dependency Environment Protection
                </p>
                <p class="flex items-center gap-2 text-emerald-400">
                    <i class="fa-solid fa-check"></i> Apache URL Rewrite Enabled
                </p>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
