<?php
require_once __DIR__ . '/includes/header.php';

// --- POST ACTIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // 1. ADD ROSTER ENTRY
        if ($action === 'add_roster') {
            $type = trim($_POST['type'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $assignedTeacher = trim($_POST['assigned_teacher_name'] ?? '');
            
            if (!in_array($type, ['teacher', 'student'], true) || empty($name)) {
                throw new Exception("Please specify valid type and member name.");
            }
            
            $stmt = $pdo->prepare("INSERT INTO verification_roster (type, name, assigned_teacher_name) VALUES (?, ?, ?)");
            $stmt->execute([$type, $name, ($type === 'student' && $assignedTeacher !== '') ? $assignedTeacher : null]);
            
            $_SESSION['msg_success'] = "Directory record for '{$name}' added successfully.";
            header("Location: roster");
            exit;
        }

        // 2. EDIT ROSTER ENTRY
        if ($action === 'edit_roster') {
            $id = (int)($_POST['id'] ?? 0);
            $type = trim($_POST['type'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $assignedTeacher = trim($_POST['assigned_teacher_name'] ?? '');
            
            if ($id <= 0 || !in_array($type, ['teacher', 'student'], true) || empty($name)) {
                throw new Exception("Invalid data submitted for roster edit.");
            }
            
            $stmt = $pdo->prepare("UPDATE verification_roster SET type = ?, name = ?, assigned_teacher_name = ? WHERE id = ?");
            $stmt->execute([$type, $name, ($type === 'student' && $assignedTeacher !== '') ? $assignedTeacher : null, $id]);
            
            $_SESSION['msg_success'] = "Directory record updated successfully.";
            header("Location: roster");
            exit;
        }

        // 3. DELETE ROSTER ENTRY
        if ($action === 'delete_roster') {
            $id = (int)($_POST['id'] ?? 0);
            
            $stmt = $pdo->prepare("DELETE FROM verification_roster WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['msg_success'] = "Directory record removed.";
            header("Location: roster");
            exit;
        }

        // 4. UNLINK / RESET CLAIM STATUS
        if ($action === 'reset_claim') {
            $id = (int)($_POST['id'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE verification_roster SET is_claimed = 0, claimed_user_id = NULL, claimed_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['msg_success'] = "Claim status reset to unclaimed.";
            header("Location: roster");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: roster");
        exit;
    }
}

// --- FETCH & FILTER ROSTER ---
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "
    SELECT vr.*, u.username AS claimed_username, u.full_name AS claimed_fullname, u.email AS claimed_email, u.phone AS claimed_phone
    FROM verification_roster vr
    LEFT JOIN users u ON vr.claimed_user_id = u.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (vr.name LIKE ? OR vr.assigned_teacher_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filterType !== '') {
    $sql .= " AND vr.type = ?";
    $params[] = $filterType;
}

if ($filterStatus === 'claimed') {
    $sql .= " AND vr.is_claimed = 1";
} elseif ($filterStatus === 'unclaimed') {
    $sql .= " AND vr.is_claimed = 0";
}

$sql .= " ORDER BY vr.type ASC, vr.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rosterList = $stmt->fetchAll();

// Counts
$totalRoster = (int)$pdo->query("SELECT COUNT(*) FROM verification_roster")->fetchColumn();
$claimedCount = (int)$pdo->query("SELECT COUNT(*) FROM verification_roster WHERE is_claimed = 1")->fetchColumn();
$unclaimedCount = $totalRoster - $claimedCount;

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Panel Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div class="space-y-1">
        <h1 class="text-2xl font-extrabold text-white">Manual Verification Roster</h1>
        <p class="text-xs text-slate-400">Pre-seeded directory of legacy teachers & students eligible for self-verification.</p>
    </div>
    
    <button onclick="openAddModal()" class="brand-gradient px-4 py-2.5 rounded-xl font-bold text-xs text-slate-950 hover:shadow-lg transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i> Add Directory Entry
    </button>
</div>

<!-- Stats Bar -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="glass-card rounded-2xl p-4 border border-white/5 flex items-center justify-between">
        <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Directory</span>
            <span class="text-xl font-extrabold text-white"><?= $totalRoster ?></span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
            <i class="fa-solid fa-address-book text-base"></i>
        </div>
    </div>
    <div class="glass-card rounded-2xl p-4 border border-white/5 flex items-center justify-between">
        <div>
            <span class="text-[10px] uppercase font-bold text-emerald-400 block tracking-wider">Claimed / Verified</span>
            <span class="text-xl font-extrabold text-emerald-400"><?= $claimedCount ?></span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
            <i class="fa-solid fa-circle-check text-base"></i>
        </div>
    </div>
    <div class="glass-card rounded-2xl p-4 border border-white/5 flex items-center justify-between">
        <div>
            <span class="text-[10px] uppercase font-bold text-amber-400 block tracking-wider">Unclaimed</span>
            <span class="text-xl font-extrabold text-amber-400"><?= $unclaimedCount ?></span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
            <i class="fa-solid fa-hourglass-half text-base"></i>
        </div>
    </div>
</div>

<!-- Search & Filtering Bar -->
<div class="glass-card rounded-2xl p-5 border border-white/5 mb-8">
    <form action="" method="GET" class="flex flex-col md:flex-row gap-4 justify-between items-center">
        <!-- Search Input -->
        <div class="relative w-full md:max-w-md">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="glass-input w-full pl-10 pr-4 py-2.5 rounded-xl text-xs" placeholder="Search by name or teacher...">
        </div>
        
        <!-- Filter dropdowns -->
        <div class="flex gap-3 w-full md:w-auto">
            <select name="type" class="glass-input px-4 py-2.5 rounded-xl text-xs" style="background:#123b47;">
                <option value="">All Types</option>
                <option value="teacher" <?= $filterType === 'teacher' ? 'selected' : '' ?>>Teachers Only</option>
                <option value="student" <?= $filterType === 'student' ? 'selected' : '' ?>>Students Only</option>
            </select>

            <select name="status" class="glass-input px-4 py-2.5 rounded-xl text-xs" style="background:#123b47;">
                <option value="">All Statuses</option>
                <option value="unclaimed" <?= $filterStatus === 'unclaimed' ? 'selected' : '' ?>>Unclaimed</option>
                <option value="claimed" <?= $filterStatus === 'claimed' ? 'selected' : '' ?>>Claimed</option>
            </select>
            
            <button type="submit" class="bg-gradient-to-r from-emerald-400 to-blue-500 hover:shadow-lg text-slate-950 font-bold px-6 py-2.5 rounded-xl text-xs transition duration-300">
                Filter
            </button>
            <?php if ($search !== '' || $filterType !== '' || $filterStatus !== ''): ?>
                <a href="roster" class="border border-white/10 hover:bg-white/5 text-slate-300 font-semibold px-4 py-2.5 rounded-xl text-xs transition flex items-center justify-center">
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Roster Table Grid -->
<div class="glass-panel rounded-3xl overflow-hidden border border-white/10">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b text-[10px] font-bold uppercase tracking-widest text-slate-400" style="background:rgba(18,59,71,0.3); border-color:rgba(109,204,141,0.08);">
                    <th class="py-4 px-6">Name</th>
                    <th class="py-4 px-6">Type</th>
                    <th class="py-4 px-6">Assigned Teacher</th>
                    <th class="py-4 px-6">Claim Status</th>
                    <th class="py-4 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y text-xs" style="divide-color:rgba(109,204,141,0.05);">
                <?php if (empty($rosterList)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400 font-semibold">
                            <i class="fa-solid fa-clipboard-list text-3xl mb-3 text-slate-500 block"></i>
                            No directory entries found matching your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rosterList as $row): ?>
                        <tr class="hover:bg-white/[0.02] transition duration-200">
                            <!-- Name -->
                            <td class="py-4 px-6 font-bold text-white">
                                <?= htmlspecialchars($row['name']) ?>
                            </td>

                            <!-- Type -->
                            <td class="py-4 px-6">
                                <?php if ($row['type'] === 'teacher'): ?>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                        Teacher
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Student
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Assigned Teacher -->
                            <td class="py-4 px-6 text-slate-300">
                                <?= htmlspecialchars($row['assigned_teacher_name'] ?? '—') ?>
                            </td>

                            <!-- Claim Status -->
                            <td class="py-4 px-6">
                                <?php if ($row['is_claimed']): ?>
                                    <div class="flex flex-col space-y-0.5">
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 w-fit">
                                            <i class="fa-solid fa-check mr-1"></i> Claimed
                                        </span>
                                        <?php if (!empty($row['claimed_fullname'])): ?>
                                            <span class="text-[11px] text-slate-300 font-semibold"><?= htmlspecialchars($row['claimed_fullname']) ?> (@<?= htmlspecialchars($row['claimed_username']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!empty($row['claimed_user_id'])): ?>
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                        <i class="fa-solid fa-clock mr-1"></i> Pending Review
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-500/15 text-slate-400 border border-white/10">
                                        Unclaimed
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="w-8 h-8 rounded-lg border border-white/10 hover:border-white/20 text-slate-300 hover:text-white transition flex items-center justify-center" title="Edit Entry">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>

                                    <?php if ($row['is_claimed']): ?>
                                        <form action="" method="POST" onsubmit="return confirm('Reset claim status for this profile?');" class="inline">
                                            <input type="hidden" name="action" value="reset_claim">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg border border-amber-500/20 text-amber-400 hover:bg-amber-500/10 transition flex items-center justify-center" title="Reset Claim">
                                                <i class="fa-solid fa-rotate-left text-xs"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form action="" method="POST" onsubmit="return confirm('Are you sure you want to delete this directory record?');" class="inline">
                                        <input type="hidden" name="action" value="delete_roster">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-lg border border-rose-500/20 text-rose-400 hover:bg-rose-500/10 transition flex items-center justify-center" title="Delete">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Entry -->
<div id="roster-modal" class="fixed inset-0 z-50 backdrop-blur-sm bg-black/60 hidden items-center justify-center p-4">
    <div class="w-full max-w-md glass-panel rounded-3xl p-6 border border-white/10 space-y-6 text-left" style="background: rgba(14, 46, 56, 0.98);">
        <div class="flex justify-between items-center pb-3 border-b border-white/10">
            <h3 id="modal-title" class="text-md font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-emerald-400"></i> Add Directory Entry
            </h3>
            <button onclick="closeModal()" class="w-8 h-8 rounded-lg border border-white/10 flex items-center justify-center text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="action" id="form-action" value="add_roster">
            <input type="hidden" name="id" id="form-id" value="">

            <div class="space-y-1.5">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Member Type <span class="text-red-400">*</span></label>
                <select name="type" id="form-type" required onchange="toggleTeacherField(this.value)" class="glass-input w-full px-4 py-3 rounded-xl text-sm" style="background:#123b47;">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher / Ustad</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Full Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" id="form-name" required class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. NOOH USTAD or MIRZA">
            </div>

            <div id="form-assigned-teacher-group" class="space-y-1.5">
                <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Assigned Teacher (For Students)</label>
                <input type="text" name="assigned_teacher_name" id="form-assigned-teacher" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. NOOH USTAD">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal()" class="w-1/2 border border-white/10 hover:bg-white/5 text-slate-300 font-semibold py-3 rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit" class="w-1/2 bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold py-3 rounded-xl text-xs hover:shadow-lg transition">
                    Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('form-action').value = 'add_roster';
        document.getElementById('form-id').value = '';
        document.getElementById('form-type').value = 'student';
        document.getElementById('form-name').value = '';
        document.getElementById('form-assigned-teacher').value = '';
        document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-plus-circle text-emerald-400"></i> Add Directory Entry';
        toggleTeacherField('student');

        const modal = document.getElementById('roster-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function openEditModal(data) {
        document.getElementById('form-action').value = 'edit_roster';
        document.getElementById('form-id').value = data.id;
        document.getElementById('form-type').value = data.type;
        document.getElementById('form-name').value = data.name;
        document.getElementById('form-assigned-teacher').value = data.assigned_teacher_name || '';
        document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-emerald-400"></i> Edit Directory Entry';
        toggleTeacherField(data.type);

        const modal = document.getElementById('roster-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        const modal = document.getElementById('roster-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function toggleTeacherField(type) {
        const group = document.getElementById('form-assigned-teacher-group');
        if (type === 'student') {
            group.classList.remove('hidden');
        } else {
            group.classList.add('hidden');
        }
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
