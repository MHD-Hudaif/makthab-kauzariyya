<?php
require_once __DIR__ . '/includes/header.php';

// Check if class_audits table exists
$tableExists = $pdo->query("SHOW TABLES LIKE 'class_audits'")->fetch();

$audits = [];
if ($tableExists) {
    // Fetch audit logs joining classes and supervisors
    $audits = $pdo->query("
        SELECT a.*, c.name as class_name, u.full_name as supervisor_name
        FROM class_audits a
        JOIN classes c ON a.class_id = c.id
        JOIN users u ON a.supervisor_id = u.id
        ORDER BY a.audit_date DESC
    ")->fetchAll();
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- ================= CLASS AUDITS PAGE ================= -->
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-white">Classroom Audits & Evaluations</h3>
        <span class="text-xs px-3 py-1 rounded-full border" style="color:rgba(167,235,243,0.6); background:rgba(109,204,141,0.05); border-color:rgba(109,204,141,0.15);">
            Weekly Quality Control Logs
        </span>
    </div>

    <?php if (!$tableExists): ?>
        <!-- Table Not Migrated Info -->
        <div class="glass-panel rounded-3xl p-8 text-center max-w-xl mx-auto space-y-4">
            <i class="fa-solid fa-clock-rotate-left text-4xl text-slate-500 animate-pulse"></i>
            <h4 class="text-lg font-bold text-white">Auditing Module Pending Migration</h4>
            <p class="text-xs text-slate-300 leading-relaxed">
                The class evaluations database structure is planned for deployment during the next phase of supervisor integration. Once migrated, supervisor ratings, camera presence verification, and classroom notes will appear here in real-time.
            </p>
        </div>
    <?php elseif (empty($audits)): ?>
        <!-- Empty State -->
        <div class="glass-panel rounded-2xl p-10 text-center">
            <i class="fa-solid fa-clipboard-question text-4xl text-slate-650 mb-3 block"></i>
            <p class="text-slate-500 font-medium">No classroom audits submitted by supervisors yet.</p>
        </div>
    <?php else: ?>
        <!-- Audits List -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($audits as $audit): ?>
                <div class="glass-panel rounded-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-white/10">
                        <div>
                            <h4 class="text-md font-bold text-white"><?= htmlspecialchars($audit['class_name']) ?></h4>
                            <p class="text-[10px] text-slate-400 font-mono">Audited by Ustad <?= htmlspecialchars($audit['supervisor_name']) ?></p>
                        </div>
                        <span class="text-xs text-slate-300 font-semibold bg-white/5 border border-white/10 px-2.5 py-1 rounded-md">
                            <?= htmlspecialchars($audit['audit_date']) ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div class="space-y-1">
                            <span class="block text-[9px] text-slate-500 uppercase tracking-widest">Timekeeping Score</span>
                            <div class="flex gap-0.5 text-yellow-500">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-<?= $i <= $audit['timekeeping_score'] ? 'solid' : 'regular' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <span class="block text-[9px] text-slate-500 uppercase tracking-widest">Motivation Score</span>
                            <div class="flex gap-0.5 text-yellow-500">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-<?= $i <= $audit['motivation_score'] ? 'solid' : 'regular' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <div class="text-xs space-y-1">
                        <span class="block text-[9px] text-slate-500 uppercase tracking-widest">Verification Checklist</span>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 <?= $audit['camera_on'] ? 'text-emerald-400' : 'text-red-400' ?>">
                                <i class="fa-solid fa-<?= $audit['camera_on'] ? 'circle-check' : 'circle-xmark' ?>"></i>
                                <span>Teacher Camera On</span>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($audit['notes'])): ?>
                        <div class="p-3.5 rounded-xl bg-slate-950/40 border border-white/5 text-xs text-slate-300 leading-relaxed font-sans">
                            <i class="fa-solid fa-quote-left text-[9px] text-slate-500 mr-1.5"></i>
                            <?= htmlspecialchars($audit['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
