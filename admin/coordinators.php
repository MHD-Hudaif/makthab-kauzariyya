<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE COORDINATOR
        if ($action === 'save_coordinator') {
            $userId = $_POST['coordinator_id'] ?? '';
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
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, 'coordinator', 'active')");
                $stmt->execute([$username, $hashedPass, $full_name, $email, $phone]);
                $_SESSION['msg_success'] = "Coordinator '{$full_name}' created successfully.";
            } else {
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $phone, $userId]);
                }
                $_SESSION['msg_success'] = "Coordinator '{$full_name}' updated successfully.";
            }

            header("Location: coordinators.php");
            exit;
        }

        // 2. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'coordinator') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Coordinator profile deleted successfully.";
            }
            header("Location: coordinators.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: coordinators.php");
        exit;
    }
}

// --- FETCH DATA ---
$coordinators = $pdo->query("
    SELECT id as coordinator_id, username as coordinator_username, full_name as coordinator_name, email as coordinator_email, phone as coordinator_phone
    FROM users
    WHERE role = 'coordinator'
    ORDER BY full_name ASC
")->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Controls bar -->
<div class="flex flex-wrap gap-4 items-center justify-between">
    <h3 class="text-lg font-bold text-white">Coordinators Directory</h3>
    <button onclick="openCoordinatorModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
        <i class="fa-solid fa-user-plus"></i> Add Coordinator
    </button>
</div>

<!-- Coordinators Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php if (empty($coordinators)): ?>
        <div class="glass-panel rounded-2xl p-10 text-center col-span-2">
            <i class="fa-solid fa-user-gear text-4xl text-slate-600 mb-3 block"></i>
            <p class="text-slate-500 font-medium">No coordinators registered yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($coordinators as $coordinator): ?>
            <div class="glass-panel rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-500 flex items-center justify-center text-slate-950 font-bold text-lg">
                            <?= strtoupper(substr($coordinator['coordinator_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($coordinator['coordinator_name']) ?></h3>
                            <p class="text-xs text-slate-400 font-mono">@<?= htmlspecialchars($coordinator['coordinator_username']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="openCoordinatorModal(<?= htmlspecialchars(json_encode([
                            'coordinator_id' => $coordinator['coordinator_id'],
                            'username' => $coordinator['coordinator_username'],
                            'full_name' => $coordinator['coordinator_name'],
                            'email' => $coordinator['coordinator_email'],
                            'phone' => $coordinator['coordinator_phone']
                        ])) ?>)" class="p-1.5 text-xs bg-white/5 hover:bg-white/10 text-slate-300 rounded border border-white/8 transition" title="Edit Coordinator">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button onclick="confirmDelete('coordinator', <?= $coordinator['coordinator_id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Coordinator">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex items-center gap-2 text-slate-300">
                        <i class="fa-regular fa-envelope w-4 text-[11px] opacity-75"></i>
                        <span><?= htmlspecialchars($coordinator['coordinator_email'] ?? 'No Email') ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-300">
                        <i class="fa-solid fa-mobile-screen w-4 text-[11px] opacity-75"></i>
                        <span><?= htmlspecialchars($coordinator['coordinator_phone'] ?? 'No Mobile Phone') ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: Save Coordinator -->
<div id="modal-coordinator" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6" style="background:rgba(14,46,56,0.75); backdrop-filter:blur(6px);">
    <div class="w-full max-w-md glass-panel rounded-3xl p-6 relative space-y-6">
        <!-- Close Button -->
        <button onclick="closeModal('coordinator')" class="absolute top-4 right-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 id="modal-title" class="text-xl font-bold text-white">Add Coordinator</h3>
        
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_coordinator">
            <input type="hidden" name="coordinator_id" id="coordinator_id">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400" for="username">Username</label>
                    <input type="text" name="username" id="username" required class="w-full px-4 py-2.5 rounded-xl border text-sm glass-input">
                </div>
                <div class="space-y-1">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400" for="password">Password</label>
                    <input type="password" name="password" id="password" class="w-full px-4 py-2.5 rounded-xl border text-sm glass-input" placeholder="Keep blank to skip">
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400" for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" required class="w-full px-4 py-2.5 rounded-xl border text-sm glass-input">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400" for="email">Email</label>
                    <input type="email" name="email" id="email" class="w-full px-4 py-2.5 rounded-xl border text-sm glass-input">
                </div>
                <div class="space-y-1">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400" for="phone">Phone</label>
                    <input type="text" name="phone" id="phone" class="w-full px-4 py-2.5 rounded-xl border text-sm glass-input">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-white/10">
                <button type="button" onclick="closeModal('coordinator')" class="px-5 py-2.5 rounded-xl text-xs font-bold border transition" style="background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.15); color:rgba(236,243,214,0.7);">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl border font-bold text-xs bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 hover:shadow-lg transition">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCoordinatorModal(data = null) {
        const modal = document.getElementById('modal-coordinator');
        const title = document.getElementById('modal-title');
        
        if (data) {
            title.textContent = 'Edit Coordinator';
            document.getElementById('coordinator_id').value = data.coordinator_id;
            document.getElementById('username').value = data.username;
            document.getElementById('username').readOnly = true;
            document.getElementById('password').required = false;
            document.getElementById('full_name').value = data.full_name;
            document.getElementById('email').value = data.email || '';
            document.getElementById('phone').value = data.phone || '';
        } else {
            title.textContent = 'Add Coordinator';
            document.getElementById('coordinator_id').value = '';
            document.getElementById('username').value = '';
            document.getElementById('username').readOnly = false;
            document.getElementById('password').required = true;
            document.getElementById('full_name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('phone').value = '';
        }
        
        modal.classList.remove('hidden');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
