<?php
require_once __DIR__ . '/includes/header.php';

// --- POST SUBMISSIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. SAVE COURSE
        if ($action === 'save_course') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $description = trim($_POST['description'] ?? '');

            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO courses (name, code, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $description]);
                $_SESSION['msg_success'] = "Course '{$name}' created successfully.";
            } else {
                $stmt = $pdo->prepare("UPDATE courses SET name = ?, code = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $code, $description, $id]);
                $_SESSION['msg_success'] = "Course '{$name}' updated successfully.";
            }
            header("Location: courses");
            exit;
        }

        // 2. SAVE CLASS
        if ($action === 'save_class') {
            $id = $_POST['id'] ?? '';
            $course_id = $_POST['course_id'] ?? null;
            $supervisor_id = !empty($_POST['supervisor_id']) ? $_POST['supervisor_id'] : null;
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'regular';

            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO classes (course_id, supervisor_id, name, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$course_id, $supervisor_id, $name, $type]);
                $_SESSION['msg_success'] = "Class '{$name}' created successfully.";
            } else {
                $stmt = $pdo->prepare("UPDATE classes SET course_id = ?, supervisor_id = ?, name = ?, type = ? WHERE id = ?");
                $stmt->execute([$course_id, $supervisor_id, $name, $type, $id]);
                $_SESSION['msg_success'] = "Class '{$name}' updated successfully.";
            }
            header("Location: courses");
            exit;
        }

        // 3. DELETE ENTITY
        if ($action === 'delete_entity') {
            $entity_type = $_POST['entity_type'] ?? '';
            $id = $_POST['id'] ?? '';

            if ($entity_type === 'course') {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Course deleted successfully.";
            } elseif ($entity_type === 'class') {
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['msg_success'] = "Class deleted successfully.";
            }
            header("Location: courses");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['msg_error'] = $e->getMessage();
        header("Location: courses");
        exit;
    }
}

// --- FETCH DATA ---
$courses = $pdo->query("SELECT * FROM courses ORDER BY name ASC")->fetchAll();

$classes = $pdo->query("
    SELECT c.*, u.full_name as supervisor_name, u.email as supervisor_email 
    FROM classes c 
    LEFT JOIN users u ON c.supervisor_id = u.id
    ORDER BY c.name ASC
")->fetchAll();

$classTeachersRaw = $pdo->query("
    SELECT ct.class_id, u.id as teacher_id, u.full_name as teacher_name, u.email as teacher_email 
    FROM class_teachers ct 
    JOIN users u ON ct.teacher_id = u.id
    ORDER BY u.full_name ASC
")->fetchAll();

$studentsRaw = $pdo->query("
    SELECT s.*, u.full_name as student_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id IS NOT NULL
")->fetchAll();

$supervisorsRaw = $pdo->query("
    SELECT u.id as supervisor_id, u.full_name as supervisor_name
    FROM users u
    WHERE u.role = 'supervisor'
    ORDER BY u.full_name ASC
")->fetchAll();

// Grouping
$classesByCourse = [];
foreach ($classes as $class) {
    $classesByCourse[$class['course_id']][] = $class;
}

$teachersByClass = [];
foreach ($classTeachersRaw as $ct) {
    $teachersByClass[$ct['class_id']][] = $ct;
}

$studentsByClass = [];
foreach ($studentsRaw as $student) {
    $studentsByClass[$student['class_id']][] = $student;
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Controls bar -->
<div class="flex flex-wrap gap-4 items-center justify-between">
    <h3 class="text-lg font-bold text-white">Academic Curriculum</h3>
    <div class="flex gap-3">
        <button onclick="openCourseModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-white/5 hover:bg-white/10 text-emerald-400 border border-emerald-500/20 rounded-xl transition">
            <i class="fa-solid fa-folder-plus"></i> Add Course
        </button>
        <button onclick="openClassModal()" class="flex items-center gap-2 px-4 py-2 text-xs font-bold bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 rounded-xl hover:shadow-lg hover:shadow-emerald-400/20 transition">
            <i class="fa-solid fa-circle-plus"></i> Add Class
        </button>
    </div>
</div>

<!-- Courses Tree Grid -->
<div class="space-y-6">
    <?php foreach ($courses as $course): ?>
        <div class="glass-panel rounded-2xl p-6 space-y-4">
            <div class="flex justify-between items-start border-b border-white/10 pb-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-md"><?= htmlspecialchars($course['code']) ?></span>
                        <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($course['name']) ?></h3>
                    </div>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($course['description']) ?></p>
                </div>
                
                <!-- Edit / Delete Course -->
                <div class="flex items-center gap-2">
                    <button onclick="openCourseModal(<?= htmlspecialchars(json_encode($course)) ?>)" class="p-2 text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg border border-white/8 transition" title="Edit Course">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button onclick="confirmDelete('course', <?= $course['id'] ?>)" class="p-2 text-xs font-semibold bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded-lg border border-red-500/10 transition" title="Delete Course">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>

            <!-- Classes Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php 
                $courseClasses = $classesByCourse[$course['id']] ?? [];
                if (empty($courseClasses)): 
                ?>
                    <p class="text-sm text-slate-500 py-2 col-span-2">No classes created under this course yet.</p>
                <?php else: ?>
                    <?php foreach ($courseClasses as $class): ?>
                        <div class="glass-card rounded-xl p-5 border border-white/5 space-y-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="text-lg font-bold text-white"><?= htmlspecialchars($class['name']) ?></h4>
                                    <span class="inline-block text-[10px] font-semibold tracking-wider uppercase px-2 py-0.5 rounded <?= $class['type'] === 'individual' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30' ?> mt-1">
                                        <?= htmlspecialchars($class['type']) ?> Class
                                    </span>
                                </div>
                                
                                <div class="flex gap-2">
                                    <!-- Edit / Delete Class -->
                                    <button onclick="openClassModal(<?= htmlspecialchars(json_encode($class)) ?>)" class="p-1.5 text-xs bg-white/5 hover:bg-white/10 text-slate-300 rounded border border-white/8 transition" title="Edit Class">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button onclick="confirmDelete('class', <?= $class['id'] ?>)" class="p-1.5 text-xs bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded border border-red-500/10 transition" title="Delete Class">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-xs pt-1 border-t border-white/5">
                                <div>
                                    <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider">Supervisor</span>
                                    <span class="text-slate-200 font-medium flex items-center gap-1.5 mt-0.5"><i class="fa-solid fa-user-tie text-[10px] opacity-70"></i><?= htmlspecialchars($class['supervisor_name'] ?? 'Not Assigned') ?></span>
                                </div>
                                <div>
                                    <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider">Enrolled Students</span>
                                    <span class="text-slate-200 font-medium flex items-center gap-1.5 mt-0.5"><i class="fa-solid fa-user-graduate text-[10px] opacity-70"></i><?= count($studentsByClass[$class['id']] ?? []) ?> Students</span>
                                </div>
                            </div>

                            <!-- Teachers Taught Many-to-Many -->
                            <div>
                                <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider mb-1.5 flex items-center gap-1"><i class="fa-solid fa-chalkboard-user text-[10px] opacity-70"></i>Teachers</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php 
                                    $classTeachers = $teachersByClass[$class['id']] ?? [];
                                    if (empty($classTeachers)): 
                                    ?>
                                        <span class="text-xs text-slate-500 font-medium">No teachers assigned</span>
                                    <?php else: ?>
                                        <?php foreach ($classTeachers as $teacher): ?>
                                            <span class="text-xs px-2 py-0.5 rounded bg-white/5 border border-white/10 text-slate-300">
                                                <?= htmlspecialchars($teacher['teacher_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Students inside Class -->
                            <div>
                                <span class="block text-[9px] text-slate-450 font-semibold uppercase tracking-wider mb-2 flex items-center gap-1"><i class="fa-solid fa-users text-[10px] opacity-70"></i>Student List</span>
                                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                    <?php 
                                    $classStudents = $studentsByClass[$class['id']] ?? [];
                                    if (empty($classStudents)): 
                                    ?>
                                        <p class="text-xs text-slate-500 font-medium">No students enrolled</p>
                                    <?php else: ?>
                                        <?php foreach ($classStudents as $student): ?>
                                            <div class="flex justify-between items-center bg-white/[0.02] border border-white/5 px-2.5 py-1.5 rounded-lg text-xs">
                                                <span class="font-medium text-slate-350"><?= htmlspecialchars($student['student_name']) ?></span>
                                                <span class="text-slate-400 font-mono text-[9px]"><?= htmlspecialchars($student['admission_no']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ================= MODALS ================= -->
<!-- Course Modal -->
<div id="modal-course" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
    <div class="w-full max-w-md glass-panel rounded-3xl p-8 relative space-y-6">
        <div class="flex justify-between items-center">
            <h3 id="course-modal-title" class="text-xl font-bold text-white">Add New Course</h3>
            <button onclick="closeModal('course')" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form action="courses" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_course">
            <input type="hidden" name="id" id="course_id">
            
            <div class="space-y-1.5">
                <label for="course_name" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Course Name</label>
                <input type="text" name="name" id="course_name" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Hifz Academy" required>
            </div>
            
            <div class="space-y-1.5">
                <label for="course_code" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Course Code</label>
                <input type="text" name="code" id="course_code" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. HIFZ" required>
            </div>

            <div class="space-y-1.5">
                <label for="course_description" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Description</label>
                <textarea name="description" id="course_description" rows="3" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="Brief details about the course..."></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('course')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs hover:shadow-lg hover:shadow-emerald-400/20 transition">Save Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Class Modal -->
<div id="modal-class" class="fixed inset-0 z-50 hidden flex items-center justify-center p-6 bg-slate-950/60 backdrop-blur-sm">
    <div class="w-full max-w-md glass-panel rounded-3xl p-8 relative space-y-6">
        <div class="flex justify-between items-center">
            <h3 id="class-modal-title" class="text-xl font-bold text-white">Add New Class</h3>
            <button onclick="closeModal('class')" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form action="courses" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_class">
            <input type="hidden" name="id" id="class_id">
            
            <div class="space-y-1.5">
                <label for="class_name" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Class Name</label>
                <input type="text" name="name" id="class_name" class="glass-input w-full px-4 py-3 rounded-xl text-sm" placeholder="e.g. Class A" required>
            </div>

            <div class="space-y-1.5">
                <label for="class_course_id" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Course Assignment</label>
                <select name="course_id" id="class_course_id" class="glass-input w-full px-4 py-3 rounded-xl text-sm" required>
                    <option value="" disabled selected>Select Parent Course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="class_supervisor_id" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Class Supervisor</label>
                <select name="supervisor_id" id="class_supervisor_id" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    <option value="">No Supervisor (Unassigned)</option>
                    <?php foreach ($supervisorsRaw as $s): ?>
                        <option value="<?= $s['supervisor_id'] ?>"><?= htmlspecialchars($s['supervisor_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="class_type" class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Class Type</label>
                <select name="type" id="class_type" class="glass-input w-full px-4 py-3 rounded-xl text-sm">
                    <option value="regular">Regular Class (Multiple Students)</option>
                    <option value="individual">Individual Class (One-on-One)</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('class')" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-400 to-blue-500 text-slate-950 font-bold text-xs hover:shadow-lg hover:shadow-emerald-400/20 transition">Save Class</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Course Modal Manager
    function openCourseModal(data = null) {
        const modal = document.getElementById('modal-course');
        const title = document.getElementById('course-modal-title');
        const form = modal.querySelector('form');
        
        form.reset();
        document.getElementById('course_id').value = '';

        if (data) {
            title.innerText = 'Edit Course';
            document.getElementById('course_id').value = data.id;
            document.getElementById('course_name').value = data.name;
            document.getElementById('course_code').value = data.code;
            document.getElementById('course_description').value = data.description;
        } else {
            title.innerText = 'Add New Course';
        }
        modal.classList.remove('hidden');
    }

    // Class Modal Manager
    function openClassModal(data = null) {
        const modal = document.getElementById('modal-class');
        const title = document.getElementById('class-modal-title');
        const form = modal.querySelector('form');
        
        form.reset();
        document.getElementById('class_id').value = '';

        if (data) {
            title.innerText = 'Edit Class';
            document.getElementById('class_id').value = data.id;
            document.getElementById('class_name').value = data.name;
            document.getElementById('class_type').value = data.type;
            document.getElementById('class_course_id').value = data.course_id;
            document.getElementById('class_supervisor_id').value = data.supervisor_id || '';
        } else {
            title.innerText = 'Add New Class';
        }
        modal.classList.remove('hidden');
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
