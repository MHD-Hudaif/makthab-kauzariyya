<?php
require_once __DIR__ . '/includes/header.php';

// --- POST ACTIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // 1. UPDATE USER ROLES
        if ($action === 'update_roles') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $selectedRoles = $_POST['roles'] ?? [];
            
            if (empty($selectedRoles)) {
                throw new Exception("A user must have at least one role assigned.");
            }
            
            // Clean and join roles
            $cleanRoles = array_intersect($selectedRoles, ['admin', 'supervisor', 'coordinator', 'teacher', 'student']);
            if (empty($cleanRoles)) {
                throw new Exception("Invalid roles specified.");
            }
            $rolesString = implode(',', $cleanRoles);
            
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$rolesString, $userId]);
            
            $_SESSION['msg_success'] = "User roles updated successfully.";
            header("Location: users.php");
            exit;
        }
        
        // 2. UPDATE USER STATUS
        if ($action === 'update_status') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $newStatus = $_POST['status'] ?? 'active';
            
            if (!in_array($newStatus, ['active', 'inactive', 'suspended'], true)) {
                throw new Exception("Invalid status specified.");
            }
            
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            
            $_SESSION['msg_success'] = "User status updated successfully.";
            header("Location: users.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: users.php");
        exit;
    }
}

// --- FETCH USERS ---
$search = trim($_GET['search'] ?? '');
$filterRole = trim($_GET['role'] ?? '');

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ? OR phone LIKE ? OR place LIKE ?)";
    $searchWild = "%{$search}%";
    array_push($params, $searchWild, $searchWild, $searchWild, $searchWild, $searchWild);
}

if ($filterRole !== '') {
    $sql .= " AND FIND_IN_SET(?, role)";
    $params[] = $filterRole;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Panel Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div class="space-y-1">
        <h1 class="text-2xl font-extrabold text-white">Users Directory</h1>
        <p class="text-xs text-slate-400">View logged-in users, manage multi-role combinations, and update account permissions.</p>
    </div>
</div>

<!-- Search & Filtering Bar -->
<div class="glass-card rounded-2xl p-5 border border-white/5 mb-8">
    <form action="" method="GET" class="flex flex-col md:flex-row gap-4 justify-between items-center">
        <!-- Search Input -->
        <div class="relative w-full md:max-w-md">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="glass-input w-full pl-10 pr-4 py-2.5 rounded-xl text-xs" placeholder="Search by name, username, email, phone or place...">
        </div>
        
        <!-- Filter dropdowns -->
        <div class="flex gap-3 w-full md:w-auto">
            <select name="role" class="glass-input px-4 py-2.5 rounded-xl text-xs flex-1 md:flex-initial" style="background:#123b47;">
                <option value="">All Roles</option>
                <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="supervisor" <?= $filterRole === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                <option value="coordinator" <?= $filterRole === 'coordinator' ? 'selected' : '' ?>>Coordinator</option>
                <option value="teacher" <?= $filterRole === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                <option value="student" <?= $filterRole === 'student' ? 'selected' : '' ?>>Student</option>
            </select>
            
            <button type="submit" class="bg-gradient-to-r from-emerald-400 to-blue-500 hover:shadow-lg text-slate-950 font-bold px-6 py-2.5 rounded-xl text-xs transition duration-300">
                Filter
            </button>
            <?php if ($search !== '' || $filterRole !== ''): ?>
                <a href="users.php" class="border border-white/10 hover:bg-white/5 text-slate-300 font-semibold px-4 py-2.5 rounded-xl text-xs transition flex items-center justify-center">
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Users List Grid -->
<div class="glass-panel rounded-3xl overflow-hidden border border-white/10">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b text-[10px] font-bold uppercase tracking-widest text-slate-400" style="background:rgba(18,59,71,0.3); border-color:rgba(109,204,141,0.08);">
                    <th class="py-4.5 px-6">User Info</th>
                    <th class="py-4.5 px-6">Contact & Location</th>
                    <th class="py-4.5 px-6">Roles</th>
                    <th class="py-4.5 px-6">Status</th>
                    <th class="py-4.5 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y text-xs" style="divide-color:rgba(109,204,141,0.05);">
                <?php if (empty($usersList)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400 font-semibold">
                            <i class="fa-solid fa-users-slash text-3xl mb-3 text-slate-500 block"></i>
                            No users found matching your search.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usersList as $user): ?>
                        <tr class="hover:bg-white/[0.02] transition duration-200">
                            <!-- User info -->
                            <td class="py-4 px-6 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full border border-white/10 overflow-hidden flex-shrink-0 bg-slate-950 flex items-center justify-center p-0.5">
                                    <?php if (!empty($user['profile_photo'])): ?>
                                        <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Avatar" class="w-full h-full object-cover rounded-full">
                                    <?php else: ?>
                                        <div class="w-full h-full brand-gradient flex items-center justify-center text-xs font-bold text-slate-950 rounded-full">
                                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-bold text-white truncate max-w-[180px]"><?= htmlspecialchars($user['full_name']) ?></span>
                                    <span class="text-[10px] text-slate-400 font-mono">@<?= htmlspecialchars($user['username']) ?></span>
                                </div>
                            </td>

                            <!-- Contact details -->
                            <td class="py-4 px-6">
                                <div class="flex flex-col space-y-1">
                                    <span class="text-slate-300 truncate max-w-[200px]" title="<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></span>
                                    <span class="text-[10px] text-slate-450"><?= htmlspecialchars($user['phone'] ?? 'No Phone') ?> | <?= htmlspecialchars($user['place'] ?? 'No Location') ?></span>
                                </div>
                            </td>

                            <!-- Roles list badges -->
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1.5 max-w-[200px]">
                                    <?php
                                    $userRoles = array_filter(array_map('trim', explode(',', $user['role'])));
                                    foreach ($userRoles as $role):
                                        $colorClass = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                                        if ($role === 'admin') {
                                            $colorClass = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                                        } elseif ($role === 'coordinator') {
                                            $colorClass = 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20';
                                        } elseif ($role === 'teacher') {
                                            $colorClass = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                                        } elseif ($role === 'supervisor') {
                                            $colorClass = 'bg-purple-500/10 text-purple-400 border-purple-500/20';
                                        }
                                        ?>
                                        <span class="text-[9px] font-bold uppercase tracking-wider border px-2 py-0.5 rounded <?= $colorClass ?>">
                                            <?= htmlspecialchars($role) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>

                            <!-- Account Status -->
                            <td class="py-4 px-6">
                                <form action="" method="POST" class="inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="bg-black/20 border border-white/10 hover:border-white/20 text-xs px-2.5 py-1 rounded-lg cursor-pointer text-slate-350 focus:outline-none">
                                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?> class="bg-slate-900 text-emerald-400">Active</option>
                                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?> class="bg-slate-900 text-slate-400">Inactive</option>
                                        <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?> class="bg-slate-900 text-rose-400">Suspended</option>
                                    </select>
                                </form>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6 text-right">
                                <button onclick="openRoleModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>', '<?= htmlspecialchars($user['role']) ?>')" class="w-8 h-8 rounded-lg border border-white/10 hover:border-emerald-400/30 hover:bg-emerald-500/5 text-slate-300 hover:text-emerald-400 transition" title="Modify Roles">
                                    <i class="fa-solid fa-user-pen text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Role Editor Modal overlay -->
<div id="role-modal" class="fixed inset-0 z-50 backdrop-blur-sm bg-black/60 hidden items-center justify-center p-4">
    <div class="w-full max-w-md glass-panel rounded-3xl p-6 border border-white/10 space-y-6 text-left">
        <!-- Modal Heading -->
        <div class="flex justify-between items-center pb-3 border-b border-white/10">
            <h3 class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-shield text-emerald-400"></i> Modify Roles
            </h3>
            <button onclick="closeRoleModal()" class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <p class="text-xs text-slate-350 leading-relaxed">
            Configure system roles for <span id="modal-user-name" class="font-bold text-white"></span>. You can select multiple check combinations to grant multi-portal permissions.
        </p>

        <!-- Role forms checkbox -->
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_roles">
            <input type="hidden" id="modal-user-id" name="user_id" value="">
            
            <div class="space-y-2">
                <label class="flex items-center gap-3 p-3 rounded-xl border border-white/5 hover:border-white/10 bg-white/5 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="admin" id="role-chk-admin" class="w-4 h-4 rounded text-emerald-400 focus:ring-0 focus:ring-offset-0 bg-black/40 border-white/10">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-rose-400">Admin</span>
                        <span class="text-[9px] text-slate-450">Full administrative access & billing registries.</span>
                    </div>
                </label>
                
                <label class="flex items-center gap-3 p-3 rounded-xl border border-white/5 hover:border-white/10 bg-white/5 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="supervisor" id="role-chk-supervisor" class="w-4 h-4 rounded text-emerald-400 focus:ring-0 focus:ring-offset-0 bg-black/40 border-white/10">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-purple-400">Supervisor</span>
                        <span class="text-[9px] text-slate-450">Google Meet audits, reviews & leave approvals.</span>
                    </div>
                </label>
                
                <label class="flex items-center gap-3 p-3 rounded-xl border border-white/5 hover:border-white/10 bg-white/5 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="coordinator" id="role-chk-coordinator" class="w-4 h-4 rounded text-emerald-400 focus:ring-0 focus:ring-offset-0 bg-black/40 border-white/10">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-cyan-400">Coordinator</span>
                        <span class="text-[9px] text-slate-450">Class schedule planner, curriculum configurations.</span>
                    </div>
                </label>
                
                <label class="flex items-center gap-3 p-3 rounded-xl border border-white/5 hover:border-white/10 bg-white/5 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="teacher" id="role-chk-teacher" class="w-4 h-4 rounded text-emerald-400 focus:ring-0 focus:ring-offset-0 bg-black/40 border-white/10">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-amber-400">Teacher</span>
                        <span class="text-[9px] text-slate-450">Session logger, opening Dua timers, progress notes.</span>
                    </div>
                </label>
                
                <label class="flex items-center gap-3 p-3 rounded-xl border border-white/5 hover:border-white/10 bg-white/5 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="student" id="role-chk-student" class="w-4 h-4 rounded text-emerald-400 focus:ring-0 focus:ring-offset-0 bg-black/40 border-white/10">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-emerald-400">Student</span>
                        <span class="text-[9px] text-slate-450">Attendance logs, roadmap milestone tracker.</span>
                    </div>
                </label>
            </div>
            
            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeRoleModal()" class="w-1/2 border border-white/10 hover:bg-white/5 text-slate-300 font-semibold py-3 rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit" class="w-1/2 bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold py-3 rounded-xl text-xs hover:shadow-lg transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRoleModal(userId, fullName, currentRoles) {
        document.getElementById('modal-user-id').value = userId;
        document.getElementById('modal-user-name').textContent = fullName;
        
        // Reset checkboxes
        const rolesArr = currentRoles.split(',').map(r => r.trim());
        const rolesMap = ['admin', 'supervisor', 'coordinator', 'teacher', 'student'];
        
        rolesMap.forEach(role => {
            const chk = document.getElementById('role-chk-' + role);
            if (chk) {
                chk.checked = rolesArr.includes(role);
            }
        });
        
        const modal = document.getElementById('role-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    
    function closeRoleModal() {
        const modal = document.getElementById('role-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
