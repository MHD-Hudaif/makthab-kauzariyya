        </main>
    </div>

    <!-- Confirm Deletion Overlay Modal (Shared across registries) -->
    <div id="modal-delete" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6" style="background:rgba(14,46,56,0.7); backdrop-filter:blur(6px);">
        <div class="w-full max-w-sm glass-panel rounded-3xl p-6 relative space-y-4 text-center">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-4xl"></i>
            <h4 class="text-lg font-bold text-white">Confirm Deletion</h4>
            <p class="text-xs text-slate-400">Are you absolutely sure you want to delete this record? This action cannot be undone.</p>
            
            <form action="" method="POST" class="flex justify-center gap-3 pt-2">
                <input type="hidden" name="action" value="delete_entity">
                <input type="hidden" name="entity_type" id="del_entity_type">
                <input type="hidden" name="id" id="del_entity_id">
                
                <button type="button" onclick="closeModal('delete')" class="px-4 py-2 rounded-xl text-xs font-semibold border transition" style="background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.15); color:rgba(236,243,214,0.7);">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl border font-bold text-xs transition" style="background:rgba(220,38,38,0.2); border-color:rgba(220,38,38,0.35); color:#f87171;">Delete</button>
            </form>
        </div>
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

        // Delete Confirmation modal trigger
        function confirmDelete(entityType, id) {
            const modal = document.getElementById('modal-delete');
            if (!modal) return;
            
            document.getElementById('del_entity_type').value = entityType;
            document.getElementById('del_entity_id').value = id;
            
            modal.classList.remove('hidden');
        }
    </script>
</body>
</html>
