<?php
/**
 * db.php — Database Connection
 *
 * Loads credentials strictly from environment variables (either system env or .env file)
 * and initializes the global $pdo instance.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env variables into $_ENV and getenv() from the project root
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Helper to get environment variables with fallback
if (!function_exists('env_val')) {
    function env_val(string $key, mixed $default = null): mixed {
        $value = getenv($key) ?: ($_ENV[$key] ?? null);
        if ($value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

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

    // Local default settings, configurable via local .env if desired
    $DB_HOST = env_val('DB_HOST', "127.0.0.1;port={$localPort}");
    $DB_NAME = env_val('DB_DATABASE', 'makthab_kauzariyya');
    $DB_USER = env_val('DB_USERNAME', 'root');
    $DB_PASS = env_val('DB_PASSWORD', '');
} else {
    // Bluehost Production Environment
    $DB_HOST = env_val('DB_HOST', 'localhost');
    $DB_NAME = env_val('DB_DATABASE', 'ensplpmy_makthab_kauzariyya');
    $DB_USER = env_val('DB_USERNAME', 'ensplpmy_hudaif');
    $DB_PASS = env_val('DB_PASSWORD', 'abd527-157');
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

// Coordinator / academic tables
try {
    $pdo->query("SELECT 1 FROM `courses` LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/install_coordinator.sql'));
    }
}
