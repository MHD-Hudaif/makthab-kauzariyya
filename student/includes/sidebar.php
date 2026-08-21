<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
    <!-- Sidebar Navigation -->
    <aside id="sidebar-drawer" class="fixed inset-y-0 left-0 w-64 shadow-2xl md:fixed md:top-4 md:left-4 md:bottom-4 md:h-[calc(100vh-32px)] md:rounded-[22px] md:border flex flex-col justify-between p-6 z-50 backdrop-blur-md transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out" style="background:rgba(14,46,56,0.95); border-color:rgba(109,204,141,0.12);">
        <div class="space-y-8">
            <!-- Branding Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden border flex items-center justify-center p-1" style="background:#123b47; border-color:rgba(109,204,141,0.2);">
                        <img src="https://kauzariyya.com/wp-content/uploads/2024/01/Kauzariyya-Old-Curve.png" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold tracking-wide uppercase brand-text-gradient">Student Panel</span>
                        <span class="text-[9px] tracking-widest uppercase" style="color:rgba(167,235,243,0.5);">Al Jamiathul Kauzariyya</span>
                    </div>
                </div>
                <!-- Close Button for Mobile -->
                <button onclick="toggleMobileSidebar(false)" class="flex md:hidden w-8 h-8 items-center justify-center rounded-lg border transition" style="background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.12);" title="Close Menu">
                    <i class="fa-solid fa-xmark" style="color:rgba(236,243,214,0.7);"></i>
                </button>
            </div>

            <!-- Tab Menu -->
            <nav class="space-y-4 flex flex-col">
                <div class="space-y-1.5">
                    <span class="block px-4 text-[9px] font-bold uppercase tracking-widest" style="color:rgba(167,235,243,0.45);">General</span>
                    <a href="./" class="tab-btn w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-semibold transition border border-transparent <?= $currentPage === 'index.php' ? 'active' : '' ?>" style="color:rgba(236,243,214,0.7);">
                        <i class="fa-solid fa-chart-pie w-4"></i> Dashboard
                    </a>
                </div>
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="pt-6 border-t mt-6 space-y-4" style="border-color:rgba(109,204,141,0.12);">
            <!-- User Badge -->
            <div class="flex items-center justify-between border p-3 rounded-xl" style="background:rgba(109,204,141,0.04); border-color:rgba(109,204,141,0.1);">
                <div class="flex items-center gap-2.5 min-w-0">
                    <?php if (!empty($currentUser['profile_photo'])): ?>
                        <img src="<?= htmlspecialchars($currentUser['profile_photo']) ?>" alt="Avatar" class="w-8 h-8 rounded-full border border-white/10 object-cover flex-shrink-0">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-full brand-gradient flex items-center justify-center text-xs font-bold flex-shrink-0 relative" style="color:#0e2e38;">
                            <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-bold truncate" style="color:#ecf3d6;"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                        <span class="text-[9px] uppercase font-semibold tracking-wider" style="color:rgba(167,235,243,0.5);"><?= htmlspecialchars($currentUser['role']) ?></span>
                    </div>
                </div>
                <a href="../logout" class="w-8 h-8 rounded-lg border flex items-center justify-center transition flex-shrink-0" style="background:rgba(220,38,38,0.08); border-color:rgba(220,38,38,0.2); color:#f87171;" title="Logout">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                </a>
            </div>

            <!-- Home link -->
            <a href="../index" class="flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-lg text-xs font-semibold transition border w-full" style="background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.12); color:rgba(236,243,214,0.7);">
                <i class="fa-solid fa-arrow-left"></i> Back to Homepage
            </a>
        </div>
    </aside>

    <!-- Main Content Panel -->
    <div class="flex-1 flex flex-col min-w-0 md:ml-72 md:pt-4 md:pr-4 md:pb-4">
        
        <!-- Header -->
        <header class="h-20 border-b flex items-center justify-between px-8 sticky top-0 z-30" style="background:rgba(14,46,56,0.4); border-color:rgba(109,204,141,0.1); backdrop-filter:blur(12px);">
            <h2 id="current-tab-title" class="text-xl font-bold tracking-wide" style="color:#ecf3d6;"><?= htmlspecialchars($pageTitle) ?></h2>
            <div class="flex items-center gap-2 text-xs px-3.5 py-1.5 rounded-full border" style="color:rgba(167,235,243,0.6); background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.15);">
                <span class="w-2 h-2 rounded-full" style="background:#6dcc8d;"></span>
                <span>Student Workspace</span>
            </div>
        </header>

        <!-- Main Scrollable Section -->
        <main class="flex-1 p-6 md:p-8 space-y-8 w-full max-w-7xl mx-auto">
