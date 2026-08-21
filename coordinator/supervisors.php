<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE SUPERVISOR
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
                $_SESSION['msg_success'] = "Supervisor '{$full_name}' created successfully.";
            } else {
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPass, $full_name, $email, $phone, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $phone, $userId]);
                }
                $_SESSION['msg_success'] = "Supervisor '{$full_name}' updated successfully.";
            }

            header("Location: supervisors");
            exit;
        }

        // 2. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'supervisor') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Supervisor profile deleted successfully.";
            }
            header("Location: supervisors");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: supervisors");
        exit;
    }
}

// --- FETCH DATA ---
$supervisorsRaw = $pdo->query("
    SELECT u.id as supervisor_id, u.username as supervisor_username, u.full_name as supervisor_name, u.email as supervisor_email, u.phone as supervisor_phone
    FROM users u
    WHERE u.role = 'supervisor'
    ORDER BY u.full_name ASC
")->fetchAll();

$classes = $pdo->query("
    SELECT c.id, c.name, u.id as supervisor_id
    FROM classes c
    LEFT JOIN users u ON c.supervisor_id = u.id
")->fetchAll();

// Group classes by supervisor_id
$classesBySupervisor = [];
foreach ($classes as $class) {
    if ($class['supervisor_id']) {
        $classesBySupervisor[$class['supervisor_id']][] = $class;
    }
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Controls bar -->
<div class="flex flex-wrap gap-4 items-center justify-between">
    <h3 class="text-lg font-bold text-white">Supervisors Directory</h3>
    <button onclick="openSupervisorModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
        <i class="fa-solid fa-user-plus"></i> Add Supervisor
    </button>
</div>

<!-- Supervisors Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php if (empty($supervisorsRaw)): ?>
        <div class="glass-panel rounded-2xl p-10 text-center col-span-2">
            <i class="fa-solid fa-user-tie text-4xl text-slate-600 mb-3 block"></i>
            <p class="text-slate-500 font-medium">No supervisors registered yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($supervisorsRaw as $supervisor): ?>
            <div class="glass-panel rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-slate-950 font-bold text-lg">
                            <?= strtoupper(substr($supervisor['supervisor_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($supervisor['supervisor_name']) ?></h3>
                            <p class="text-xs text-slate-400 font-mono">@<?= htmlspecialchars($supervisor['supervisor_username']) ?></p>
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
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button onclick="confirmDelete('supervisor', <?= $supervisor['supervisor_id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Supervisor">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-slate-350">
                            <i class="fa-regular fa-envelope w-4 text-[11px] opacity-70"></i>
                            <span><?= htmlspecialchars($supervisor['supervisor_email'] ?? 'No Gmail') ?></span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-350">
                            <i class="fa-solid fa-mobile-screen w-4 text-[11px] opacity-70"></i>
                            <span><?= htmlspecialchars($supervisor['supervisor_phone'] ?? 'No Mobile') ?></span>
                        </div>
                    </div>

                    <!-- Classes Supervised -->
                    <div class="pt-2 border-t border-white/5">
                        <span class="block text-[9px] text-slate-400 font-semibold uppercase tracking-wider mb-2">Overseen Classes</span>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $supervisedClasses = $classesBySupervisor[$supervisor['supervisor_id']] ?? [];
                            if (empty($supervisedClasses)): 
                            ?>
                                <span class="text-xs text-slate-500 font-medium">No classes assigned</span>
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
    <?php endif; ?>
</div>

<!-- ================= MODALS ================= -->
<!-- Supervisor Modal -->
<div id="modal-supervisor" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
    <div class="w-full max-w-lg glass-panel rounded-3xl p-8 relative space-y-6">
        <div class="flex justify-between items-center">
            <h3 id="supervisor-modal-title" class="text-xl font-bold text-white">Add New Supervisor</h3>
            <button onclick="closeModal('supervisor')" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form action="supervisors" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_supervisor">
            <input type="hidden" name="supervisor_id" id="supervisor_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Username</label>
                    <input type="text" name="username" id="supervisor_username" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. supervisor_ali" required>
                </div>
                <div class="space-y-1.5">
                    <label id="lbl-supervisor-password" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Password</label>
                    <input type="password" name="password" id="supervisor_password" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="••••••••">
                </div>
            </div>
            
            <div class="space-y-1.5">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name</label>
                <input type="text" name="full_name" id="supervisor_fullname" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Ustad Ibrahim" required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Gmail / Email</label>
                    <input type="email" name="email" id="supervisor_email" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="email@gmail.com">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Mobile Number</label>
                    <input type="text" name="phone" id="supervisor_phone" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="+91 999 555 1234">
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('supervisor')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs hover:shadow-lg hover:shadow-emerald-400/20 transition">Save Supervisor</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openSupervisorModal(data = null) {
        const modal = document.getElementById('modal-supervisor');
        const title = document.getElementById('supervisor-modal-title');
        const form = modal.querySelector('form');
        const passLabel = document.getElementById('lbl-supervisor-password');
        const passInput = document.getElementById('supervisor_password');

        form.reset();
        document.getElementById('supervisor_id').value = '';

        if (data) {
            title.innerText = 'Edit Supervisor';
            document.getElementById('supervisor_id').value = data.supervisor_id;
            document.getElementById('supervisor_username').value = data.username;
            document.getElementById('supervisor_fullname').value = data.full_name;
            document.getElementById('supervisor_email').value = data.email || '';
            document.getElementById('supervisor_phone').value = data.phone || '';
            passLabel.innerText = 'New Password (leave blank to keep)';
            passInput.removeAttribute('required');
        } else {
            title.innerText = 'Add New Supervisor';
            passLabel.innerText = 'Password';
            passInput.setAttribute('required', 'required');
        }
        modal.classList.remove('hidden');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
