<?php
/**
 * db.php — Database Connection
 * Loads credentials from .env (root-level) and creates a global $pdo instance.
 * Supports auto-detection of localhost vs production, auto-create DB, and auto-migration.
 */

// ── .env Loader ─────────────────────────────────────────────────────────────
// A minimal, dependency-free .env parser. Reads KEY=VALUE lines, ignores
// blank lines and lines starting with # (comments).
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        return; // .env is optional; fallback values apply
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        // Split on first '=' only
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        // Strip optional surrounding quotes
        if (preg_match('/^(["\'])(.+)\1$/', $value, $m)) {
            $value = $m[2];
        }
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// Load from project root (two levels up if called from a sub-folder)
loadEnv(__DIR__ . '/.env');

// ── Environment Detection ────────────────────────────────────────────────────
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.local')
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.test')
               || php_sapi_name() === 'cli';

// ── Credential Selection ─────────────────────────────────────────────────────
if ($isLocalhost) {
    // Auto-detect Laragon MySQL port (3306 or 3307)
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

    $DB_HOST = ($_ENV['LOCAL_DB_HOST'] ?? '127.0.0.1') . ";port={$localPort}";
    $DB_USER = $_ENV['LOCAL_DB_USER'] ?? 'root';
    $DB_PASS = $_ENV['LOCAL_DB_PASS'] ?? '';
    $DB_NAME = $_ENV['LOCAL_DB_NAME'] ?? 'makthab_kauzariyya';
} else {
    $DB_HOST = $_ENV['PROD_DB_HOST'] ?? 'localhost';
    $DB_USER = $_ENV['PROD_DB_USER'] ?? '';
    $DB_PASS = $_ENV['PROD_DB_PASS'] ?? '';
    $DB_NAME = $_ENV['PROD_DB_NAME'] ?? '';
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
    // Locally: auto-create database if it doesn't exist yet
    if ($isLocalhost && $e->getCode() == 1049) {
        try {
            $temp = new PDO("mysql:host={$DB_HOST};charset=utf8mb4", $DB_USER, $DB_PASS);
            $temp->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $pdoOptions);
        } catch (PDOException $ex) {
            die("Database connection failed. Please create a database named '{$DB_NAME}' in Laragon: " . $ex->getMessage());
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
        $pdo->exec(file_get_contents(__DIR__ . '/database/install_users.sql'));

        // Seed default users
        $defaultUsers = [
            ['admin',       'admin@kauzariyya.com',       'admin123',       'Administrator',       'admin'],
            ['supervisor',  'supervisor@kauzariyya.com',  'supervisor123',  'Head Supervisor',     'supervisor'],
            ['coordinator', 'coordinator@kauzariyya.com', 'coordinator123', 'Academic Coordinator','coordinator'],
            ['teacher',     'teacher@kauzariyya.com',     'teacher123',     'Sample Teacher',      'teacher'],
            ['student',     'student@kauzariyya.com',     'student123',     'Sample Student',      'student'],
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
        $pdo->exec(file_get_contents(__DIR__ . '/database/install_coordinator.sql'));
    }
}
