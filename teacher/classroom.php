<?php
require_once __DIR__ . '/includes/header.php';

$selectedClassId = $_GET['class_id'] ?? '';
$classInfo = null;
$students = [];
$dateStr = date('Y-m-d');

// If a class is selected, verify teacher teaches it and load details
if (!empty($selectedClassId)) {
    $stmtClass = $pdo->prepare("
        SELECT c.*, co.name as course_name, co.code as course_code, co.total_targets, co.target_type
        FROM classes c
        JOIN class_teachers ct ON c.id = ct.class_id
        JOIN courses co ON c.course_id = co.id
        WHERE c.id = ? AND ct.teacher_id = ?
    ");
    $stmtClass->execute([$selectedClassId, $teacherId]);
    $classInfo = $stmtClass->fetch();

    if ($classInfo) {
        // Load students enrolled in this class
        $stmtStudents = $pdo->prepare("
            SELECT s.user_id, u.full_name, u.username, s.admission_no
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.class_id = ?
            ORDER BY u.full_name ASC
        ");
        $stmtStudents->execute([$selectedClassId]);
        $students = $stmtStudents->fetchAll();
    }
}

// --- POST HANDLING: SAVE PROGRESS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $classInfo) {
    try {
        $attendances = $_POST['attendance'] ?? []; // student_id => 1 (present) or 0 (absent)
        
        $pdo->beginTransaction();
        
        foreach ($students as $student) {
            $sid = $student['user_id'];
            $isPresent = isset($attendances[$sid]) && $attendances[$sid] == '1' ? 1 : 0;
            
            // Extract metrics based on course code
            $lesson = null;
            $juz = null;
            $page = null;
            $sabak_lines = 0;
            $sabqi_completed = 0;
            $manzil_submitted = 0;
            
            if ($isPresent) {
                if ($classInfo['course_code'] === 'TJ' || $classInfo['course_code'] === 'SPECIAL') {
                    $lesson = !empty($_POST['lesson'][$sid]) ? (int)$_POST['lesson'][$sid] : null;
                } elseif ($classInfo['course_code'] === 'NZ' || $classInfo['course_code'] === 'SHARIAH') {
                    $juz = !empty($_POST['juz'][$sid]) ? (int)$_POST['juz'][$sid] : null;
                    $page = !empty($_POST['page'][$sid]) ? (int)$_POST['page'][$sid] : null;
                } elseif ($classInfo['course_code'] === 'HZ' || $classInfo['course_code'] === 'HIFZ') {
                    $sabak_lines = !empty($_POST['sabak_lines'][$sid]) ? (int)$_POST['sabak_lines'][$sid] : 0;
                    $sabqi_completed = isset($_POST['sabqi_completed'][$sid]) ? 1 : 0;
                    $manzil_submitted = isset($_POST['manzil_submitted'][$sid]) ? 1 : 0;
                }
            }
            
            // Check if a log row already exists for this student on this day
            $chk = $pdo->prepare("SELECT id FROM progress_logs WHERE student_id = ? AND logged_date = ?");
            $chk->execute([$sid, $dateStr]);
            $logRow = $chk->fetch();
            
            if ($logRow) {
                // Update existing row
                $upd = $pdo->prepare("
                    UPDATE progress_logs 
                    SET is_present = ?, current_lesson = ?, current_juz = ?, current_page = ?, 
                        sabak_lines = ?, sabqi_completed = ?, manzil_submitted = ? 
                    WHERE id = ?
                ");
                $upd->execute([$isPresent, $lesson, $juz, $page, $sabak_lines, $sabqi_completed, $manzil_submitted, $logRow['id']]);
            } else {
                // Insert new row
                $ins = $pdo->prepare("
                    INSERT INTO progress_logs 
                    (student_id, class_id, logged_date, is_present, current_lesson, current_juz, current_page, sabak_lines, sabqi_completed, manzil_submitted) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$sid, $selectedClassId, $dateStr, $isPresent, $lesson, $juz, $page, $sabak_lines, $sabqi_completed, $manzil_submitted]);
            }
        }
        
        $pdo->commit();
        $_SESSION['msg_success'] = "Daily class progression logged successfully for " . date('d-M-Y') . ".";
        header("Location: classroom.php?class_id=" . $selectedClassId);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['msg_error'] = "Error logging progress: " . $e->getMessage();
        header("Location: classroom.php?class_id=" . $selectedClassId);
        exit;
    }
}

// Function to fetch yesterday's Hifz rules checklist
function get_hifz_validation($pdo, $studentId, $classDate) {
    // 1. Check yesterday's Sabqi
    $yesterday = date('Y-m-d', strtotime($classDate . ' -1 day'));
    $sabqiStmt = $pdo->prepare("SELECT sabqi_completed FROM progress_logs WHERE student_id = ? AND logged_date = ?");
    $sabqiStmt->execute([$studentId, $yesterday]);
    $sabqiVal = $sabqiStmt->fetch();
    
    // Default to true if no logs yesterday
    if ($sabqiVal && (int)$sabqiVal['sabqi_completed'] === 0) {
        return ['can_assign_sabak' => false, 'reason' => 'Sabqi (Juz Lesson) was not completed yesterday.'];
    }
    
    // 2. Check past 2 days of Manzil (Old Lesson) audio submissions
    $twoDaysAgo = date('Y-m-d', strtotime($classDate . ' -2 days'));
    $manzilStmt = $pdo->prepare("
        SELECT COUNT(*) FROM progress_logs 
        WHERE student_id = ? 
        AND logged_date BETWEEN ? AND ? 
        AND manzil_submitted = 0
    ");
    $manzilStmt->execute([$studentId, $twoDaysAgo, $yesterday]);
    $missingManzilCount = (int)$manzilStmt->fetchColumn();
    
    if ($missingManzilCount >= 2) {
        return ['can_assign_sabak' => false, 'reason' => 'Manzil (Old Lesson) voice note missing for 2 consecutive days.'];
    }
    
    return ['can_assign_sabak' => true];
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- ================= CLASSROOM MODULE ================= -->
<div class="space-y-8">
    
    <!-- Class Selector Dropdown -->
    <div class="glass-panel rounded-2xl p-6 flex flex-wrap gap-4 items-center justify-between border border-white/10">
        <div class="space-y-1">
            <h3 class="text-md font-bold text-white">Select Classroom</h3>
            <p class="text-xs text-slate-400">Choose one of your assigned classes to start the runner.</p>
        </div>
        <form action="" method="GET" class="w-full md:w-64">
            <select name="class_id" onchange="this.form.submit()" class="glass-input w-full px-4 py-2.5 rounded-xl text-xs font-semibold cursor-pointer">
                <option value="">-- Choose Class --</option>
                <?php foreach ($teacherClasses as $tc): ?>
                    <option value="<?= $tc['id'] ?>" <?= $selectedClassId == $tc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tc['name']) ?> (<?= htmlspecialchars($tc['course_code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!$classInfo): ?>
        <!-- Empty Class State -->
        <div class="glass-panel rounded-3xl p-12 text-center max-w-xl mx-auto space-y-4">
            <i class="fa-solid fa-chalkboard text-4xl text-slate-500"></i>
            <h4 class="text-lg font-bold text-white">No Class Selected</h4>
            <p class="text-xs text-slate-450 leading-relaxed">
                Please select a classroom from the dropdown list above to fetch the enrolled students list, start the daily countdown, and log curriculum progress details.
            </p>
        </div>
    <?php else: ?>
        <!-- Class Runner Console -->
        <div class="space-y-6">
            
            <!-- Dua Banner & Session Timer Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Dua Banner -->
                <div class="lg:col-span-2 glass-panel rounded-2xl p-6 border-l-4 border-l-emerald-400 flex flex-col justify-between space-y-3 relative overflow-hidden">
                    <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-widest">Opening Classroom Dua</span>
                    
                    <?php 
                    $isQuranic = in_array($classInfo['course_code'], ['TJ', 'NZ', 'HZ', 'HIFZ']);
                    if ($isQuranic):
                    ?>
                        <!-- Quran / Tajweed/Hifz Dua -->
                        <div class="space-y-2 text-right">
                            <p class="text-lg font-semibold text-emerald-350 leading-relaxed font-serif" dir="rtl">
                                اللَّهُمَّ اجْعَلِ الْقُرْآنَ رَبِيعَ قَلْبِي، وَنُورَ صَدْرِي، وَجَلَاءَ حُزْنِي، وَذَهَابَ هَمِّي.
                            </p>
                            <p class="text-[10px] text-slate-400 italic text-left">
                                "O Allah, make the Quran the spring of my heart, the light of my chest, the banisher of my sadness..."
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- General Studies Dua -->
                        <div class="space-y-2 text-right">
                            <p class="text-base font-semibold text-emerald-350 leading-relaxed font-serif" dir="rtl">
                                اللَّهُمَّ إِنَّا نَسْأَلُكَ عِلْمًا نَافِعًا، وَرِزْقًا طَيِّبًا، وَعَمَلًا مُتَقَبَّلًا. اللَّهُمَّ لَا سَهْلَ إِلَّا مَا جَعَلْتَهُ سَهْلًا.
                            </p>
                            <p class="text-[10px] text-slate-400 italic text-left">
                                "O Allah, we ask You for beneficial knowledge, good provision, and accepted deeds..."
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Session Timer Card -->
                <div class="glass-panel rounded-2xl p-6 border-t-2 border-t-blue-500/30 flex flex-col justify-between relative overflow-hidden text-center">
                    <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-widest">Daily Class Timer</span>
                    <div class="text-4xl font-black text-white font-mono my-2" id="class-countdown">01:00:00</div>
                    <span class="block text-[9px] text-slate-500 uppercase tracking-widest font-semibold">Recommended Session Limit</span>
                </div>
            </div>

            <!-- Student Roster Grid / Form -->
            <form action="" method="POST" class="space-y-6">
                <div class="glass-panel rounded-2xl overflow-hidden border border-white/10">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/15 bg-white/[0.02] text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    <th class="py-4 px-6 w-1/4">Student</th>
                                    <th class="py-4 px-6 w-24">Attendance</th>
                                    <th class="py-4 px-6">Daily Progress Log</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="3" class="py-8 px-6 text-center text-slate-500 font-medium">No students enrolled in this class yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <?php 
                                        $sid = $student['user_id'];
                                        
                                        // Load today's existing log values
                                        $stmtToday = $pdo->prepare("SELECT * FROM progress_logs WHERE student_id = ? AND logged_date = ?");
                                        $stmtToday->execute([$sid, $dateStr]);
                                        $todayLog = $stmtToday->fetch();
                                        
                                        $isPresent = $todayLog ? (int)$todayLog['is_present'] : 1;
                                        ?>
                                        <tr class="hover:bg-white/[0.01] transition-colors student-row-item">
                                            <!-- Student Info -->
                                            <td class="py-4 px-6">
                                                <div class="font-bold text-white"><?= htmlspecialchars($student['full_name']) ?></div>
                                                <div class="text-[10px] text-slate-400 font-mono">Adm: <?= htmlspecialchars($student['admission_no']) ?></div>
                                            </td>

                                            <!-- Attendance Toggle -->
                                            <td class="py-4 px-6">
                                                <select name="attendance[<?= $sid ?>]" onchange="toggleStudentProgressView(this, <?= $sid ?>)" class="glass-input px-2.5 py-1.5 rounded-lg text-xs font-semibold cursor-pointer">
                                                    <option value="1" <?= $isPresent === 1 ? 'selected' : '' ?>>Present</option>
                                                    <option value="0" <?= $isPresent === 0 ? 'selected' : '' ?>>Absent</option>
                                                </select>
                                            </td>

                                            <!-- Progression Metric Controls -->
                                            <td class="py-4 px-6" id="progress-box-<?= $sid ?>">
                                                <div class="progress-inputs-wrapper <?= $isPresent === 0 ? 'hidden opacity-30 pointer-events-none' : '' ?>">
                                                    
                                                    <!-- Category: Tajweed (Noorani Qaida Slider) -->
                                                    <?php if ($classInfo['course_code'] === 'TJ' || $classInfo['course_code'] === 'SPECIAL'): ?>
                                                        <div class="flex items-center gap-4 text-xs">
                                                            <span class="text-slate-350">Noorani Qaida Lesson:</span>
                                                            <select name="lesson[<?= $sid ?>]" class="glass-input px-3 py-1.5 rounded-lg text-xs">
                                                                <?php for ($l = 1; $l <= $classInfo['total_targets']; $l++): ?>
                                                                    <option value="<?= $l ?>" <?= ($todayLog && $todayLog['current_lesson'] == $l) ? 'selected' : '' ?>>Lesson <?= $l ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>

                                                    <!-- Category: Nazira (Juz & Page) -->
                                                    <?php elseif ($classInfo['course_code'] === 'NZ' || $classInfo['course_code'] === 'SHARIAH'): ?>
                                                        <div class="flex flex-wrap gap-4 text-xs items-center">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-slate-400">Juz:</span>
                                                                <input type="number" name="juz[<?= $sid ?>]" min="1" max="<?= $classInfo['total_targets'] ?>" value="<?= $todayLog ? htmlspecialchars($todayLog['current_juz']) : '1' ?>" class="glass-input w-16 px-3 py-1.5 rounded-lg text-xs">
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-slate-400">Page Number:</span>
                                                                <input type="number" name="page[<?= $sid ?>]" min="1" max="604" value="<?= $todayLog ? htmlspecialchars($todayLog['current_page']) : '' ?>" class="glass-input w-20 px-3 py-1.5 rounded-lg text-xs" placeholder="Page">
                                                            </div>
                                                        </div>

                                                    <!-- Category: Hifz (Sabak, Sabqi, Manzil validation) -->
                                                    <?php elseif ($classInfo['course_code'] === 'HZ' || $classInfo['course_code'] === 'HIFZ'): ?>
                                                        <?php 
                                                        $val = get_hifz_validation($pdo, $sid, $dateStr);
                                                        ?>
                                                        <div class="flex flex-wrap gap-6 text-xs items-center">
                                                            <!-- New Sabak (Lock Checked) -->
                                                            <?php if ($val['can_assign_sabak']): ?>
                                                                <div class="flex items-center gap-2">
                                                                    <span class="text-slate-450">New Lesson (Sabak) Lines:</span>
                                                                    <input type="number" name="sabak_lines[<?= $sid ?>]" min="0" max="30" value="<?= $todayLog ? (int)$todayLog['sabak_lines'] : '0' ?>" class="glass-input w-16 px-3 py-1.5 rounded-lg text-xs">
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="text-red-400 font-bold flex items-center gap-1.5">
                                                                    <i class="fa-solid fa-lock text-[10px]"></i>
                                                                    <span>New Sabak Locked: <?= htmlspecialchars($val['reason']) ?></span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Recitations Checkboxes -->
                                                            <label class="flex items-center gap-2 cursor-pointer select-none text-slate-300">
                                                                <input type="checkbox" name="sabqi_completed[<?= $sid ?>]" value="1" <?= (!$todayLog || $todayLog['sabqi_completed'] == 1) ? 'checked' : '' ?> class="w-4 h-4 rounded bg-white/5 border border-white/10 text-emerald-500 focus:ring-0">
                                                                <span>Juz Lesson (Sabqi) Recited</span>
                                                            </label>

                                                            <label class="flex items-center gap-2 cursor-pointer select-none text-slate-300">
                                                                <input type="checkbox" name="manzil_submitted[<?= $sid ?>]" value="1" <?= (!$todayLog || $todayLog['manzil_submitted'] == 1) ? 'checked' : '' ?> class="w-4 h-4 rounded bg-white/5 border border-white/10 text-emerald-500 focus:ring-0">
                                                                <span>Old Lesson (Manzil) Sent</span>
                                                            </label>
                                                        </div>

                                                    <!-- Category: Default (General studies review log) -->
                                                    <?php else: ?>
                                                        <div class="text-xs text-slate-500 italic">Core syllabus progress updates managed weekly.</div>
                                                    <?php endif; ?>

                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Submit Button -->
                <?php if (!empty($students)): ?>
                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-sm hover:shadow-lg hover:shadow-emerald-400/20 transition flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Save Daily Class Progress
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    // Timer functionality
    let timeRemaining = 3600; // 1 hour in seconds
    const timerDisplay = document.getElementById('class-countdown');
    
    if (timerDisplay) {
        const interval = setInterval(() => {
            if (timeRemaining <= 0) {
                clearInterval(interval);
                timerDisplay.textContent = "Time Elapsed";
                timerDisplay.classList.add('text-red-400');
                return;
            }
            timeRemaining--;
            let hours = Math.floor(timeRemaining / 3600);
            let minutes = Math.floor((timeRemaining % 3600) / 60);
            let seconds = timeRemaining % 60;
            
            timerDisplay.textContent = 
                (hours < 10 ? '0' : '') + hours + ':' +
                (minutes < 10 ? '0' : '') + minutes + ':' +
                (seconds < 10 ? '0' : '') + seconds;
        }, 1000);
    }

    // Toggle visibility of input fields when present/absent changes
    function toggleStudentProgressView(selectEl, studentId) {
        const box = document.getElementById('progress-box-' + studentId);
        if (!box) return;
        const wrapper = box.querySelector('.progress-inputs-wrapper');
        if (!wrapper) return;

        if (selectEl.value === '1') {
            wrapper.classList.remove('hidden', 'opacity-30', 'pointer-events-none');
        } else {
            wrapper.classList.add('hidden', 'opacity-30', 'pointer-events-none');
        }
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
