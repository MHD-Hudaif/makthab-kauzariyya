<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];
$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? $jsonBody['action'] ?? '';

try {
    require_once __DIR__ . '/db.php';

    if ($action === 'get_data') {
        // 1. Fetch Stats
        $stats = [
            'total_students' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
            'total_teachers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
            'total_supervisors' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn(),
            'total_courses' => (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
            'total_classes' => (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn()
        ];

        // 2. Fetch Courses
        $coursesList = $pdo->query("SELECT id, name, code, description FROM courses ORDER BY name ASC")->fetchAll();

        // 3. Fetch Classes
        $classesList = $pdo->query("
            SELECT c.id, c.course_id, c.name, c.type, u.full_name as supervisor_name, u.email as supervisor_email 
            FROM classes c 
            LEFT JOIN users u ON c.supervisor_id = u.id
            ORDER BY c.name ASC
        ")->fetchAll();

        // 4. Fetch Class-Teacher mapping
        $classTeachers = $pdo->query("
            SELECT ct.class_id, u.id as teacher_id, u.full_name as teacher_name 
            FROM class_teachers ct 
            JOIN users u ON ct.teacher_id = u.id
            ORDER BY u.full_name ASC
        ")->fetchAll();

        // 5. Fetch Students
        $studentsList = $pdo->query("
            SELECT s.id, s.user_id, s.class_id, u.full_name as name, u.email, u.phone, s.admission_no, s.parent_name, s.dob, c.name as class_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN classes c ON s.class_id = c.id
            ORDER BY u.full_name ASC
        ")->fetchAll();

        // 6. Fetch Teachers List
        $teachersList = $pdo->query("
            SELECT u.id as teacher_id, u.full_name as name, u.email, u.phone, t.specialisation
            FROM users u
            LEFT JOIN teachers t ON u.id = t.user_id
            WHERE u.role = 'teacher'
            ORDER BY u.full_name ASC
        ")->fetchAll();

        // 7. Fetch Supervisors List
        $supervisorsList = $pdo->query("
            SELECT u.id as supervisor_id, u.full_name as name, u.email, u.phone
            FROM users u
            WHERE u.role = 'supervisor'
            ORDER BY u.full_name ASC
        ")->fetchAll();

        // Group / format data
        $classesByCourse = [];
        foreach ($classesList as $c) {
            // Find teachers for this class
            $teachers = [];
            foreach ($classTeachers as $ct) {
                if ($ct['class_id'] == $c['id']) {
                    $teachers[] = [
                        'id' => (int)$ct['teacher_id'],
                        'name' => $ct['teacher_name']
                    ];
                }
            }

            // Find student count
            $studentCount = 0;
            foreach ($studentsList as $s) {
                if ($s['class_id'] == $c['id']) {
                    $studentCount++;
                }
            }

            $classesByCourse[$c['course_id']][] = [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'type' => $c['type'],
                'supervisor' => $c['supervisor_name'],
                'student_count' => $studentCount,
                'teachers' => $teachers
            ];
        }

        $formattedCourses = [];
        foreach ($coursesList as $course) {
            $formattedCourses[] = [
                'id' => (int)$course['id'],
                'name' => $course['name'],
                'code' => $course['code'],
                'description' => $course['description'],
                'classes' => $classesByCourse[$course['id']] ?? []
            ];
        }

        $formattedTeachers = [];
        foreach ($teachersList as $teacher) {
            $taughtClasses = [];
            foreach ($classTeachers as $ct) {
                if ($ct['teacher_id'] == $teacher['teacher_id']) {
                    // Find class name
                    foreach ($classesList as $c) {
                        if ($c['id'] == $ct['class_id']) {
                            $taughtClasses[] = [
                                'class_id' => (int)$c['id'],
                                'class_name' => $c['name']
                            ];
                        }
                    }
                }
            }
            $formattedTeachers[] = [
                'id' => (int)$teacher['teacher_id'],
                'name' => $teacher['name'],
                'email' => $teacher['email'],
                'phone' => $teacher['phone'],
                'specialisation' => $teacher['specialisation'],
                'classes' => $taughtClasses
            ];
        }

        $formattedSupervisors = [];
        foreach ($supervisorsList as $sv) {
            $supervisedClasses = [];
            foreach ($classesList as $c) {
                if ($c['supervisor_name'] == $sv['name']) {
                    $supervisedClasses[] = [
                        'class_id' => (int)$c['id'],
                        'class_name' => $c['name']
                    ];
                }
            }
            $formattedSupervisors[] = [
                'id' => (int)$sv['supervisor_id'],
                'name' => $sv['name'],
                'email' => $sv['email'],
                'phone' => $sv['phone'],
                'classes' => $supervisedClasses
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'stats' => $stats,
                'courses' => $formattedCourses,
                'teachers' => $formattedTeachers,
                'supervisors' => $formattedSupervisors,
                'students' => $studentsList
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Default action fallback
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing action parameter.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'API Server Error: ' . $e->getMessage()
    ]);
}
