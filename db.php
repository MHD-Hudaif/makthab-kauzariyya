<?php
// Auto-detect environment (localhost/Laragon vs. Production Bluehost)
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) 
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.local')
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.test')
               || php_sapi_name() === 'cli';

if ($isLocalhost) {
    // Localhost Development Credentials (detect Laragon port 3306 or 3307)
    $localPort = 3306;
    $fp = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 0.05);
    if (!$fp) {
        $fp2 = @fsockopen('127.0.0.1', 3307, $errno, $errstr, 0.05);
        if ($fp2) {
            fclose($fp2);
            $localPort = 3307;
        }
    } else {
        fclose($fp);
    }
    
    $DB_HOST = "127.0.0.1;port={$localPort}";
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'makthab_kauzariyya';
} else {
    // Production (Bluehost)
    $DB_HOST = 'localhost';
    $DB_USER = 'ensplpmy_hudaif';
    $DB_PASS = 'abd527-157';
    $DB_NAME = 'ensplpmy_makthab_kauzariyya';
}

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If database doesn't exist locally, catch it and explain to the user
    if ($isLocalhost && $e->getCode() == 1049) {
        try {
            // Attempt to create the database locally if it doesn't exist
            $temp_pdo = new PDO("mysql:host={$DB_HOST};charset=utf8mb4", $DB_USER, $DB_PASS);
            $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            // Retry connection
            $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $ex) {
            die("Database connection failed. Please create a database named '{$DB_NAME}' in Laragon: " . $ex->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Auto-migrate tables if not exists
try {
    $pdo->query("SELECT 1 FROM `users` LIMIT 1");
} catch (PDOException $e) {
    // Table 'users' doesn't exist (SQLSTATE 42S02)
    if ($e->getCode() == '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
        $sql = file_get_contents(__DIR__ . '/database/install_users.sql');
        $pdo->exec($sql);

        // Seed Default Users
        $defaultUsers = [
            ['admin', 'admin@kauzariyya.com', 'admin123', 'Administrator', 'admin'],
            ['supervisor', 'supervisor@kauzariyya.com', 'supervisor123', 'Head Supervisor', 'supervisor'],
            ['coordinator', 'coordinator@kauzariyya.com', 'coordinator123', 'Academic Coordinator', 'coordinator'],
            ['teacher', 'teacher@kauzariyya.com', 'teacher123', 'Sample Teacher', 'teacher'],
            ['student', 'student@kauzariyya.com', 'student123', 'Sample Student', 'student']
        ];

        $stmt = $pdo->prepare("INSERT INTO `users` (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
        
        foreach ($defaultUsers as $user) {
            $hashedPassword = password_hash($user[2], PASSWORD_DEFAULT);
            $stmt->execute([$user[0], $user[1], $hashedPassword, $user[3], $user[4]]);
            
            // Get inserted user id
            $userId = $pdo->lastInsertId();
            
            // Seed role-specific profiles
            if ($user[4] === 'student') {
                $studentStmt = $pdo->prepare("INSERT INTO `students` (user_id, admission_no, parent_name, class_id, dob) VALUES (?, ?, ?, ?, ?)");
                $studentStmt->execute([$userId, 'ADM-2026-001', 'John Doe Sr.', null, '2015-05-15']);
            } elseif ($user[4] === 'teacher') {
                $teacherStmt = $pdo->prepare("INSERT INTO `teachers` (user_id, specialisation, date_of_joining) VALUES (?, ?, ?)");
                $teacherStmt->execute([$userId, 'Quran Recitation & Tajweed', '2020-06-01']);
            }
        }
    }
}

// Auto-migrate coordinator schema if not exists
try {
    $pdo->query("SELECT 1 FROM `courses` LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
        $sql = file_get_contents(__DIR__ . '/database/install_coordinator.sql');
        $pdo->exec($sql);
    }
}

