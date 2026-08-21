<?php
/**
 * db.php — Database Connection
 *
 * Zero-dependency database connection matching the Musabaqa website setup.
 * Loads variables from the root .env file and sets up the PDO connection.
 */

// ── Zero-Dependency env() Loader (From Musabaqa env.php) ──────────────────────
if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $value = trim($value);

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string)$value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $baseUrl = env('APP_BASE_URL', 'auto');
        if ($baseUrl === 'auto') {
            $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
            $projRoot = str_replace('\\', '/', dirname(__DIR__));
            
            if ($docRoot !== '' && str_starts_with($projRoot, $docRoot)) {
                $subDir = substr($projRoot, strlen($docRoot));
                $baseUrl = rtrim($subDir, '/\\');
            } else {
                $baseUrl = '';
            }
        }
        return '/' . ltrim(rtrim($baseUrl, '/') . '/' . ltrim($path, '/'), '/');
    }
}

// Load .env from the root folder
load_env_file(dirname(__DIR__) . '/.env');

// ── Environment Detection ────────────────────────────────────────────────────
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.local')
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.test')
               || php_sapi_name() === 'cli';

// ── Credential Selection (Like Musabaqa Website) ──────────────────────────────
if ($isLocalhost) {
    // Laragon Local Environment
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

    $DB_HOST = env('DB_HOST', "127.0.0.1;port={$localPort}");
    $DB_NAME = env('DB_DATABASE', 'makthab_kauzariyya');
    $DB_USER = env('DB_USERNAME', 'root');
    $DB_PASS = env('DB_PASSWORD', ''); // Local default root password
} else {
    // Bluehost Production Environment
    $DB_HOST = env('DB_HOST', 'localhost');
    $DB_NAME = env('DB_DATABASE', 'ensplpmy_makthab_kauzariyya');
    $DB_USER = env('DB_USERNAME', 'ensplpmy_hudaif');
    $DB_PASS = env('DB_PASSWORD', 'abd527-157');
}

// ── PDO Connection ───────────────────────────────────────────────────────────
$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    // Locally auto-create database if it doesn't exist yet
    if ($e->getCode() == 1049 && !empty($DB_NAME)) {
        try {
            $temp = new PDO("mysql:host={$DB_HOST};charset=utf8mb4", $DB_USER, $DB_PASS);
            $temp->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $pdoOptions);
        } catch (PDOException $ex) {
            die("Database connection failed. Please create a database named '{$DB_NAME}': " . $ex->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// ── Auto-Migration ───────────────────────────────────────────────────────────
// Users / core tables
try {
    $pdo->query("SELECT 1 FROM `users` LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/install_users.sql'));

        // Seed default users
        $defaultUsers = [
            ['admin',       'admin@kauzariyya.com',       'admin123',       'Administrator',        'admin'],
            ['supervisor',  'supervisor@kauzariyya.com',  'supervisor123',  'Head Supervisor',      'supervisor'],
            ['coordinator', 'coordinator@kauzariyya.com', 'coordinator123', 'Academic Coordinator', 'coordinator'],
            ['teacher',     'teacher@kauzariyya.com',     'teacher123',     'Sample Teacher',       'teacher'],
            ['student',     'student@kauzariyya.com',     'student123',     'Sample Student',       'student'],
        ];

        $stmt = $pdo->prepare("INSERT INTO `users` (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
        foreach ($defaultUsers as $u) {
            $stmt->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_DEFAULT), $u[3], $u[4]]);
            $uid = $pdo->lastInsertId();

            if ($u[4] === 'student') {
                $pdo->prepare("INSERT INTO `students` (user_id, admission_no, parent_name, class_id, dob) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$uid, 'ADM-2026-001', 'John Doe Sr.', null, '2015-05-15']);
            } elseif ($u[4] === 'teacher') {
                $pdo->prepare("INSERT INTO `teachers` (user_id, specialisation, date_of_joining) VALUES (?, ?, ?)")
                    ->execute([$uid, 'Quran Recitation & Tajweed', '2020-06-01']);
            }
        }
    }
}

// Ensure place column exists on users table
try {
    $pdo->query("SELECT place FROM `users` LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `place` varchar(150) DEFAULT NULL AFTER `phone`");
    } catch (PDOException $ex) {}
}

// Ensure google_id column exists on users table
try {
    $pdo->query("SELECT google_id FROM `users` LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `google_id` varchar(255) DEFAULT NULL UNIQUE AFTER `status`");
    } catch (PDOException $ex) {}
}

// Ensure role column is varchar to support multiple comma-separated roles
try {
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` varchar(150) NOT NULL");
} catch (PDOException $e) {}

// Ensure profile_photo column exists on users table
try {
    $pdo->query("SELECT profile_photo FROM `users` LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `profile_photo` varchar(255) DEFAULT NULL AFTER `google_id`");
    } catch (PDOException $ex) {}
}

// Coordinator / academic tables
try {
    $pdo->query("SELECT 1 FROM `courses` LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/install_coordinator.sql'));
    }
}

// Ensure dynamic targets columns exist on courses table
try {
    $pdo->query("SELECT target_type FROM `courses` LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE `courses` ADD COLUMN `target_type` ENUM('lesson', 'juz') NOT NULL DEFAULT 'lesson', ADD COLUMN `total_targets` INT NOT NULL DEFAULT 1");
        $pdo->exec("UPDATE `courses` SET `target_type` = 'lesson', `total_targets` = 28 WHERE `code` = 'TJ' OR `code` = 'SPECIAL'");
        $pdo->exec("UPDATE `courses` SET `target_type` = 'juz', `total_targets` = 5 WHERE `code` = 'NZ' OR `code` = 'SHARIAH'");
        $pdo->exec("UPDATE `courses` SET `target_type` = 'juz', `total_targets` = 30 WHERE `code` = 'HZ' OR `code` = 'HIFZ'");
    } catch (PDOException $ex) {}
}

// Progress logs, class audits, and leaves tables
try {
    $pdo->query("SELECT 1 FROM `progress_logs` LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
        if (file_exists(__DIR__ . '/../database/install_progress.sql')) {
            $sql = file_get_contents(__DIR__ . '/../database/install_progress.sql');
            $queries = explode(';', $sql);
            foreach ($queries as $q) {
                $q = trim($q);
                if ($q !== '') {
                    try {
                        $pdo->exec($q);
                    } catch (PDOException $ex) {}
                }
            }
        }
    }
}

// Weekly reports table
try {
    $pdo->query("SELECT 1 FROM `weekly_reports` LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `weekly_reports` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `teacher_id` INT UNSIGNED NOT NULL,
                    `class_id` INT UNSIGNED NOT NULL,
                    `report_week` DATE NOT NULL,
                    `topics_covered` TEXT,
                    `slow_learners` TEXT,
                    `attendance_remarks` TEXT,
                    `general_feedback` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (PDOException $ex) {}
    }
}
