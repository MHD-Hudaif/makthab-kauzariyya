<?php
require_once 'includes/db.php';

$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalSupervisors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maktab Kauzariyya — The Munazzam Path to Islamic Education</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20260821-footer">
</head>
<body class="home-page relative min-h-screen overflow-x-hidden" style="color:#ecf3d6;">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 z-[-3]" style="background: linear-gradient(135deg, #0e2e38 0%, #123b47 50%, #0e2e38 100%);"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <div class="absolute top-[10%] left-[5%] w-[45vw] h-[45vw] max-w-[600px] max-h-[600px] blur-[100px] animate-blob-1" style="background:rgba(109,204,141,0.12);"></div>
        <div class="absolute bottom-[15%] right-[5%] w-[50vw] h-[50vw] max-w-[700px] max-h-[700px] blur-[120px] animate-blob-2" style="background:rgba(65,174,189,0.1);"></div>
        <div class="absolute top-[40%] left-[30%] w-[35vw] h-[35vw] max-w-[500px] max-h-[500px] blur-[100px] animate-blob-3" style="background:rgba(167,235,243,0.06);"></div>
    </div>

    <!-- Navigation Bar -->
    <nav class="glass-navbar sticky top-0 z-50 w-full">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full overflow-hidden border flex items-center justify-center p-1" style="background:#123b47; border-color:rgba(109,204,141,0.25); box-shadow:0 0 12px rgba(109,204,141,0.1);">
                    <img src="assets/images/logo-mark-dark.png" alt="Maktab Kauzariyya Logo" class="w-full h-full object-contain">
                </div>
                <div class="flex flex-col">
                    <span class="text-lg font-bold tracking-wide uppercase brand-text-gradient">Maktab Kauzariyya</span>
                    <span class="text-[10px] tracking-widest uppercase" style="color:rgba(167,235,243,0.45);">Al Jamiathul Kauzariyya</span>
                </div>
            </div>
            
            <div class="hidden md:flex items-center gap-8">
                <a href="#home" class="text-sm font-medium transition" style="color:rgba(236,243,214,0.7);">Home</a>
                <a href="coordinator/index" class="text-sm font-semibold transition" style="color:rgba(109,204,141,0.8);"><i class="fa-solid fa-chart-pie mr-1 text-[11px]"></i>Panel</a>
                <a href="#about" class="text-sm font-medium transition" style="color:rgba(236,243,214,0.7);">About</a>
                <a href="#courses" class="text-sm font-medium transition" style="color:rgba(236,243,214,0.7);">Courses</a>
                <a href="#socials" class="text-sm font-medium transition" style="color:rgba(236,243,214,0.7);">Socials</a>
            </div>

            <!-- Desktop Action Buttons -->
            <div class="hidden md:flex items-center gap-4">
                <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="w-10 h-10 rounded-full glass-card flex items-center justify-center transition" style="color:rgba(236,243,214,0.7);" title="Follow us on Instagram">
                    <i class="fa-brands fa-instagram text-lg"></i>
                </a>
                <a href="#contact" class="brand-gradient px-5 py-2 rounded-full font-semibold transition duration-300" style="color:#0e2e38; box-shadow:0 4px 16px rgba(109,204,141,0.2);">
                    Admission
                </a>
            </div>

            <!-- Mobile Hamburger Toggle Trigger -->
            <button onclick="toggleMobileMenu()" class="flex md:hidden w-10 h-10 items-center justify-center rounded-xl border transition" style="background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.15);" title="Toggle Navigation Menu">
                <i id="hamburger-icon" class="fa-solid fa-bars text-lg" style="color:#ecf3d6;"></i>
            </button>
        </div>

        <!-- Mobile Responsive Dropdown Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t backdrop-blur-md transition-all duration-300 w-full px-6 py-4 space-y-3" style="background:rgba(14,46,56,0.97); border-color:rgba(109,204,141,0.12);">
            <a href="#home" onclick="toggleMobileMenu()" class="block text-sm font-medium py-2 transition" style="color:rgba(236,243,214,0.7);">Home</a>
            <a href="coordinator/index" onclick="toggleMobileMenu()" class="block text-sm font-semibold py-2 transition" style="color:#6dcc8d;"><i class="fa-solid fa-chart-pie mr-1 text-[11px]"></i>Panel</a>
            <a href="#about" onclick="toggleMobileMenu()" class="block text-sm font-medium py-2 transition" style="color:rgba(236,243,214,0.7);">About</a>
            <a href="#courses" onclick="toggleMobileMenu()" class="block text-sm font-medium py-2 transition" style="color:rgba(236,243,214,0.7);">Courses</a>
            <a href="#socials" onclick="toggleMobileMenu()" class="block text-sm font-medium py-2 transition" style="color:rgba(236,243,214,0.7);">Socials</a>
            <div class="pt-3 border-t flex gap-4" style="border-color:rgba(109,204,141,0.12);">
                <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="w-10 h-10 rounded-full glass-card flex items-center justify-center transition" title="Follow us on Instagram">
                    <i class="fa-brands fa-instagram text-lg"></i>
                </a>
                <a href="#contact" onclick="toggleMobileMenu()" class="brand-gradient px-5 py-2.5 rounded-full font-semibold transition duration-300 text-center flex-1" style="color:#0e2e38;">
                    Admission
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-6 py-12 space-y-24">

        <!-- Hero Section inside a grand Liquid Glass Panel -->
        <section id="home" class="home-hero relative glass-panel rounded-3xl p-8 md:p-16 overflow-hidden grid grid-cols-1 lg:grid-cols-[1.1fr_0.9fr] gap-10 items-center">
            <!-- Glass gloss streak -->
            <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/[0.02] to-transparent pointer-events-none"></div>

            <div class="max-w-3xl space-y-6 relative z-10">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border text-xs font-semibold uppercase tracking-wider" style="background:rgba(109,204,141,0.08); border-color:rgba(109,204,141,0.25); color:#6dcc8d;">
                    <span class="w-2 h-2 rounded-full animate-ping" style="background:#6dcc8d;"></span> Established 1974
                </span>

                <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight leading-tight" style="color:#ecf3d6;">
                    The Munazzam Path to<br>
                    <span class="brand-text-gradient">Islamic Excellence</span>
                </h1>

                <p class="text-base md:text-lg leading-relaxed max-w-2xl mx-auto" style="color:rgba(236,243,214,0.65);">
                    Welcome to Maktab Kauzariyya — مكتب كوثرية. We are committed to fostering deep academic learning and spiritual enlightenment to build leaders for tomorrow.
                </p>

                <div class="pt-4 flex flex-col sm:flex-row justify-center gap-4">
                    <a href="#courses" class="glass-highlight relative overflow-hidden brand-gradient px-8 py-4 rounded-xl font-bold transition" style="color:#0e2e38; box-shadow:0 8px 24px rgba(109,204,141,0.2);">
                        Explore Our Courses
                    </a>
                    <a href="https://www.instagram.com/reel/DYVA7pMjBm9/" target="_blank" class="glass-card flex items-center justify-center gap-2 px-8 py-4 rounded-xl font-bold transition" style="color:#ecf3d6;">
                        <i class="fa-solid fa-play" style="color:#6dcc8d;"></i> Watch Our Introduction
                    </a>
                </div>
            </div>

            <div class="relative z-10 hidden lg:flex justify-center items-center">
                <div class="relative w-full max-w-sm aspect-[4/5] overflow-hidden rounded-[2rem] border border-white/15 shadow-2xl shadow-black/30">
                    <img src="assets/images/hero-quran-student-clean.jpg" alt="Student studying the Quran" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#09272e]/80 via-transparent to-transparent"></div>
                    <div class="home-hero-mark absolute bottom-6 left-6 right-6 rounded-2xl p-4 flex items-center gap-3">
                        <img src="assets/images/logo-dark.png" alt="Maktab Kauzariyya" class="w-12 h-12 object-contain">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] font-bold" style="color:#0e2e38;">Maktab Kauzariyya</p>
                            <p class="text-xs mt-1" style="color:#3e6d6c;">The Munazzam Path</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
            <!-- Stat 1 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl brand-gradient flex items-center justify-center" style="color:#0e2e38;">
                    <i class="fa-solid fa-user-graduate text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold" style="color:#ecf3d6;"><?= $totalStudents ?>+</span>
                    <span class="block text-xs md:text-sm font-medium uppercase tracking-wider" style="color:rgba(167,235,243,0.5);">Students</span>
                </div>
            </div>

            <!-- Stat 2 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#41aebd,rgba(62,109,108,0.4)); color:#0e2e38;">
                    <i class="fa-solid fa-chalkboard-user text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold" style="color:#ecf3d6;"><?= $totalTeachers ?>+</span>
                    <span class="block text-xs md:text-sm font-medium uppercase tracking-wider" style="color:rgba(167,235,243,0.5);">Teachers</span>
                </div>
            </div>

            <!-- Stat 3 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#a7ebf3,rgba(65,174,189,0.3)); color:#0e2e38;">
                    <i class="fa-solid fa-book-open text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold" style="color:#ecf3d6;"><?= $totalCourses ?>+</span>
                    <span class="block text-xs md:text-sm font-medium uppercase tracking-wider" style="color:rgba(167,235,243,0.5);">Courses</span>
                </div>
            </div>

            <!-- Stat 4 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#6dcc8d,rgba(62,109,108,0.5)); color:#0e2e38;">
                    <i class="fa-solid fa-user-tie text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold" style="color:#ecf3d6;"><?= $totalSupervisors ?>+</span>
                    <span class="block text-xs md:text-sm font-medium uppercase tracking-wider" style="color:rgba(167,235,243,0.5);">Supervisors</span>
                </div>
            </div>
        </section>

        <!-- Course Cards Section -->
        <section id="courses" class="space-y-12">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight" style="color:#ecf3d6;">Our Specialties</h2>
                <p style="color:rgba(236,243,214,0.55);">Explore the academic and religious programs offered at Maktab Kauzariyya.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Course 1 -->
                <div class="glass-card rounded-2xl p-8 flex flex-col justify-between h-80 relative overflow-hidden">
                    <div class="space-y-4">
                        <div class="w-12 h-12 rounded-xl brand-gradient flex items-center justify-center" style="color:#0e2e38;">
                            <i class="fa-solid fa-book-quran text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold" style="color:#ecf3d6;">Hifzul Quran</h3>
                        <p class="text-sm" style="color:rgba(236,243,214,0.6);">Comprehensive Quran memorization program with tajweed standards under qualified scholars.</p>
                    </div>
                    <a href="#" class="text-sm font-semibold flex items-center gap-1 transition group" style="color:#6dcc8d;">
                        Learn More <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition"></i>
                    </a>
                </div>

                <!-- Course 2 -->
                <div class="glass-card rounded-2xl p-8 flex flex-col justify-between h-80 relative overflow-hidden">
                    <div class="space-y-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#41aebd,rgba(62,109,108,0.5)); color:#0e2e38;">
                            <i class="fa-solid fa-mosque text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold" style="color:#ecf3d6;">Shariah Course</h3>
                        <p class="text-sm" style="color:rgba(236,243,214,0.6);">A comprehensive Islamic jurisprudence and theology curriculum matching modern developments.</p>
                    </div>
                    <a href="#" class="text-sm font-semibold flex items-center gap-1 transition group" style="color:#41aebd;">
                        Learn More <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition"></i>
                    </a>
                </div>

                <!-- Course 3 -->
                <div class="glass-card rounded-2xl p-8 flex flex-col justify-between h-80 relative overflow-hidden">
                    <div class="space-y-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#a7ebf3,rgba(65,174,189,0.4)); color:#0e2e38;">
                            <i class="fa-solid fa-award text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold" style="color:#ecf3d6;">Specializations</h3>
                        <p class="text-sm" style="color:rgba(236,243,214,0.6);">Post-graduate programs specializing in Fiqh (Islamic Law) and Qirath (recitation variations).</p>
                    </div>
                    <a href="#" class="text-sm font-semibold flex items-center gap-1 transition group" style="color:#a7ebf3;">
                        Learn More <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Social Media Highlight Section -->
        <section id="socials" class="glass-panel rounded-3xl p-8 md:p-12 relative overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 text-pink-400 bg-pink-500/10 px-4 py-1.5 rounded-full border border-pink-500/25 text-xs font-semibold uppercase tracking-wider">
                        <i class="fa-brands fa-instagram"></i> Social Connect
                    </div>
                    <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight">Follow Our Journey On Instagram</h2>
                    <p class="text-slate-300 leading-relaxed">
                        Stay connected with daily updates, campus news, events, spiritual quotes, and academic announcements. Watch our featured video directly on Instagram to know more about our mission.
                    </p>
                    <div class="flex flex-wrap gap-4 pt-2">
                        <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="bg-gradient-to-r from-pink-500 to-rose-500 text-white px-6 py-3.5 rounded-xl font-bold hover:shadow-lg hover:shadow-pink-500/20 transition flex items-center gap-2">
                            <i class="fa-brands fa-instagram text-lg"></i> Follow @kauzariyya
                        </a>
                        <a href="https://www.instagram.com/reel/DYVA7pMjBm9/" target="_blank" class="glass-card text-white px-6 py-3.5 rounded-xl font-semibold hover:bg-white/10 transition flex items-center gap-2">
                            <i class="fa-solid fa-circle-play text-pink-400"></i> Watch Featured Reel
                        </a>
                    </div>
                </div>

                <!-- Stylized Mockup/Teaser using Glassmorphism -->
                <div class="relative flex justify-center items-center">
                    <div class="absolute w-72 h-72 bg-gradient-to-tr from-pink-500 to-rose-400 rounded-full blur-[60px] opacity-30 z-[-1] animate-pulse"></div>
                    <div class="glass-card w-full max-w-sm rounded-2xl p-6 border border-white/15 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-yellow-400 via-pink-500 to-purple-600 flex items-center justify-center p-[2px]">
                                    <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-xs text-white">K</div>
                                </div>
                                <span class="text-xs font-bold">kauzariyya</span>
                            </div>
                            <i class="fa-solid fa-ellipsis text-slate-400"></i>
                        </div>
                        
                        <!-- Simulated Image placeholder with Glass gradient -->
                        <div class="aspect-video w-full rounded-xl border flex items-center justify-center relative overflow-hidden group" style="background:linear-gradient(135deg,#123b47,#0e2e38); border-color:rgba(109,204,141,0.12);">
                            <div class="absolute inset-0 bg-contain bg-no-repeat bg-center" style="background-image: url('assets/images/logo-mark-dark.png'); opacity: 0.7;"></div>
                            <a href="https://www.instagram.com/reel/DYVA7pMjBm9/" target="_blank" class="w-12 h-12 rounded-full bg-white/10 backdrop-blur-md flex items-center justify-center text-white border border-white/20 hover:scale-110 hover:bg-white/20 transition z-10">
                                <i class="fa-solid fa-play text-xl translate-x-[2px]"></i>
                            </a>
                        </div>
                        
                        <div class="flex justify-between items-center text-sm pt-2">
                            <div class="flex gap-4">
                                <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="hover:text-pink-400 transition"><i class="fa-regular fa-heart text-base"></i></a>
                                <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="hover:text-pink-400 transition"><i class="fa-regular fa-comment text-base"></i></a>
                                <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="hover:text-pink-400 transition"><i class="fa-regular fa-paper-plane text-base"></i></a>
                            </div>
                            <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="hover:text-pink-400 transition"><i class="fa-regular fa-bookmark text-base"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer id="contact" class="site-footer glass-navbar mt-24 px-6 py-16 md:py-20">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-[1.15fr_1fr_0.75fr] gap-12 lg:gap-20">
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl overflow-hidden border flex items-center justify-center p-1.5" style="background:#123b47; border-color:rgba(109,204,141,0.25);">
                        <img src="assets/images/logo-mark-dark.png" alt="Maktab Kauzariyya logo" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="text-lg font-bold" style="color:#ecf3d6;">Maktab Kauzariyya</p>
                        <p class="text-xs uppercase tracking-[0.16em]" style="color:rgba(167,235,243,0.55);">مكتب كوثرية</p>
                    </div>
                </div>
                <p class="max-w-md text-sm leading-7" style="color:rgba(236,243,214,0.6);">A Darul Uloom committed to nurturing sound Islamic scholarship, spiritual character, and service to humanity.</p>
                <p class="text-xs" style="color:rgba(167,235,243,0.42);">&copy; 2026 Al Jamiathul Kauzariyya. All rights reserved.</p>
            </div>

            <div>
                <h2 class="text-xl font-bold mb-6" style="color:#ecf3d6;">Links</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                    <a href="https://www.facebook.com/Kauzariyya" target="_blank" rel="noopener noreferrer" class="footer-link"><i class="fa-brands fa-facebook"></i><span>Facebook</span></a>
                    <a href="https://www.instagram.com/kauzariyya/" target="_blank" rel="noopener noreferrer" class="footer-link"><i class="fa-brands fa-instagram"></i><span>Instagram</span></a>
                    <a href="https://shopee.kauzariyya.com" target="_blank" rel="noopener noreferrer" class="footer-link"><i class="fa-solid fa-globe"></i><span>Kauzariyya Shopee</span></a>
                    <a href="https://twitter.com/kauzariyya" target="_blank" rel="noopener noreferrer" class="footer-link"><i class="fa-brands fa-x-twitter"></i><span>Twitter</span></a>
                    <a href="https://fatwa.kauzariyya.com" target="_blank" rel="noopener noreferrer" class="footer-link"><i class="fa-solid fa-globe"></i><span>Darul Ifta</span></a>
                    <a href="https://www.youtube.com/c/AlJamiathulKauzariyya" target="_blank" rel="noopener noreferrer" class="footer-link"><i class="fa-brands fa-youtube"></i><span>Our YouTube Channel</span></a>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold mb-6" style="color:#ecf3d6;">More info</h2>
                <div class="space-y-5 text-sm" style="color:rgba(236,243,214,0.62);">
                    <a href="mailto:info@kauzariyya.com" class="footer-link"><i class="fa-regular fa-envelope"></i><span>View email address</span></a>
                    <a href="https://www.youtube.com/@Kauzariyya" target="_blank" rel="noopener noreferrer" class="footer-link"><i class="fa-brands fa-youtube"></i><span>youtube.com/@Kauzariyya</span></a>
                    <div class="footer-link"><i class="fa-solid fa-globe"></i><span>India</span></div>
                    <div class="footer-link"><i class="fa-solid fa-circle-info"></i><span>Joined Jul 15, 2018</span></div>
                </div>
            </div>
        </div>
    </footer>
    <!-- Mobile Navigation Toggle Script -->
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const icon = document.getElementById('hamburger-icon');
            
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-xmark');
            } else {
                menu.classList.add('hidden');
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-bars');
            }
        }
    </script>

</body>
</html>
