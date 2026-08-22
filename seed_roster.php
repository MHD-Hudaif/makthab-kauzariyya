<?php
/**
 * seed_roster.php
 * One-Click Migration & Seeder for Maktab Kauzariyya Verification Roster
 * Access via browser: https://makthab.kauzariyya.com/seed_roster.php
 * Or run via terminal: php seed_roster.php
 */

require_once __DIR__ . '/includes/db.php';

$isCli = (php_sapi_name() === 'cli');
$messages = [];

try {
    // 1. Ensure columns exist
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `gender` ENUM('male', 'female') NOT NULL DEFAULT 'male' AFTER `full_name`");
        $messages[] = "Added 'gender' column to users table.";
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `roster_id` INT UNSIGNED DEFAULT NULL AFTER `google_id`");
        $messages[] = "Added 'roster_id' column to users table.";
    } catch (PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE `students` ADD COLUMN `parent_phone` VARCHAR(30) DEFAULT NULL AFTER `parent_name`");
        $messages[] = "Added 'parent_phone' column to students table.";
    } catch (PDOException $e) {}

    // 2. Create verification_roster table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `verification_roster` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `type` ENUM('teacher', 'student') NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `assigned_teacher_name` VARCHAR(200) DEFAULT NULL,
            `is_claimed` TINYINT(1) DEFAULT 0,
            `claimed_user_id` INT UNSIGNED DEFAULT NULL,
            `claimed_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`type`),
            INDEX (`name`),
            INDEX (`is_claimed`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $messages[] = "Checked/Created 'verification_roster' table.";

    // 3. Clear existing unclaimed roster to prevent duplicate entries
    $pdo->exec("DELETE FROM `verification_roster` WHERE `is_claimed` = 0");

    // 4. Initial Roster Dataset (44 Teachers + 73 Students = 117 Total)
    $rosterData = [
        // --- 44 TEACHERS & USTADS ---
        ['teacher', 'NOOH USTAD', null],
        ['teacher', 'SUHAIL USTAD', null],
        ['teacher', 'ASHIF USTAD', null],
        ['teacher', 'USMAN USTAD', null],
        ['teacher', 'JAUHAR USTAD', null],
        ['teacher', 'ABBAS USTAD', null],
        ['teacher', 'ALTHAF ISMAIL USTAD', null],
        ['teacher', 'NIZAR USTAD', null],
        ['teacher', 'ABDULLA KP USTAD', null],
        ['teacher', 'ALTHAF KO USTAD', null],
        ['teacher', 'FAWAZ USTAD', null],
        ['teacher', 'ABDULRAHMAN USTAD', null],
        ['teacher', 'ANAS USTAD', null],
        ['teacher', 'MUHSIN USTAD', null],
        ['teacher', 'BILAL USTAD', null],
        ['teacher', 'ALTHAF MANARI USTAD', null],
        ['teacher', 'BILAL TDP USTAD', null],
        ['teacher', 'ABID USTAD', null],
        ['teacher', 'HASEEB USTAD', null],
        ['teacher', 'ANSIF USTAD', null],
        ['teacher', 'ABDULLA IBRAHIM USTAD', null],
        ['teacher', 'AMEEN USTAD', null],
        ['teacher', 'YASEEN USTAD', null],
        ['teacher', 'IHSAN USTAD', null],
        ['teacher', 'ADIL USTAD', null],
        ['teacher', 'ASHIQ USTAD', null],
        ['teacher', 'ALTHAF USTAD KOLLAM', null],
        ['teacher', 'THAHA USTAD', null],
        ['teacher', 'SUMAYYA TEACHER', null],
        ['teacher', 'FATHIMA TK TEACHER', null],
        ['teacher', 'ADHILA TEACHER', null],
        ['teacher', 'AYISHA HUSAIN TEACHER', null],
        ['teacher', 'RUSHDA TEACHER', null],
        ['teacher', 'MANSOORA TEACHER', null],
        ['teacher', 'AMINA TEACHER', null],
        ['teacher', 'FIDA TEACHER', null],
        ['teacher', 'HALEEMA ZUBAIR TEACHER', null],
        ['teacher', 'HANAN TEACHER', null],
        ['teacher', 'AYISHA KABEER TEACHER', null],
        ['teacher', 'AYISHA S TEACHER', null],
        ['teacher', 'HIBA TEACHER', null],
        ['teacher', 'FATHIMA ZAHRA TEACHER', null],
        ['teacher', 'HIBA SHERIN TEACHER', null],
        ['teacher', 'AISHA S TEACHER', null],

        // --- 73 STUDENTS & ASSIGNED TEACHERS ---
        ['student', 'MIRZA', 'NOOH USTAD'],
        ['student', 'SHANAVAS KOCHI', 'SUHAIL USTAD'],
        ['student', 'NOUFAL', 'ASHIF USTAD'],
        ['student', 'FAHEEM', 'ASHIF USTAD'],
        ['student', 'AMMAR WASEEM Pathanamthitta', 'USMAN USTAD'],
        ['student', 'SAINUDHEEN', 'JAUHAR USTAD'],
        ['student', 'SALMAN JAMSHEER', 'ABBAS USTAD'],
        ['student', 'SANEEJ', 'ALTHAF ISMAIL USTAD'],
        ['student', 'IZAN SANEEJ', 'NIZAR USTAD'],
        ['student', 'IMRAN', 'ASHIF USTAD'],
        ['student', 'IMAAD', 'ABDULLA KP USTAD'],
        ['student', 'FADHIL JANEESH', 'ALTHAF KO USTAD'],
        ['student', 'DHAKIR', 'FAWAZ USTAD'],
        ['student', 'TUFAIL', 'ABDULRAHMAN USTAD'],
        ['student', 'KHALID', 'ANAS USTAD'],
        ['student', 'ZAID', 'MUHSIN USTAD'],
        ['student', 'AMAN', 'MUHSIN USTAD'],
        ['student', 'YASEEN', 'MUHSIN USTAD'],
        ['student', 'HAMDAN', 'BILAL USTAD'],
        ['student', 'HISHAM ADIVADU BALIGAN', 'ALTHAF MANARI USTAD'],
        ['student', 'ADHIL BALIGAN', 'ALTHAF MANARI USTAD'],
        ['student', 'RAFI KOLLAM', 'FAWAZ USTAD'],
        ['student', 'AMEEN KANJIRAPALLY', 'BILAL TDP USTAD'],
        ['student', 'AMEEN PUNE', 'ABID USTAD'],
        ['student', 'PCS MUZAMMIL', 'BILAL TDP USTAD'],
        ['student', 'JAMSHEER MLV', 'FAWAZ USTAD'],
        ['student', 'ALTHAF TDP', 'HASEEB USTAD'],
        ['student', 'ANSHAD SAUDI', 'SUHAIL USTAD'],
        ['student', 'ANAS', 'ANSIF USTAD'],
        ['student', 'FARHAN BALIGAN', 'ANAS USTAD'],
        ['student', 'SHAN MUMBAI CL 9', 'ABDULLA IBRAHIM USTAD'],
        ['student', 'MINHAJ', 'AMEEN USTAD'],
        ['student', 'NOUFAL KOLLAM', 'ABDULLA IBRAHIM USTAD'],
        ['student', 'ASHEHAD', 'ABDULLA KP USTAD'],
        ['student', 'AFFAN MALA', 'YASEEN USTAD'],
        ['student', 'ISADUL ALI', null],
        ['student', 'MUAD', 'IHSAN USTAD'],
        ['student', 'YAHYA', 'ANSIF USTAD'],
        ['student', 'AMIR YAHYA', 'ABDULLA KP USTAD'],
        ['student', 'AHMAD ADIL', 'ADIL USTAD'],
        ['student', 'ALTHAF SALAHUDHEEN', 'ASHIQ USTAD'],
        ['student', 'ABDULLA ILYAS MLV', 'ANSIF USTAD'],
        ['student', 'UMARUL FAROOQ', 'ALTHAF USTAD KOLLAM'],
        ['student', 'MUZAMMIL FAIZAL', 'THAHA USTAD'],
        ['student', 'UMAR FAIZAL', 'THAHA USTAD'],
        ['student', 'SUMAYYA IBRAHIM', 'SUMAYYA TEACHER'],
        ['student', 'NISA ADHIL UMMA', 'FATHIMA TK TEACHER'],
        ['student', 'SWALIHA FATHIMA', 'SUMAYYA TEACHER'],
        ['student', 'ABINA', 'ADHILA TEACHER'],
        ['student', 'ISHAN', 'AYISHA HUSAIN TEACHER'],
        ['student', 'HAMRA', 'RUSHDA TEACHER'],
        ['student', 'BEEMA', 'AYISHA HUSAIN TEACHER'],
        ['student', 'SHAHANA ITHA', 'MANSOORA TEACHER'],
        ['student', 'FILLATH', 'AMINA TEACHER'],
        ['student', 'AJMIYA', 'MANSOORA TEACHER'],
        ['student', 'AIZA SANEEJ', 'FIDA TEACHER'],
        ['student', 'HAZINE SANEEJ', 'FIDA TEACHER'],
        ['student', 'HAAZIQ SANEEJ', 'RUSHDA TEACHER'],
        ['student', 'IZZA EDATHALA', 'HALEEMA ZUBAIR TEACHER'],
        ['student', 'HANNA', 'HANAN TEACHER'],
        ['student', 'ZULAIKHA', 'AYISHA KABEER TEACHER'],
        ['student', 'SUMAYYA', 'AYISHA S TEACHER'],
        ['student', 'HIBA', 'AYISHA S TEACHER'],
        ['student', 'AMNA DUBAI', 'HIBA TEACHER'],
        ['student', 'IHAAN', 'FATHIMA ZAHRA TEACHER'],
        ['student', 'SHAHANA SHAMSHEER', 'AYISHA KABEER TEACHER'],
        ['student', 'NADIYA', 'ADHILA TEACHER'],
        ['student', 'YAQUB', 'HALEEMA ZUBAIR TEACHER'],
        ['student', 'ASIYA NASRIN', 'AYISHA HUSSAIN TEACHER'],
        ['student', 'IMRAN JEDDAH', 'HIBA SHERIN TEACHER'],
        ['student', 'NAJUMA', 'HIBA SHERIN TEACHER'],
        ['student', 'FATHIMA ZAHRA CHRNLR', 'AISHA S TEACHER'],
        ['student', 'SUHAILA', null]
    ];

    $stmt = $pdo->prepare("INSERT INTO `verification_roster` (type, name, assigned_teacher_name) VALUES (?, ?, ?)");
    $teacherCount = 0;
    $studentCount = 0;

    foreach ($rosterData as $row) {
        $stmt->execute([$row[0], $row[1], $row[2]]);
        if ($row[0] === 'teacher') {
            $teacherCount++;
        } else {
            $studentCount++;
        }
    }

    $totalInDb = (int)$pdo->query("SELECT COUNT(*) FROM `verification_roster`")->fetchColumn();
    $messages[] = "Successfully seeded {$teacherCount} Teachers/Ustads and {$studentCount} Students.";
    $messages[] = "Total active roster entries in database: <strong>{$totalInDb}</strong>";

    $status = 'success';
} catch (Exception $e) {
    $status = 'error';
    $messages[] = "Error during execution: " . $e->getMessage();
}

if ($isCli) {
    echo "\n=== Maktab Kauzariyya Roster Seeder ===\n";
    foreach ($messages as $m) {
        echo strip_tags($m) . "\n";
    }
    echo "Status: " . strtoupper($status) . "\n\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Seeder — Maktab Kauzariyya</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6" style="background:#0e2e38; color:#ecf3d6;">
    <div class="max-w-lg w-full rounded-3xl p-8 border border-white/10 space-y-6 text-center" style="background:rgba(18,59,71,0.5); backdrop-filter:blur(20px);">
        <div class="w-16 h-16 rounded-full mx-auto flex items-center justify-center <?= $status === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' ?> text-2xl font-bold">
            <?= $status === 'success' ? '✓' : '✗' ?>
        </div>
        
        <div>
            <h1 class="text-2xl font-bold text-white">Database Migration & Seeder</h1>
            <p class="text-xs text-slate-400 mt-1">Manual Verification Roster Setup</p>
        </div>

        <div class="space-y-2 text-left text-xs bg-slate-900/60 p-4 rounded-2xl border border-white/5 font-mono">
            <?php foreach ($messages as $msg): ?>
                <div class="flex items-start gap-2">
                    <span class="text-emerald-400 font-bold">›</span>
                    <span><?= $msg ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pt-2 flex flex-col gap-3">
            <a href="verify" class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold py-3 rounded-xl text-xs transition block text-center">
                Go to Verification Page
            </a>
            <a href="coordinator/roster" class="w-full border border-white/10 hover:bg-white/5 text-slate-300 font-semibold py-3 rounded-xl text-xs transition block text-center">
                View Coordinator Roster Directory
            </a>
        </div>
    </div>
</body>
</html>
