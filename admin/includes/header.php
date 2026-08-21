<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Authentication Check: Only allow admins
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ' . base_url('login'));
    exit;
}

$currentUser = $_SESSION['user'];
$successMessage = $_SESSION['msg_success'] ?? '';
$errorMessage = $_SESSION['msg_error'] ?? '';
unset($_SESSION['msg_success'], $_SESSION['msg_error']);

// Fetch common counts for sidebar/overview panels
$totalCoordinators = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'coordinator'")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalSupervisors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Detect active page to determine document title
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = 'Admin Dashboard';
if ($currentPage === 'coordinators.php') {
    $pageTitle = 'Coordinators Registry';
} elseif ($currentPage === 'supervisors.php') {
    $pageTitle = 'Supervisors Registry';
} elseif ($currentPage === 'teachers.php') {
    $pageTitle = 'Teachers Registry';
} elseif ($currentPage === 'students.php') {
    $pageTitle = 'Student Directory';
} elseif ($currentPage === 'courses.php') {
    $pageTitle = 'Courses & Classes';
} elseif ($currentPage === 'audits.php') {
    $pageTitle = 'Class Audits';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kauzariyya Admin - <?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=20260821-icons">
</head>
<body class="relative min-h-screen text-slate-100 overflow-x-hidden flex flex-col md:flex-row" style="background-color:#0e2e38; color:#ecf3d6;">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 z-[-3]" style="background: linear-gradient(135deg, #0e2e38 0%, #123b47 50%, #0e2e38 100%);"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[5%] left-[20%] w-[35vw] h-[35vw] max-w-[500px] blur-[100px] animate-blob-1" style="background:rgba(109,204,141,0.08);"></div>
        <div class="absolute bottom-[10%] right-[20%] w-[40vw] h-[40vw] max-w-[600px] blur-[120px] animate-blob-2" style="background:rgba(65,174,189,0.07);"></div>
    </div>

    <!-- Mobile Top Navigation Header -->
    <header class="flex md:hidden items-center justify-between px-6 h-16 border-b sticky top-0 z-50 backdrop-blur-md w-full" style="background:rgba(14,46,56,0.95); border-color:rgba(109,204,141,0.12);">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full overflow-hidden border flex items-center justify-center p-0.5" style="background:#123b47; border-color:rgba(109,204,141,0.2);">
                <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <span class="text-sm font-bold tracking-wide uppercase brand-text-gradient">Administrator</span>
        </div>
        <button onclick="toggleMobileSidebar(true)" class="w-10 h-10 flex items-center justify-center rounded-xl border transition" style="background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.15);" title="Open Sidebar Menu">
            <i class="fa-solid fa-bars text-lg" style="color:#ecf3d6;"></i>
        </button>
    </header>

    <!-- Mobile Sidebar Dark Overlay Backdrop -->
    <div id="sidebar-overlay" onclick="toggleMobileSidebar(false)" class="fixed inset-0 backdrop-blur-sm z-40 hidden md:hidden transition-opacity duration-300 opacity-0 pointer-events-none" style="background:rgba(14,46,56,0.7);"></div>
