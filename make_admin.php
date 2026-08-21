<?php
/**
 * make_admin.php
 * Temporary script to register or update hudaifmhd0@gmail.com as Admin.
 * Delete this file after running it once.
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain');
echo "=== Maktab Kauzariyya Admin Promotion Script ===\n";

$targetEmail = 'hudaifmhd0@gmail.com';
$targetUsername = 'hudaif';
$targetName = 'MHD Hudaif';

try {
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$targetEmail]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user to admin role
        $upd = $pdo->prepare("UPDATE users SET role = 'admin', status = 'active' WHERE id = ?");
        $upd->execute([$user['id']]);
        echo "Success: Existing user account '{$targetEmail}' has been promoted to Admin role.\n";
    } else {
        // Insert new admin user
        $randomPassword = bin2hex(random_bytes(8));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
        
        $ins = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
        $ins->execute([$targetUsername, $targetEmail, $passwordHash, $targetName]);
        
        echo "Success: Created new admin user profile for '{$targetEmail}'.\n";
        echo "Username: {$targetUsername}\n";
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
echo "=============================================\n";
