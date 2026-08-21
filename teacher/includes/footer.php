        </main>
    </div>

    <!-- JavaScript Helpers -->
    <script>
        // Mobile Sidebar Drawer Toggle helper
        function toggleMobileSidebar(isOpen) {
            const sidebar = document.getElementById('sidebar-drawer');
            const overlay = document.getElementById('sidebar-overlay');
            if (!sidebar || !overlay) return;
            
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

        // Close any modal overlay
        function closeModal(type) {
            const modal = document.getElementById('modal-' + type);
            if (modal) {
                modal.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
