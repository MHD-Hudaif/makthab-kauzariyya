<?php
/**
 * deploy.php — Secure 1-Click Deployment Script
 *
 * Pulls the latest code from GitHub master branch directly.
 * Bookmark this URL in your browser for 1-click updates:
 * https://makthab.kauzariyya.com/deploy.php?secret=Kauzariyya_Deploy_Secret_2026
 */

define('DEPLOY_SECRET', 'Kauzariyya_Deploy_Secret_2026');

if (($_GET['secret'] ?? '') !== DEPLOY_SECRET) {
    http_response_code(403);
    die('Forbidden: Invalid secret token.');
}

header('Content-Type: text/plain');
echo "=== Maktab Kauzariyya 1-Click Deployment ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Run git pull
echo "Executing Git Pull...\n";
$output = shell_exec("git pull origin master 2>&1");

if ($output === null) {
    echo "Error: shell_exec is disabled on this server or git command failed.\n";
} else {
    echo $output . "\n";
}

echo "=== Deployment Process Finished ===\n";
