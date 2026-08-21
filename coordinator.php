<?php
session_start();
require_once 'db.php';

// Authentication Check: Only allow coordinators and admins
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['coordinator', 'admin'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$successMessage = '';
$errorMessage = '';
$activeTab = $_GET['tab'] ?? 'overview';

// --- POST SUBMISSIONS (CRUD Handlers) ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE COURSE
        if ($action === 'save_course') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $description = trim($_POST['description'] ?? '');

            if (empty($id)) {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO courses (name, code, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $description]);
                $successMessage = "Course '{$name}' created successfully.";
            } else {
                // Update
                $stmt = $pdo->prepare("UPDATE courses SET name = ?, code = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $code, $description, $id]);
                $successMessage = "Course '{$name}' updated successfully.";
            }
            $_SESSION['msg_success'] = $successMessage;
            header("Location: coordinator.php?tab=courses");
            exit;
        }

        // 2. SAVE CLASS
        if ($action === 'save_class') {
            $id = $_POST['id'] ?? '';
            $course_id = $_POST['course_id'] ?? null;
            $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'regular';

            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO classes (course_id, supervisor_id, name, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$course_id, $supervisor_id, $name, $type]);
                $successMessage = "Class '{$name}' created successfully.";
            } else {
                $stmt = $pdo->prepare("UPDATE classes SET course_id = ?, supervisor_id = ?, name = ?, type = ? WHERE id = ?");
                $stmt->execute([$course_id, $supervisor_id, $name, $type, $id]);
                $successMessage = "Class '{$name}' updated successfully.";
            }
            $_SESSION['msg_success'] = $successMessage;
            header("Location: coordinator.php?tab=courses");
            exit;
        }

        // 3. SAVE TEACHER
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
            }

            // Sync Many-to-Many classes
            $pdo->prepare("DELETE FROM class_teachers WHERE teacher_id = ?")->execute([$userId]);
            if (!empty($class_ids)) {
                $ins = $pdo->prepare("INSERT INTO class_teachers (class_id, teacher_id) VALUES (?, ?)");
                foreach ($class_ids as $cid) {
                    $ins->execute([$cid, $userId]);
                }
            }

            $successMessage = "Teacher '{$full_name}' saved successfully.";
            $_SESSION['msg_success'] = $successMessage;
            header("Location: coordinator.php?tab=teachers");
            exit;
        }

        // 4. SAVE SUPERVISOR
        if ($action === 'save_supervisor') {
            $userId = $_POST['supervisor_id'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? null);
            $phone = trim($_POST['phone'] ?? null);

            if (empty($userId)) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    throw new Exception("Username '{$username}' already exists.");
                }

                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, 'supervisor', 'active')");
                $stmt->execute([$username, $hashedPass, $full_name, $email, $phone]);
            } else {
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $phone, $userId]);
                }
            }

            $successMessage = "Supervisor '{$full_name}' saved successfully.";
            $_SESSION['msg_success'] = $successMessage;
            header("Location: coordinator.php?tab=supervisors");
            exit;
        }

        // 5. SAVE STUDENT
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
                $stmt2->execute([$userId, $admission_no, $parent_name, $dob, $class_id]);
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
                    $stmt2->execute([$admission_no, $parent_name, $dob, $class_id, $userId]);
                } else {
                    $stmt2 = $pdo->prepare("INSERT INTO students (user_id, admission_no, parent_name, dob, class_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->execute([$userId, $admission_no, $parent_name, $dob, $class_id]);
                }
            }

            $successMessage = "Student '{$full_name}' saved successfully.";
            $_SESSION['msg_success'] = $successMessage;
            header("Location: coordinator.php?tab=students");
            exit;
        }

        // 6. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'course') {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = "Course deleted successfully.";
            } elseif ($entity_type === 'class') {
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = "Class deleted successfully.";
            } elseif (in_array($entity_type, ['teacher', 'supervisor', 'student'])) {
                // Delete user account - cascades to details tables automatically due to ON DELETE CASCADE
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $successMessage = ucfirst($entity_type) . " profile deleted successfully.";
            }

            $_SESSION['msg_success'] = $successMessage;
            header("Location: coordinator.php?tab=" . ($_POST['tab_origin'] ?? 'overview'));
            exit;
        }

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Retrieve session flash success messages
if (isset($_SESSION['msg_success'])) {
    $successMessage = $_SESSION['msg_success'];
    unset($_SESSION['msg_success']);
}

// --- FETCH DATA ---
// 1. Stats
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
    SELECT s.*, u.full_name as student_name, u.username as student_username, u.email as student_email, u.phone as student_phone, c.name as class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY u.full_name ASC
")->fetchAll();

// 6. Fetch Teachers list for Teachers Tab
$teachersRaw = $pdo->query("
    SELECT u.id as teacher_id, u.username as teacher_username, u.full_name as teacher_name, u.email as teacher_email, u.phone as teacher_phone, t.specialisation
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.role = 'teacher'
    ORDER BY u.full_name ASC
")->fetchAll();

// 7. Fetch Supervisors list for Supervisors Tab
$supervisorsRaw = $pdo->query("
    SELECT u.id as supervisor_id, u.username as supervisor_username, u.full_name as supervisor_name, u.email as supervisor_email, u.phone as supervisor_phone
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
    $classesByTeacher[$ct['teacher_id']][] = $ct['class_id']; 
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="relative min-h-screen bg-slate-950 text-slate-100 overflow-x-hidden flex flex-col md:flex-row">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[5%] left-[20%] w-[35vw] h-[35vw] max-w-[500px] bg-emerald-500/10 blur-[100px] animate-blob-1"></div>
        <div class="absolute bottom-[10%] right-[20%] w-[40vw] h-[40vw] max-w-[600px] bg-blue-600/10 blur-[120px] animate-blob-2"></div>
    </div>

    <!-- Mobile Top Navigation Header -->
    <header class="flex md:hidden items-center justify-between px-6 h-16 bg-[#0a0f1a]/95 border-b border-white/10 sticky top-0 z-50 backdrop-blur-md w-full">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-900 border border-white/10 flex items-center justify-center p-0.5">
                <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <span class="text-sm font-bold tracking-wide uppercase bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Coordinator</span>
        </div>
        <button onclick="toggleMobileSidebar(true)" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 transition" title="Open Sidebar Menu">
            <i class="fa-solid fa-bars text-white text-lg"></i>
        </button>
    </header>

    <!-- Mobile Sidebar Dark Overlay Backdrop -->
    <div id="sidebar-overlay" onclick="toggleMobileSidebar(false)" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity duration-300 opacity-0 pointer-events-none"></div>

    <!-- Sidebar Navigation -->
    <aside id="sidebar-drawer" class="fixed inset-y-0 left-0 w-64 bg-[#0a0f1a]/95 border-r border-white/10 shadow-2xl md:fixed md:top-4 md:left-4 md:bottom-4 md:h-[calc(100vh-32px)] md:rounded-[22px] md:border flex flex-col justify-between p-6 z-50 backdrop-blur-md transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
        <div class="space-y-8">
            <!-- Branding Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-900 border border-white/10 flex items-center justify-center p-1">
                        <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold tracking-wide uppercase bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Coordinator</span>
                        <span class="text-[9px] text-slate-455 tracking-widest uppercase">Al Jamiathul Kauzariyya</span>
                    </div>
                </div>
                <!-- Close Button for Mobile -->
                <button onclick="toggleMobileSidebar(false)" class="flex md:hidden w-8 h-8 items-center justify-center rounded-lg bg-white/5 hover:bg-white/10 border border-white/8 transition" title="Close Menu">
                    <i class="fa-solid fa-xmark text-slate-400"></i>
                </button>
            </div>

            <!-- Tab Menu -->
            <!-- Tab Menu (Grouped by Category) -->
            <nav class="space-y-4 flex flex-col">
                <!-- Group 1: General -->
                <div class="space-y-1.5">
                    <span class="block px-4 text-[9px] font-bold text-slate-450 uppercase tracking-widest">General</span>
                    <button onclick="switchTab('overview')" id="btn-overview" class="tab-btn active w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-semibold transition border border-transparent text-emerald-400 bg-white/10 text-left">
                        <i class="fa-solid fa-chart-pie w-4"></i> Overview
                    </button>
                </div>

                <!-- Group 2: Curriculum -->
                <div class="space-y-1.5">
                    <span class="block px-4 text-[9px] font-bold text-slate-450 uppercase tracking-widest">Curriculum</span>
                    <button onclick="switchTab('courses')" id="btn-courses" class="tab-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                        <i class="fa-solid fa-graduation-cap w-4"></i> Courses & Classes
                    </button>
                </div>

                <!-- Group 3: Users & Staff -->
                <div class="space-y-1.5">
                    <span class="block px-4 text-[9px] font-bold text-slate-450 uppercase tracking-widest">Directory</span>
                    <button onclick="switchTab('teachers')" id="btn-teachers" class="tab-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                        <i class="fa-solid fa-chalkboard-user w-4"></i> Teachers
                    </button>
                    <button onclick="switchTab('supervisors')" id="btn-supervisors" class="tab-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                        <i class="fa-solid fa-user-tie w-4"></i> Supervisors
                    </button>
                    <button onclick="switchTab('students')" id="btn-students" class="tab-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-semibold transition border border-transparent text-slate-350 hover:text-white text-left">
                        <i class="fa-solid fa-user-graduate w-4"></i> Students
                    </button>
                </div>
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="pt-6 border-t border-white/10 mt-6 space-y-4">
            <!-- User Badge -->
            <div class="flex items-center justify-between bg-white/[0.02] border border-white/5 p-3 rounded-xl">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-emerald-400 to-blue-500 flex items-center justify-center text-xs font-bold text-slate-950 flex-shrink-0 relative">
                        <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-[#0a0f1a]"></span>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-bold text-white truncate"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                        <span class="text-[9px] text-slate-450 uppercase font-semibold tracking-wider"><?= htmlspecialchars($currentUser['role']) ?></span>
                    </div>
                </div>
                <a href="logout.php" class="w-8 h-8 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/15 flex items-center justify-center transition flex-shrink-0" title="Logout">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                </a>
            </div>

            <!-- Home link -->
            <a href="index.php" class="flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-lg bg-white/5 hover:bg-white/10 text-xs font-semibold text-slate-300 hover:text-white transition border border-white/8 w-full">
                <i class="fa-solid fa-arrow-left"></i> Back to Homepage
            </a>
        </div>
    </aside>

    <!-- Main Content Panel -->
    <div class="flex-1 flex flex-col min-w-0 md:ml-72 md:pt-4 md:pr-4 md:pb-4">
        
        <!-- Header -->
        <header class="h-20 border-b border-white/10 flex items-center justify-between px-8 bg-slate-950/20 backdrop-blur-md sticky top-0 z-30">
            <h2 id="current-tab-title" class="text-xl font-bold text-white tracking-wide">Overview</h2>
            <div class="flex items-center gap-2 text-xs text-slate-450 bg-white/5 px-3.5 py-1.5 rounded-full border border-white/8">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Active Portal</span>
            </div>
        </header>

        <!-- Main Scrollable Section -->
        <main class="flex-1 p-6 md:p-8 space-y-8 w-full max-w-7xl mx-auto">

            <!-- Success / Error messages -->
            <?php if (!empty($successMessage)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl text-sm flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                    <span><?= htmlspecialchars($successMessage) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl text-sm flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-lg"></i>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
            <?php endif; ?>

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
                <!-- Controls bar -->
                <div class="flex flex-wrap gap-4 items-center justify-between">
                    <h3 class="text-lg font-bold text-white">Academic Curriculum</h3>
                    <div class="flex gap-3">
                        <button onclick="openCourseModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-white/5 hover:bg-white/10 text-emerald-400 border border-emerald-500/20 rounded-xl transition">
                            <i class="fa-solid fa-folder-plus"></i> Add Course
                        </button>
                        <button onclick="openClassModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
                            <i class="fa-solid fa-circle-plus"></i> Add Class
                        </button>
                    </div>
                </div>

                <div class="space-y-6">
                    <?php foreach ($courses as $course): ?>
                        <div class="glass-panel rounded-2xl p-6 space-y-4">
                            <div class="flex justify-between items-start border-b border-white/10 pb-4">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-md"><?= htmlspecialchars($course['code']) ?></span>
                                        <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($course['name']) ?></h3>
                                    </div>
                                    <p class="text-xs text-slate-450"><?= htmlspecialchars($course['description']) ?></p>
                                </div>
                                
                                <!-- Edit / Delete Course -->
                                <div class="flex items-center gap-2">
                                    <button onclick="openCourseModal(<?= htmlspecialchars(json_encode($course)) ?>)" class="p-2 text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg border border-white/8 transition" title="Edit Course">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button onclick="confirmDelete('course', <?= $course['id'] ?>)" class="p-2 text-xs font-semibold bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded-lg border border-red-500/10 transition" title="Delete Course">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Classes Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php 
                                $courseClasses = $classesByCourse[$course['id']] ?? [];
                                if (empty($courseClasses)): 
                                ?>
                                    <p class="text-sm text-slate-500 py-2 col-span-2">No classes created under this course yet.</p>
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
                                                
                                                <div class="flex gap-2">
                                                    <!-- Edit / Delete Class -->
                                                    <button onclick="openClassModal(<?= htmlspecialchars(json_encode($class)) ?>)" class="p-1.5 text-xs bg-white/5 hover:bg-white/10 text-slate-300 rounded border border-white/8 transition" title="Edit Class">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button onclick="confirmDelete('class', <?= $class['id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Class">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4 text-xs pt-1 border-t border-white/5">
                                                <div>
                                                    <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider">Supervisor</span>
                                                    <span class="text-slate-200 font-medium"><?= htmlspecialchars($class['supervisor_name'] ?? 'Not Assigned') ?></span>
                                                </div>
                                                <div>
                                                    <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider">Enrolled Students</span>
                                                    <span class="text-slate-200 font-medium"><?= count($studentsByClass[$class['id']] ?? []) ?> Students</span>
                                                </div>
                                            </div>

                                            <!-- Teachers Taught Many-to-Many -->
                                            <div>
                                                <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider mb-1.5">Teachers</span>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <?php 
                                                    $classTeachers = $teachersByClass[$class['id']] ?? [];
                                                    if (empty($classTeachers)): 
                                                    ?>
                                                        <span class="text-xs text-slate-550">No teachers assigned</span>
                                                    <?php else: ?>
                                                        <?php foreach ($classTeachers as $teacher): ?>
                                                            <span class="text-xs px-2 py-0.5 rounded bg-white/5 border border-white/10 text-slate-300">
                                                                <?= htmlspecialchars($teacher['teacher_name']) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Students inside Class -->
                                            <div>
                                                <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider mb-2">Student List</span>
                                                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                                    <?php 
                                                    $classStudents = $studentsByClass[$class['id']] ?? [];
                                                    if (empty($classStudents)): 
                                                    ?>
                                                        <p class="text-xs text-slate-550">No students enrolled</p>
                                                    <?php else: ?>
                                                        <?php foreach ($classStudents as $student): ?>
                                                            <div class="flex justify-between items-center bg-white/[0.02] border border-white/5 px-2.5 py-1.5 rounded-lg text-xs">
                                                                <span class="font-medium text-slate-350"><?= htmlspecialchars($student['student_name']) ?></span>
                                                                <span class="text-slate-400 font-mono text-[9px]"><?= htmlspecialchars($student['admission_no']) ?></span>
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
                <div class="flex justify-between items-center">
                    <p class="text-sm text-slate-450">Faculty database and class assignments.</p>
                    <button onclick="openTeacherModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
                        <i class="fa-solid fa-user-plus"></i> Add Teacher
                    </button>
                </div>

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
                                        <p class="text-xs text-slate-400">@<?= htmlspecialchars($teacher['teacher_username']) ?> · <?= htmlspecialchars($teacher['teacher_email'] ?? 'No Email') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="openTeacherModal(<?= htmlspecialchars(json_encode([
                                        'teacher_id' => $teacher['teacher_id'],
                                        'username' => $teacher['teacher_username'],
                                        'full_name' => $teacher['teacher_name'],
                                        'email' => $teacher['teacher_email'],
                                        'phone' => $teacher['teacher_phone'],
                                        'specialisation' => $teacher['specialisation'],
                                        'class_ids' => $classesByTeacher[$teacher['teacher_id']] ?? []
                                    ])) ?>)" class="p-1.5 text-xs bg-white/5 hover:bg-white/10 text-slate-300 rounded border border-white/8 transition" title="Edit Teacher">
                                        <i class="fa-solid fa-user-pen"></i>
                                    </button>
                                    <button onclick="confirmDelete('teacher', <?= $teacher['teacher_id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Teacher">
                                        <i class="fa-solid fa-user-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-slate-450 font-semibold uppercase tracking-wider text-[9px]">Phone:</span>
                                    <span class="text-slate-200"><?= htmlspecialchars($teacher['teacher_phone'] ?? 'N/A') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-450 font-semibold uppercase tracking-wider text-[9px]">Specialisation:</span>
                                    <span class="text-slate-200 text-right"><?= htmlspecialchars($teacher['specialisation'] ?? 'N/A') ?></span>
                                </div>

                                <!-- Classes Taught (Many-to-Many) -->
                                <div class="pt-2">
                                    <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider mb-2">Assigned Classes</span>
                                    <div class="flex flex-wrap gap-2">
                                        <?php 
                                        $taughtClasses = $classesByTeacher[$teacher['teacher_id']] ?? [];
                                        if (empty($taughtClasses)): 
                                        ?>
                                            <span class="text-xs text-slate-550">No classes assigned</span>
                                        <?php else: ?>
                                            <?php foreach ($taughtClasses as $cid): 
                                                // Find class details
                                                $fullClass = array_filter($classes, fn($c) => $c['id'] == $cid);
                                                $fullClass = reset($fullClass);
                                            ?>
                                                <?php if ($fullClass): ?>
                                                    <span class="text-xs px-2.5 py-1 rounded bg-emerald-500/5 border border-emerald-500/15 text-slate-200">
                                                        <i class="fa-solid fa-school text-emerald-400 mr-1 text-[9px]"></i><?= htmlspecialchars($fullClass['name']) ?>
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
                <div class="flex justify-between items-center">
                    <p class="text-sm text-slate-450">Supervisors overseer directory.</p>
                    <button onclick="openSupervisorModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
                        <i class="fa-solid fa-user-plus"></i> Add Supervisor
                    </button>
                </div>

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
                                        <p class="text-xs text-slate-400">@<?= htmlspecialchars($supervisor['supervisor_username']) ?> · <?= htmlspecialchars($supervisor['supervisor_email'] ?? 'No Email') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="openSupervisorModal(<?= htmlspecialchars(json_encode([
                                        'supervisor_id' => $supervisor['supervisor_id'],
                                        'username' => $supervisor['supervisor_username'],
                                        'full_name' => $supervisor['supervisor_name'],
                                        'email' => $supervisor['supervisor_email'],
                                        'phone' => $supervisor['supervisor_phone']
                                    ])) ?>)" class="p-1.5 text-xs bg-white/5 hover:bg-white/10 text-slate-300 rounded border border-white/8 transition" title="Edit Supervisor">
                                        <i class="fa-solid fa-user-pen"></i>
                                    </button>
                                    <button onclick="confirmDelete('supervisor', <?= $supervisor['supervisor_id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Supervisor">
                                        <i class="fa-solid fa-user-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-slate-450 font-semibold uppercase tracking-wider text-[9px]">Phone:</span>
                                    <span class="text-slate-200"><?= htmlspecialchars($supervisor['supervisor_phone'] ?? 'N/A') ?></span>
                                </div>

                                <!-- Classes Supervised -->
                                <div class="pt-2">
                                    <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider mb-2">Overseen Classes</span>
                                    <div class="flex flex-wrap gap-2">
                                        <?php 
                                        $supervisedClasses = $classesBySupervisor[$supervisor['supervisor_id']] ?? [];
                                        if (empty($supervisedClasses)): 
                                        ?>
                                            <span class="text-xs text-slate-550">No classes assigned</span>
                                        <?php else: ?>
                                            <?php foreach ($supervisedClasses as $class): ?>
                                                <span class="text-xs px-2.5 py-1 rounded bg-blue-500/5 border border-blue-500/15 text-slate-200">
                                                    <i class="fa-solid fa-eye text-blue-400 mr-1.5 text-[9px]"></i><?= htmlspecialchars($class['name']) ?>
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
                <div class="flex justify-between items-center">
                    <p class="text-sm text-slate-450">Active student directory list.</p>
                    <button onclick="openStudentModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
                        <i class="fa-solid fa-user-plus"></i> Add Student
                    </button>
                </div>

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
                                    <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-400 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <?php if (empty($studentsRaw)): ?>
                                    <tr>
                                        <td colspan="7" class="p-8 text-center text-slate-500">No students registered yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($studentsRaw as $student): ?>
                                        <tr class="hover:bg-white/[0.02] transition">
                                            <td class="p-4">
                                                <div class="font-semibold text-white"><?= htmlspecialchars($student['student_name']) ?></div>
                                                <div class="text-[10px] text-slate-450">@<?= htmlspecialchars($student['student_username']) ?></div>
                                            </td>
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
                                                    <div><i class="fa-regular fa-envelope text-[10px] mr-1"></i><?= htmlspecialchars($student['student_email'] ?? 'N/A') ?></div>
                                                    <div><i class="fa-solid fa-phone text-[10px] mr-1"></i><?= htmlspecialchars($student['student_phone'] ?? 'N/A') ?></div>
                                                </div>
                                            </td>
                                            <td class="p-4 text-right">
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
                                                        <i class="fa-solid fa-user-pen"></i>
                                                    </button>
                                                    <button onclick="confirmDelete('student', <?= $student['user_id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Student">
                                                        <i class="fa-solid fa-user-xmark"></i>
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
            </section>

        </main>
    </div>

    <!-- ================= MODALS ================= -->
    
    <!-- 1. Course Modal -->
    <div id="modal-course" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
        <div class="w-full max-w-lg glass-panel rounded-3xl p-6 relative space-y-4">
            <div class="flex justify-between items-center border-b border-white/10 pb-3">
                <h4 id="course-modal-title" class="text-lg font-bold text-white">Add New Course</h4>
                <button onclick="closeModal('course')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form action="coordinator.php?tab=courses" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_course">
                <input type="hidden" name="id" id="course_id">
                
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2 space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Course Name</label>
                        <input type="text" name="name" id="course_name" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="e.g. Hifzul Quran" required>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Course Code</label>
                        <input type="text" name="code" id="course_code" class="glass-input w-full px-3 py-2 rounded-xl text-sm font-semibold uppercase" placeholder="e.g. HIFZ" required>
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Description</label>
                    <textarea name="description" id="course_description" rows="3" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Course description..."></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('course')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs">Save Course</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Class Modal -->
    <div id="modal-class" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
        <div class="w-full max-w-lg glass-panel rounded-3xl p-6 relative space-y-4">
            <div class="flex justify-between items-center border-b border-white/10 pb-3">
                <h4 id="class-modal-title" class="text-lg font-bold text-white">Add New Class</h4>
                <button onclick="closeModal('class')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form action="coordinator.php?tab=courses" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_class">
                <input type="hidden" name="id" id="class_id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Class Name</label>
                        <input type="text" name="name" id="class_name" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="e.g. Class A" required>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Class Type</label>
                        <select name="type" id="class_type" class="glass-input w-full px-3 py-2 rounded-xl text-sm bg-slate-900">
                            <option value="regular">Regular Class</option>
                            <option value="individual">Individual Class</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Under Course</label>
                        <select name="course_id" id="class_course_id" class="glass-input w-full px-3 py-2 rounded-xl text-sm bg-slate-900" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Supervisor Overseer</label>
                        <select name="supervisor_id" id="class_supervisor_id" class="glass-input w-full px-3 py-2 rounded-xl text-sm bg-slate-900">
                            <option value="">No Supervisor</option>
                            <?php foreach ($supervisorsRaw as $s): ?>
                                <option value="<?= $s['supervisor_id'] ?>"><?= htmlspecialchars($s['supervisor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('class')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs">Save Class</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Teacher Modal -->
    <div id="modal-teacher" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
        <div class="w-full max-w-xl glass-panel rounded-3xl p-6 relative space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-white/10 pb-3">
                <h4 id="teacher-modal-title" class="text-lg font-bold text-white">Add New Teacher</h4>
                <button onclick="closeModal('teacher')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form action="coordinator.php?tab=teachers" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_teacher">
                <input type="hidden" name="teacher_id" id="teacher_id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Username</label>
                        <input type="text" name="username" id="teacher_username" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Username" required>
                    </div>
                    <div class="space-y-1">
                        <label id="lbl-teacher-password" class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Password</label>
                        <input type="password" name="password" id="teacher_password" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Password">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Full Name</label>
                        <input type="text" name="full_name" id="teacher_fullname" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Full name" required>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Specialisation</label>
                        <input type="text" name="specialisation" id="teacher_specialisation" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="e.g. Tajweed">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Gmail / Email</label>
                        <input type="email" name="email" id="teacher_email" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="email@gmail.com">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Mobile Number</label>
                        <input type="text" name="phone" id="teacher_phone" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Phone number">
                    </div>
                </div>

                <!-- Assigned Classes (Checkboxes) -->
                <div class="space-y-1.5 pt-1">
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Assign Classes</label>
                    <div class="grid grid-cols-2 gap-2 max-h-32 overflow-y-auto bg-white/[0.02] border border-white/8 p-3 rounded-xl">
                        <?php foreach ($classes as $c): ?>
                            <label class="flex items-center gap-2 text-xs text-slate-300 hover:text-white cursor-pointer select-none">
                                <input type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" class="teacher-class-checkbox w-4 h-4 rounded border-slate-700 bg-slate-900 text-emerald-500 focus:ring-emerald-500 focus:ring-opacity-25 focus:ring-2">
                                <span><?= htmlspecialchars($c['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-2 border-t border-white/10">
                    <button type="button" onclick="closeModal('teacher')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs">Save Teacher</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Supervisor Modal -->
    <div id="modal-supervisor" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
        <div class="w-full max-w-lg glass-panel rounded-3xl p-6 relative space-y-4">
            <div class="flex justify-between items-center border-b border-white/10 pb-3">
                <h4 id="supervisor-modal-title" class="text-lg font-bold text-white">Add New Supervisor</h4>
                <button onclick="closeModal('supervisor')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form action="coordinator.php?tab=supervisors" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_supervisor">
                <input type="hidden" name="supervisor_id" id="supervisor_id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Username</label>
                        <input type="text" name="username" id="supervisor_username" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Username" required>
                    </div>
                    <div class="space-y-1">
                        <label id="lbl-supervisor-password" class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Password</label>
                        <input type="password" name="password" id="supervisor_password" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Password">
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Full Name</label>
                    <input type="text" name="full_name" id="supervisor_fullname" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Full name" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Gmail / Email</label>
                        <input type="email" name="email" id="supervisor_email" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="email@gmail.com">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Mobile Number</label>
                        <input type="text" name="phone" id="supervisor_phone" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Phone number">
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('supervisor')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs">Save Supervisor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. Student Modal -->
    <div id="modal-student" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
        <div class="w-full max-w-xl glass-panel rounded-3xl p-6 relative space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-white/10 pb-3">
                <h4 id="student-modal-title" class="text-lg font-bold text-white">Add New Student</h4>
                <button onclick="closeModal('student')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form action="coordinator.php?tab=students" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_student">
                <input type="hidden" name="student_id" id="student_id">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Username</label>
                        <input type="text" name="username" id="student_username" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Username" required>
                    </div>
                    <div class="space-y-1">
                        <label id="lbl-student-password" class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Password</label>
                        <input type="password" name="password" id="student_password" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Password">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Full Name</label>
                        <input type="text" name="full_name" id="student_fullname" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Full name" required>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Admission No</label>
                        <input type="text" name="admission_no" id="student_adm" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="e.g. ADM-2026-001" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Parent / Guardian</label>
                        <input type="text" name="parent_name" id="student_parent" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Guardian name" required>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Date of Birth</label>
                        <input type="date" name="dob" id="student_dob" class="glass-input w-full px-3 py-2 rounded-xl text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2 space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Gmail / Email</label>
                        <input type="email" name="email" id="student_email" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="student@gmail.com">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider font-semibold">Mobile Number</label>
                        <input type="text" name="phone" id="student_phone" class="glass-input w-full px-3 py-2 rounded-xl text-sm" placeholder="Phone">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider font-semibold">Assign to Class</label>
                    <select name="class_id" id="student_class_id" class="glass-input w-full px-3 py-2 rounded-xl text-sm bg-slate-900">
                        <option value="">Unassigned</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3 pt-2 border-t border-white/10">
                    <button type="button" onclick="closeModal('student')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs">Save Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 6. Delete Confirmation Modal -->
    <div id="modal-delete" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
        <div class="w-full max-w-sm glass-panel rounded-3xl p-6 relative space-y-4 text-center">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-4xl"></i>
            <h4 class="text-lg font-bold text-white">Confirm Deletion</h4>
            <p class="text-xs text-slate-400">Are you absolutely sure you want to delete this record? This action cannot be undone.</p>
            
            <form action="coordinator.php" method="POST" class="flex justify-center gap-3 pt-2">
                <input type="hidden" name="action" value="delete_entity">
                <input type="hidden" name="entity_type" id="del_entity_type">
                <input type="hidden" name="id" id="del_entity_id">
                <input type="hidden" name="tab_origin" id="del_tab_origin">
                
                <button type="button" onclick="closeModal('delete')" class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-500/25 border border-red-500/35 hover:bg-red-500/40 text-red-400 font-bold text-xs transition">Delete</button>
            </form>
        </div>
    </div>

    <!-- Tab switcher JS & Modal Helpers -->
    <script>
        // Check URL tab parameter on page load
        window.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || 'overview';
            switchTab(tab);
        });

        // Mobile Sidebar Drawer Toggle helper
        function toggleMobileSidebar(isOpen) {
            const sidebar = document.getElementById('sidebar-drawer');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (isOpen) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => {
                    overlay.classList.remove('opacity-0');
                    overlay.classList.add('opacity-100');
                    overlay.classList.add('pointer-events-auto');
                }, 10);
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0');
                overlay.classList.remove('pointer-events-auto');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 300);
            }
        }

        function switchTab(tabId) {
            // Close mobile sidebar drawer automatically on tab switch
            toggleMobileSidebar(false);

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
            if (activeBtn) {
                activeBtn.classList.add('active');
                activeBtn.classList.add('bg-white/10');
                activeBtn.classList.add('text-emerald-400');
                activeBtn.classList.remove('text-slate-350');
            }

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

        // --- Modal Managers ---
        function closeModal(type) {
            document.getElementById('modal-' + type).classList.add('hidden');
        }

        // Course Modal
        function openCourseModal(data = null) {
            const modal = document.getElementById('modal-course');
            const title = document.getElementById('course-modal-title');
            const form = modal.querySelector('form');
            
            // Reset
            form.reset();
            document.getElementById('course_id').value = '';

            if (data) {
                title.innerText = 'Edit Course';
                document.getElementById('course_id').value = data.id;
                document.getElementById('course_name').value = data.name;
                document.getElementById('course_code').value = data.code;
                document.getElementById('course_description').value = data.description;
            } else {
                title.innerText = 'Add New Course';
            }
            modal.classList.remove('hidden');
        }

        // Class Modal
        function openClassModal(data = null) {
            const modal = document.getElementById('modal-class');
            const title = document.getElementById('class-modal-title');
            const form = modal.querySelector('form');
            
            form.reset();
            document.getElementById('class_id').value = '';

            if (data) {
                title.innerText = 'Edit Class';
                document.getElementById('class_id').value = data.id;
                document.getElementById('class_name').value = data.name;
                document.getElementById('class_type').value = data.type;
                document.getElementById('class_course_id').value = data.course_id;
                document.getElementById('class_supervisor_id').value = data.supervisor_id || '';
            } else {
                title.innerText = 'Add New Class';
            }
            modal.classList.remove('hidden');
        }

        // Teacher Modal
        function openTeacherModal(data = null) {
            const modal = document.getElementById('modal-teacher');
            const title = document.getElementById('teacher-modal-title');
            const form = modal.querySelector('form');
            const passwordLabel = document.getElementById('lbl-teacher-password');
            const passwordInput = document.getElementById('teacher_password');
            const usernameInput = document.getElementById('teacher_username');
            
            form.reset();
            document.getElementById('teacher_id').value = '';
            
            // Uncheck all class checkboxes
            document.querySelectorAll('.teacher-class-checkbox').forEach(cb => cb.checked = false);

            if (data) {
                title.innerText = 'Edit Teacher';
                document.getElementById('teacher_id').value = data.teacher_id;
                usernameInput.value = data.username;
                document.getElementById('teacher_fullname').value = data.full_name;
                document.getElementById('teacher_email').value = data.email || '';
                document.getElementById('teacher_phone').value = data.phone || '';
                document.getElementById('teacher_specialisation').value = data.specialisation || '';
                
                // Check mapped classes
                if (data.class_ids) {
                    document.querySelectorAll('.teacher-class-checkbox').forEach(cb => {
                        if (data.class_ids.includes(parseInt(cb.value))) {
                            cb.checked = true;
                        }
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

        // Supervisor Modal
        function openSupervisorModal(data = null) {
            const modal = document.getElementById('modal-supervisor');
            const title = document.getElementById('supervisor-modal-title');
            const form = modal.querySelector('form');
            const passwordLabel = document.getElementById('lbl-supervisor-password');
            const passwordInput = document.getElementById('supervisor_password');
            const usernameInput = document.getElementById('supervisor_username');
            
            form.reset();
            document.getElementById('supervisor_id').value = '';

            if (data) {
                title.innerText = 'Edit Supervisor';
                document.getElementById('supervisor_id').value = data.supervisor_id;
                usernameInput.value = data.username;
                document.getElementById('supervisor_fullname').value = data.full_name;
                document.getElementById('supervisor_email').value = data.email || '';
                document.getElementById('supervisor_phone').value = data.phone || '';

                passwordLabel.innerText = 'New Password (leave blank to keep)';
                passwordInput.removeAttribute('required');
            } else {
                title.innerText = 'Add New Supervisor';
                passwordLabel.innerText = 'Password';
                passwordInput.setAttribute('required', 'required');
            }
            modal.classList.remove('hidden');
        }

        // Student Modal
        function openStudentModal(data = null) {
            const modal = document.getElementById('modal-student');
            const title = document.getElementById('student-modal-title');
            const form = modal.querySelector('form');
            const passwordLabel = document.getElementById('lbl-student-password');
            const passwordInput = document.getElementById('student_password');
            const usernameInput = document.getElementById('student_username');
            
            form.reset();
            document.getElementById('student_id').value = '';

            if (data) {
                title.innerText = 'Edit Student';
                document.getElementById('student_id').value = data.student_id;
                usernameInput.value = data.username;
                document.getElementById('student_fullname').value = data.full_name;
                document.getElementById('student_adm').value = data.admission_no;
                document.getElementById('student_parent').value = data.parent_name;
                document.getElementById('student_dob').value = data.dob || '';
                document.getElementById('student_email').value = data.email || '';
                document.getElementById('student_phone').value = data.phone || '';
                document.getElementById('student_class_id').value = data.class_id || '';

                passwordLabel.innerText = 'New Password (leave blank to keep)';
                passwordInput.removeAttribute('required');
            } else {
                title.innerText = 'Add New Student';
                passwordLabel.innerText = 'Password';
                passwordInput.setAttribute('required', 'required');
            }
            modal.classList.remove('hidden');
        }

        // Delete Confirmation Modal
        function confirmDelete(entityType, id) {
            const modal = document.getElementById('modal-delete');
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || 'overview';
            
            document.getElementById('del_entity_type').value = entityType;
            document.getElementById('del_entity_id').value = id;
            document.getElementById('del_tab_origin').value = tab;
            
            modal.classList.remove('hidden');
        }
    </script>
</body>
</html>
