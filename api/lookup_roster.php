<?php
/**
 * api/lookup_roster.php
 * Autocomplete / Live search endpoint for Old Student / Teacher Verification Roster
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$type = trim($_GET['type'] ?? '');
$query = trim($_GET['query'] ?? $_GET['term'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, type, name, assigned_teacher_name, is_claimed FROM verification_roster WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    echo json_encode($item ?: ['error' => 'Record not found']);
    exit;
}

if (!in_array($type, ['teacher', 'student'], true)) {
    echo json_encode(['error' => 'Invalid type specified. Must be teacher or student.']);
    exit;
}

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, type, name, assigned_teacher_name, is_claimed 
        FROM verification_roster 
        WHERE type = ? AND name LIKE ? 
        ORDER BY is_claimed ASC, name ASC 
        LIMIT 15
    ");
    $stmt->execute([$type, '%' . $query . '%']);
    $results = $stmt->fetchAll();
    echo json_encode($results ?: []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
