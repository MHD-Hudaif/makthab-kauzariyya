<?php
// Auto-detect environment (localhost/Laragon vs. Production Bluehost)
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) 
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.local')
               || str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.test');

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
