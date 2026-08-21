<?php
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Al Jamiathul Kauzariyya - Liquid Glass Theme</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Liquid Blob Animations */
        @keyframes morph-blob-1 {
            0%, 100% { border-radius: 42% 58% 70% 30% / 45% 45% 55% 55%; transform: translate(0, 0) scale(1); }
            33% { border-radius: 70% 30% 52% 48% / 60% 40% 60% 40%; transform: translate(100px, 80px) scale(1.2); }
            66% { border-radius: 50% 50% 30% 70% / 50% 60% 40% 50%; transform: translate(-50px, 120px) scale(0.85); }
        }
        @keyframes morph-blob-2 {
            0%, 100% { border-radius: 70% 30% 52% 48% / 60% 40% 60% 40%; transform: translate(0, 0) scale(1); }
            50% { border-radius: 42% 58% 70% 30% / 45% 45% 55% 55%; transform: translate(-120px, -80px) scale(1.15); }
        }
        @keyframes morph-blob-3 {
            0%, 100% { border-radius: 50% 50% 30% 70% / 50% 60% 40% 50%; transform: translate(0, 0) scale(1); }
            50% { border-radius: 70% 30% 52% 48% / 60% 40% 60% 40%; transform: translate(80px, -120px) scale(1.1); }
        }

        .animate-blob-1 {
            animation: morph-blob-1 25s infinite alternate ease-in-out;
        }
        .animate-blob-2 {
            animation: morph-blob-2 20s infinite alternate ease-in-out;
        }
        .animate-blob-3 {
            animation: morph-blob-3 22s infinite alternate ease-in-out;
        }

        /* Glassmorphism Styles */
        .glass-panel {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.15);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-5px);
            box-shadow: 0 12px 30px 0 rgba(0, 0, 0, 0.2);
        }

        .glass-navbar {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Specular light streak effect */
        .glass-highlight::after {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.15),
                transparent
            );
            transform: skewX(-25deg);
            transition: 0.75s;
        }

        .glass-highlight:hover::after {
            left: 150%;
        }
    </style>
</head>
<body class="relative min-h-screen bg-slate-950 text-slate-100 overflow-x-hidden">

    <!-- Background Color Layer -->
    <div class="fixed inset-0 bg-gradient-to-tr from-slate-950 via-indigo-950 to-slate-950 z-[-3]"></div>

    <!-- Liquid Glass Organic Blobs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-[-2]">
        <!-- Blob 1 (Teal/Emerald) -->
        <div class="absolute top-[10%] left-[5%] w-[45vw] h-[45vw] max-w-[600px] max-h-[600px] bg-emerald-500/20 blur-[100px] animate-blob-1"></div>
        <!-- Blob 2 (Indigo/Blue) -->
        <div class="absolute bottom-[15%] right-[5%] w-[50vw] h-[50vw] max-w-[700px] max-h-[700px] bg-blue-600/20 blur-[120px] animate-blob-2"></div>
        <!-- Blob 3 (Pink/Purple) -->
        <div class="absolute top-[40%] left-[30%] w-[35vw] h-[35vw] max-w-[500px] max-h-[500px] bg-purple-600/15 blur-[100px] animate-blob-3"></div>
    </div>

    <!-- Navigation Bar -->
    <nav class="glass-navbar sticky top-0 z-50 w-full">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-900 border border-white/10 flex items-center justify-center shadow-lg shadow-emerald-500/10 p-1">
                    <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div class="flex flex-col">
                    <span class="text-lg font-bold tracking-wide uppercase bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">Kauzariyya</span>
                    <span class="text-[10px] text-slate-400 tracking-widest uppercase">Al Jamiathul Kauzariyya</span>
                </div>
            </div>
            
            <div class="hidden md:flex items-center gap-8">
                <a href="#home" class="text-sm font-medium text-slate-300 hover:text-white transition">Home</a>
                <a href="#about" class="text-sm font-medium text-slate-300 hover:text-white transition">About</a>
                <a href="#courses" class="text-sm font-medium text-slate-300 hover:text-white transition">Courses</a>
                <a href="#socials" class="text-sm font-medium text-slate-300 hover:text-white transition">Socials</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="w-10 h-10 rounded-full glass-card flex items-center justify-center hover:text-pink-400 transition" title="Follow us on Instagram">
                    <i class="fa-brands fa-instagram text-lg"></i>
                </a>
                <a href="#contact" class="bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 px-5 py-2 rounded-full font-semibold hover:shadow-lg hover:shadow-emerald-400/20 transition duration-300">
                    Admission
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-6 py-12 space-y-24">

        <!-- Hero Section inside a grand Liquid Glass Panel -->
        <section id="home" class="relative glass-panel rounded-3xl p-8 md:p-16 overflow-hidden flex flex-col items-center text-center">
            <!-- Glass gloss streak -->
            <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/[0.02] to-transparent pointer-events-none"></div>

            <div class="max-w-3xl space-y-6 relative z-10">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Established 1974
                </span>

                <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight leading-tight">
                    Where Knowledge Meets <br>
                    <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-blue-500 bg-clip-text text-transparent">Spiritual Excellence</span>
                </h1>

                <p class="text-base md:text-lg text-slate-300 leading-relaxed max-w-2xl mx-auto">
                    Welcome to Al Jamiathul Kauzariyya. We are committed to fostering deep academic learning and spiritual enlightenment to build leaders for tomorrow.
                </p>

                <div class="pt-4 flex flex-col sm:flex-row justify-center gap-4">
                    <a href="#courses" class="glass-highlight relative overflow-hidden bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 px-8 py-4 rounded-xl font-bold transition shadow-xl hover:shadow-emerald-500/10">
                        Explore Our Courses
                    </a>
                    <a href="https://www.instagram.com/reel/DYVA7pMjBm9/" target="_blank" class="glass-card flex items-center justify-center gap-2 px-8 py-4 rounded-xl font-bold text-white hover:bg-white/10 transition">
                        <i class="fa-solid fa-play text-emerald-400"></i> Watch Our Introduction
                    </a>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
            <!-- Stat 1 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500/20 flex items-center justify-center text-slate-950">
                    <i class="fa-solid fa-user-graduate text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold text-white">150+</span>
                    <span class="block text-xs md:text-sm font-medium text-slate-400 uppercase tracking-wider">Students</span>
                </div>
            </div>

            <!-- Stat 2 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-400 to-indigo-500/20 flex items-center justify-center text-slate-950">
                    <i class="fa-solid fa-chalkboard-user text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold text-white">40+</span>
                    <span class="block text-xs md:text-sm font-medium text-slate-400 uppercase tracking-wider">Teachers</span>
                </div>
            </div>

            <!-- Stat 3 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-400 to-pink-500/20 flex items-center justify-center text-slate-950">
                    <i class="fa-solid fa-book-open text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold text-white">5+</span>
                    <span class="block text-xs md:text-sm font-medium text-slate-400 uppercase tracking-wider">Courses</span>
                </div>
            </div>

            <!-- Stat 4 -->
            <div class="glass-card rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3 relative overflow-hidden">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-pink-400 to-rose-500/20 flex items-center justify-center text-slate-950">
                    <i class="fa-solid fa-user-tie text-xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="block text-3xl md:text-4xl font-extrabold text-white">15+</span>
                    <span class="block text-xs md:text-sm font-medium text-slate-400 uppercase tracking-wider">Supervisors</span>
                </div>
            </div>
        </section>

        <!-- Course Cards Section -->
        <section id="courses" class="space-y-12">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight">Our Specialties</h2>
                <p class="text-slate-400">Explore the academic and religious programs offered at Kauzariyya.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Course 1 -->
                <div class="glass-card rounded-2xl p-8 flex flex-col justify-between h-80 relative overflow-hidden">
                    <div class="space-y-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500/20 flex items-center justify-center text-slate-950">
                            <i class="fa-solid fa-book-quran text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white">Hifzul Quran</h3>
                        <p class="text-sm text-slate-350">Comprehensive Quran memorization program with tajweed standards under qualified scholars.</p>
                    </div>
                    <a href="#" class="text-sm font-semibold text-emerald-400 flex items-center gap-1 hover:text-emerald-300 transition group">
                        Learn More <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition"></i>
                    </a>
                </div>

                <!-- Course 2 -->
                <div class="glass-card rounded-2xl p-8 flex flex-col justify-between h-80 relative overflow-hidden">
                    <div class="space-y-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-400 to-indigo-500/20 flex items-center justify-center text-slate-950">
                            <i class="fa-solid fa-mosque text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white">Shariah Course</h3>
                        <p class="text-sm text-slate-350">A comprehensive Islamic jurisprudence and theology curriculum matching modern developments.</p>
                    </div>
                    <a href="#" class="text-sm font-semibold text-blue-400 flex items-center gap-1 hover:text-blue-300 transition group">
                        Learn More <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition"></i>
                    </a>
                </div>

                <!-- Course 3 -->
                <div class="glass-card rounded-2xl p-8 flex flex-col justify-between h-80 relative overflow-hidden">
                    <div class="space-y-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-400 to-pink-500/20 flex items-center justify-center text-slate-950">
                            <i class="fa-solid fa-award text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white">Specializations</h3>
                        <p class="text-sm text-slate-350">Post-graduate programs specializing in Fiqh (Islamic Law) and Qirath (recitation variations).</p>
                    </div>
                    <a href="#" class="text-sm font-semibold text-purple-400 flex items-center gap-1 hover:text-purple-300 transition group">
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
                        <div class="aspect-video w-full rounded-xl bg-gradient-to-tr from-slate-900 to-indigo-950 border border-white/10 flex items-center justify-center relative overflow-hidden group">
                            <div class="absolute inset-0 bg-contain bg-no-repeat bg-center" style="background-image: url('https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png'); opacity: 0.7;"></div>
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
    <footer class="glass-navbar mt-24 py-12 px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6 text-sm text-slate-400">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-900 border border-white/10 flex items-center justify-center p-0.5">
                    <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <span class="font-bold text-white">Al Jamiathul Kauzariyya</span>
            </div>
            <div class="flex gap-6">
                <a href="https://www.instagram.com/kauzariyya/" target="_blank" class="hover:text-white transition"><i class="fa-brands fa-instagram text-lg"></i></a>
                <a href="#" class="hover:text-white transition"><i class="fa-brands fa-facebook text-lg"></i></a>
                <a href="#" class="hover:text-white transition"><i class="fa-brands fa-youtube text-lg"></i></a>
            </div>
            <p>&copy; 2026 Al Jamiathul Kauzariyya. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
