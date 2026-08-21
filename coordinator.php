<?php
session_start();
require_once 'db.php';

// Authentication Check: Only allow coordinators and admins
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['coordinator', 'admin'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];

// 1. Fetch Stats
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalSupervisors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// 2. Fetch Courses
$courses = $pdo->query("SELECT * FROM courses ORDER BY name ASC")->fetchAll();

// 3. Fetch Classes with Supervisor Details
$classes = $pdo->query("
    SELECT c.*, u.full_name as supervisor_name, u.email as supervisor_email 
    FROM classes c 
    LEFT JOIN users u ON c.supervisor_id = u.id
    ORDER BY c.name ASC
")->fetchAll();

// 4. Fetch Class-Teacher mappings
$classTeachersRaw = $pdo->query("
    SELECT ct.class_id, u.id as teacher_id, u.full_name as teacher_name, u.email as teacher_email 
    FROM class_teachers ct 
    JOIN users u ON ct.teacher_id = u.id
    ORDER BY u.full_name ASC
")->fetchAll();

// 5. Fetch Students mapped to classes
$studentsRaw = $pdo->query("
    SELECT s.*, u.full_name as student_name, u.email as student_email, u.phone as student_phone, c.name as class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY u.full_name ASC
")->fetchAll();

// 6. Fetch Teachers list for Teachers Tab
$teachersRaw = $pdo->query("
    SELECT u.id as teacher_id, u.full_name as teacher_name, u.email as teacher_email, u.phone as teacher_phone, t.specialisation
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.role = 'teacher'
    ORDER BY u.full_name ASC
")->fetchAll();

// 7. Fetch Supervisors list for Supervisors Tab
$supervisorsRaw = $pdo->query("
    SELECT u.id as supervisor_id, u.full_name as supervisor_name, u.email as supervisor_email, u.phone as supervisor_phone
    FROM users u
    WHERE u.role = 'supervisor'
    ORDER BY u.full_name ASC
")->fetchAll();

// --- Process / Group Data in PHP ---
$classesByCourse = [];
foreach ($classes as $class) {
    $classesByCourse[$class['course_id']][] = $class;
}

$teachersByClass = [];
foreach ($classTeachersRaw as $ct) {
    $teachersByClass[$ct['class_id']][] = $ct;
}

$studentsByClass = [];
foreach ($studentsRaw as $student) {
    if ($student['class_id']) {
        $studentsByClass[$student['class_id']][] = $student;
    }
}

// Map classes taught by each teacher
$classesByTeacher = [];
foreach ($classTeachersRaw as $ct) {
    $classesByTeacher[$ct['teacher_id']][] = $ct; // holds class details
}

// Map classes overseen by each supervisor
$classesBySupervisor = [];
foreach ($classes as $class) {
    if ($class['supervisor_id']) {
        $classesBySupervisor[$class['supervisor_id']][] = $class;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kauzariyya - Coordinator Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Liquid Blob Animations */
        @keyframes morph-blob-1 {
            0%, 100% { border-radius: 42% 58% 70% 30% / 45% 45% 55% 55%; transform: translate(0, 0) scale(1); }
            50% { border-radius: 70% 30% 52% 48% / 60% 40% 60% 40%; transform: translate(40px, 30px) scale(1.05); }
        }
        @keyframes morph-blob-2 {
            0%, 100% { border-radius: 70% 30% 52% 48% / 60% 40% 60% 40%; transform: translate(0, 0) scale(1); }
            50% { border-radius: 42% 58% 70% 30% / 45% 45% 55% 55%; transform: translate(-40px, -30px) scale(1.1); }
        }

        .animate-blob-1 {
            animation: morph-blob-1 25s infinite alternate ease-in-out;
        }
        .animate-blob-2 {
            animation: morph-blob-2 20s infinite alternate ease-in-out;
        }

        /* Glassmorphism Styles */
        .glass-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        /* Sidebar navigation link active styling */
        .tab-btn.active {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
            color: #34d399; /* emerald-400 */
        }
    </style>
</head>
<body class="relative min-h-screen bg-slate-950 text-slate-100 overflow-x-hidden flex flex-col md:flex-row">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[5%] left-[20%] w-[35vw] h-[35vw] max-w-[500px] bg-emerald-500/10 blur-[100px] animate-blob-1"></div>
        <div class="absolute bottom-[10%] right-[20%] w-[40vw] h-[40vw] max-w-[600px] bg-blue-600/10 blur-[120px] animate-blob-2"></div>
    </div>

    <!-- Sidebar Navigation -->
    <aside class="w-full md:w-64 glass-panel border-r border-white/10 md:h-screen md:sticky md:top-0 flex flex-col justify-between p-6 z-40">
        <div class="space-y-8">
            <!-- Branding Header -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-900 border border-white/10 flex items-center justify-center p-1">
                    <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-bold tracking-wide uppercase bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Coordinator</span>
                    <span class="text-[9px] text-slate-400 tracking-widest uppercase">Al Jamiathul Kauzariyya</span>
                </div>
            </div>

            <!-- Tab Menu -->
            <nav class="space-y-2 flex flex-col">
                <button onclick="switchTab('overview')" id="btn-overview" class="tab-btn active w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition border border-transparent text-emerald-400 bg-white/10 text-left">
                    <i class="fa-solid fa-chart-pie w-5"></i> Overview
                </button>
                <button onclick="switchTab('courses')" id="btn-courses" class="tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                    <i class="fa-solid fa-graduation-cap w-5"></i> Courses & Classes
                </button>
                <button onclick="switchTab('teachers')" id="btn-teachers" class="tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                    <i class="fa-solid fa-chalkboard-user w-5"></i> Teachers
                </button>
                <button onclick="switchTab('supervisors')" id="btn-supervisors" class="tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                    <i class="fa-solid fa-user-tie w-5"></i> Supervisors
                </button>
                <button onclick="switchTab('students')" id="btn-students" class="tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                    <i class="fa-solid fa-user-graduate w-5"></i> Students
                </button>
            </nav>
        </div>

        <!-- Sidebar Footer & Controls -->
        <div class="pt-6 border-t border-white/10 mt-6 space-y-4">
            <!-- User Badge -->
            <div class="flex items-center gap-2.5 px-1">
                <div class="w-8 h-8 rounded-full bg-slate-900 border border-white/15 flex items-center justify-center text-xs font-bold text-emerald-400">
                    <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                    <span class="text-[9px] text-slate-450 uppercase font-semibold tracking-wider"><?= htmlspecialchars($currentUser['role']) ?></span>
                </div>
            </div>

            <!-- Buttons Grid -->
            <div class="grid grid-cols-2 gap-2">
                <a href="index.php" class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg bg-white/5 hover:bg-white/10 text-xs font-semibold text-slate-300 hover:text-white transition border border-white/8" title="Back to Homepage">
                    <i class="fa-solid fa-arrow-left"></i> Home
                </a>
                <a href="logout.php" class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-xs font-semibold text-red-400 transition border border-red-500/15" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Panel -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- Header (Dynamic Tab Title Banner) -->
        <header class="h-20 border-b border-white/10 flex items-center justify-between px-8 bg-slate-950/20 backdrop-blur-md sticky top-0 z-30">
            <h2 id="current-tab-title" class="text-xl font-bold text-white tracking-wide">Overview</h2>
            <div class="flex items-center gap-2 text-xs text-slate-450 bg-white/5 px-3.5 py-1.5 rounded-full border border-white/8">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Active Portal</span>
            </div>
        </header>

        <!-- Main Scrollable Section -->
        <main class="flex-1 p-6 md:p-8 space-y-8 w-full max-w-7xl mx-auto">

            <!-- ================= OVERVIEW TAB ================= -->
            <section id="tab-overview" class="tab-content space-y-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-6">
                    <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
                        <span class="block text-sm text-slate-400 font-semibold uppercase tracking-wider">Students</span>
                        <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalStudents ?></span>
                        <i class="fa-solid fa-user-graduate absolute right-4 bottom-4 text-white/5 text-5xl"></i>
                    </div>
                    <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
                        <span class="block text-sm text-slate-400 font-semibold uppercase tracking-wider">Teachers</span>
                        <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalTeachers ?></span>
                        <i class="fa-solid fa-chalkboard-user absolute right-4 bottom-4 text-white/5 text-5xl"></i>
                    </div>
                    <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
                        <span class="block text-sm text-slate-400 font-semibold uppercase tracking-wider">Supervisors</span>
                        <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalSupervisors ?></span>
                        <i class="fa-solid fa-user-tie absolute right-4 bottom-4 text-white/5 text-5xl"></i>
                    </div>
                    <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
                        <span class="block text-sm text-slate-400 font-semibold uppercase tracking-wider">Courses</span>
                        <span class="block text-4xl font-extrabold text-white mt-2"><?= $totalCourses ?></span>
                        <i class="fa-solid fa-book-open absolute right-4 bottom-4 text-white/5 text-5xl"></i>
                    </div>
                    <div class="glass-card rounded-2xl p-6 relative overflow-hidden col-span-2 lg:col-span-1">
                        <span class="block text-sm text-slate-400 font-semibold uppercase tracking-wider">Classes</span>
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
                        <button onclick="switchTab('courses')" class="bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 px-6 py-3 rounded-xl font-bold hover:shadow-lg hover:shadow-emerald-400/20 transition text-sm">
                            Manage Courses & Classes
                        </button>
                    </div>
                </div>
            </section>

            <!-- ================= COURSES & CLASSES TAB ================= -->
            <section id="tab-courses" class="tab-content hidden space-y-8">
                <div class="space-y-6">
                    <?php foreach ($courses as $course): ?>
                        <div class="glass-panel rounded-2xl p-6 space-y-4">
                            <div class="flex justify-between items-start border-b border-white/10 pb-4">
                                <div>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-md"><?= htmlspecialchars($course['code']) ?></span>
                                    <h3 class="text-xl font-bold text-white mt-2"><?= htmlspecialchars($course['name']) ?></h3>
                                </div>
                                <p class="text-sm text-slate-450 max-w-md text-right"><?= htmlspecialchars($course['description']) ?></p>
                            </div>

                            <!-- Classes Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php 
                                $courseClasses = $classesByCourse[$course['id']] ?? [];
                                if (empty($courseClasses)): 
                                ?>
                                    <p class="text-sm text-slate-500 py-2">No classes created under this course yet.</p>
                                <?php else: ?>
                                    <?php foreach ($courseClasses as $class): ?>
                                        <div class="glass-card rounded-xl p-5 border border-white/5 space-y-4">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="text-lg font-bold text-white"><?= htmlspecialchars($class['name']) ?></h4>
                                                    <span class="inline-block text-[10px] font-semibold tracking-wider uppercase px-2 py-0.5 rounded <?= $class['type'] === 'individual' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30' ?> mt-1">
                                                        <?= htmlspecialchars($class['type']) ?> Class
                                                    </span>
                                                </div>
                                                
                                                <!-- Supervisor Badge -->
                                                <div class="text-right">
                                                    <span class="block text-[10px] text-slate-450 font-semibold uppercase tracking-wider">Supervisor</span>
                                                    <span class="text-xs text-slate-200 font-medium"><?= htmlspecialchars($class['supervisor_name'] ?? 'Not Assigned') ?></span>
                                                </div>
                                            </div>

                                            <!-- Teachers Taught Many-to-Many -->
                                            <div>
                                                <span class="block text-[10px] text-slate-450 font-semibold uppercase tracking-wider mb-1.5">Teachers</span>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <?php 
                                                    $classTeachers = $teachersByClass[$class['id']] ?? [];
                                                    if (empty($classTeachers)): 
                                                    ?>
                                                        <span class="text-xs text-slate-500">No teachers assigned</span>
                                                    <?php else: ?>
                                                        <?php foreach ($classTeachers as $teacher): ?>
                                                            <span class="text-xs px-2.5 py-1 rounded-md bg-white/5 border border-white/10 text-slate-300" title="<?= htmlspecialchars($teacher['teacher_email']) ?>">
                                                                <i class="fa-solid fa-chalkboard-user text-emerald-400 mr-1 text-[10px]"></i><?= htmlspecialchars($teacher['teacher_name']) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Students inside Class -->
                                            <div>
                                                <span class="block text-[10px] text-slate-450 font-semibold uppercase tracking-wider mb-2">Students (<?= count($studentsByClass[$class['id']] ?? []) ?>)</span>
                                                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                                    <?php 
                                                    $classStudents = $studentsByClass[$class['id']] ?? [];
                                                    if (empty($classStudents)): 
                                                    ?>
                                                        <p class="text-xs text-slate-500">No students enrolled yet</p>
                                                    <?php else: ?>
                                                        <?php foreach ($classStudents as $student): ?>
                                                            <div class="flex justify-between items-center bg-white/[0.02] border border-white/5 px-2.5 py-1.5 rounded-lg text-xs">
                                                                <span class="font-medium text-slate-300"><?= htmlspecialchars($student['student_name']) ?></span>
                                                                <span class="text-slate-400 font-mono text-[10px]"><?= htmlspecialchars($student['admission_no']) ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ================= TEACHERS TAB ================= -->
            <section id="tab-teachers" class="tab-content hidden space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach ($teachersRaw as $teacher): ?>
                        <div class="glass-panel rounded-2xl p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-500 flex items-center justify-center text-slate-950 font-bold text-lg">
                                        <?= strtoupper(substr($teacher['teacher_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($teacher['teacher_name']) ?></h3>
                                        <p class="text-xs text-slate-400"><?= htmlspecialchars($teacher['teacher_email']) ?></p>
                                    </div>
                                </div>
                                <span class="text-xs px-2.5 py-1 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-medium">Teacher</span>
                            </div>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-450">Phone:</span>
                                    <span class="text-slate-200"><?= htmlspecialchars($teacher['teacher_phone'] ?? 'N/A') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-450">Specialisation:</span>
                                    <span class="text-slate-200 text-right"><?= htmlspecialchars($teacher['specialisation'] ?? 'N/A') ?></span>
                                </div>

                                <!-- Classes Taught (Many-to-Many) -->
                                <div class="pt-2">
                                    <span class="block text-[10px] text-slate-450 font-semibold uppercase tracking-wider mb-2">Assigned Classes</span>
                                    <div class="flex flex-wrap gap-2">
                                        <?php 
                                        $taughtClasses = $classesByTeacher[$teacher['teacher_id']] ?? [];
                                        if (empty($taughtClasses)): 
                                        ?>
                                            <span class="text-xs text-slate-500">No classes assigned</span>
                                        <?php else: ?>
                                            <?php foreach ($taughtClasses as $classRef): 
                                                // Find full class details from classes list
                                                $fullClass = array_filter($classes, fn($c) => $c['id'] == $classRef['class_id']);
                                                $fullClass = reset($fullClass);
                                            ?>
                                                <?php if ($fullClass): ?>
                                                    <span class="text-xs px-3 py-1.5 rounded-lg bg-emerald-500/5 border border-emerald-500/15 text-slate-200">
                                                        <i class="fa-solid fa-school text-emerald-400 mr-1 text-[10px]"></i><?= htmlspecialchars($fullClass['name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ================= SUPERVISORS TAB ================= -->
            <section id="tab-supervisors" class="tab-content hidden space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach ($supervisorsRaw as $supervisor): ?>
                        <div class="glass-panel rounded-2xl p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-slate-950 font-bold text-lg">
                                        <?= strtoupper(substr($supervisor['supervisor_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($supervisor['supervisor_name']) ?></h3>
                                        <p class="text-xs text-slate-400"><?= htmlspecialchars($supervisor['supervisor_email']) ?></p>
                                    </div>
                                </div>
                                <span class="text-xs px-2.5 py-1 rounded bg-blue-500/10 border border-blue-500/20 text-blue-400 font-medium">Supervisor</span>
                            </div>

                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-450">Phone:</span>
                                    <span class="text-slate-200"><?= htmlspecialchars($supervisor['supervisor_phone'] ?? 'N/A') ?></span>
                                </div>

                                <!-- Classes Supervised -->
                                <div class="pt-2">
                                    <span class="block text-[10px] text-slate-450 font-semibold uppercase tracking-wider mb-2">Overseen Classes</span>
                                    <div class="flex flex-wrap gap-2">
                                        <?php 
                                        $supervisedClasses = $classesBySupervisor[$supervisor['supervisor_id']] ?? [];
                                        if (empty($supervisedClasses)): 
                                        ?>
                                            <span class="text-xs text-slate-500">No classes assigned</span>
                                        <?php else: ?>
                                            <?php foreach ($supervisedClasses as $class): ?>
                                                <span class="text-xs px-3 py-1.5 rounded-lg bg-blue-500/5 border border-blue-500/15 text-slate-200">
                                                    <i class="fa-solid fa-eye text-blue-400 mr-1.5 text-[10px]"></i><?= htmlspecialchars($class['name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ================= STUDENTS TAB ================= -->
            <section id="tab-students" class="tab-content hidden space-y-8">
                <div class="glass-panel rounded-2xl overflow-hidden border border-white/10">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/15 bg-white/[0.02]">
                                    <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-400">Name</th>
                                    <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-400">Admission No</th>
                                    <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-400">Class</th>
                                    <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-400">Parent / Guardian</th>
                                    <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-400">Date of Birth</th>
                                    <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-400">Contact</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <?php if (empty($studentsRaw)): ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-500">No students registered yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($studentsRaw as $student): ?>
                                        <tr class="hover:bg-white/[0.02] transition">
                                            <td class="p-4 font-semibold text-white"><?= htmlspecialchars($student['student_name']) ?></td>
                                            <td class="p-4 font-mono text-xs text-slate-300"><?= htmlspecialchars($student['admission_no'] ?? 'N/A') ?></td>
                                            <td class="p-4">
                                                <span class="px-2.5 py-1 rounded bg-white/5 border border-white/10 text-xs">
                                                    <?= htmlspecialchars($student['class_name'] ?? 'Unassigned') ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-slate-300"><?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?></td>
                                            <td class="p-4 text-slate-350"><?= htmlspecialchars($student['dob'] ?? 'N/A') ?></td>
                                            <td class="p-4 text-xs text-slate-400">
                                                <div class="space-y-0.5">
                                                    <div class="flex items-center gap-1.5"><i class="fa-regular fa-envelope text-[10px]"></i><?= htmlspecialchars($student['student_email'] ?? 'N/A') ?></div>
                                                    <div class="flex items-center gap-1.5"><i class="fa-solid fa-phone text-[10px]"></i><?= htmlspecialchars($student['student_phone'] ?? 'N/A') ?></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- Tab switcher JS -->
    <script>
        function switchTab(tabId) {
            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.add('hidden');
            });
            // Show selected tab content
            document.getElementById('tab-' + tabId).classList.remove('hidden');

            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
                btn.classList.remove('bg-white/10');
                btn.classList.remove('text-emerald-400');
                btn.classList.add('text-slate-350');
            });
            
            // Activate selected tab button
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.classList.add('active');
            activeBtn.classList.add('bg-white/10');
            activeBtn.classList.add('text-emerald-400');
            activeBtn.classList.remove('text-slate-350');

            // Update Page Header Title
            const titles = {
                'overview': 'Overview',
                'courses': 'Courses & Classes Tree',
                'teachers': 'Teachers Registry',
                'supervisors': 'Supervisors Registry',
                'students': 'Student Directory'
            };
            document.getElementById('current-tab-title').innerText = titles[tabId] || 'Dashboard';
        }
    </script>
</body>
</html>
