<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- ================= OVERVIEW PAGE ================= -->
<div class="space-y-8">
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
            <span class="block text-sm text-slate-450 font-semibold uppercase tracking-wider">Students</span>
            <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalStudents ?></span>
            <i class="fa-solid fa-user-graduate absolute right-4 bottom-4 text-white/5 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
            <span class="block text-sm text-slate-455 font-semibold uppercase tracking-wider">Teachers</span>
            <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalTeachers ?></span>
            <i class="fa-solid fa-chalkboard-user absolute right-4 bottom-4 text-white/5 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
            <span class="block text-sm text-slate-455 font-semibold uppercase tracking-wider">Supervisors</span>
            <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalSupervisors ?></span>
            <i class="fa-solid fa-user-tie absolute right-4 bottom-4 text-white/5 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
            <span class="block text-sm text-slate-455 font-semibold uppercase tracking-wider">Courses</span>
            <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalCourses ?></span>
            <i class="fa-solid fa-book-open absolute right-4 bottom-4 text-white/5 text-5xl"></i>
        </div>
        <div class="glass-card rounded-2xl p-6 relative overflow-hidden col-span-2 lg:col-span-1">
            <span class="block text-sm text-slate-455 font-semibold uppercase tracking-wider">Classes</span>
            <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalClasses ?></span>
            <i class="fa-solid fa-school absolute right-4 bottom-4 text-white/5 text-5xl"></i>
        </div>
    </div>

    <!-- Welcome panel -->
    <div class="glass-panel rounded-3xl p-8 md:p-12 space-y-4">
        <h2 class="text-3xl font-bold bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Hello, <?= htmlspecialchars($currentUser['full_name']) ?>!</h2>
        <p class="text-slate-300 max-w-3xl leading-relaxed text-sm">
            Welcome back to the Makthab Kauzariyya portal management board. Use the sidebar controls to explore courses, assign class structures, edit supervisors, and evaluate teacher assignments.
        </p>
        <div class="flex gap-4 pt-2">
            <a href="courses" class="bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 px-6 py-3.5 rounded-xl font-bold hover:shadow-lg hover:shadow-emerald-400/20 transition text-sm">
                Manage Courses & Classes
            </a>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
