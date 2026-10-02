<?php

require_once '../../config.php';

// Prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if logged in
if (!isLoggedIn() || !isStudent()) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$pair_id = (int)($_GET['pair_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);

if (!$pair_id) {
    echo json_encode(['error' => 'No pair ID', 'messages' => []]);
    exit;
}

// Verify student is part of this pair
$stmt = $pdo->prepare("
    SELECT * FROM pairs 
    WHERE id = ? AND (student1_id = ? OR student2_id = ?)
");
$stmt->execute([$pair_id, $student_id, $student_id]);
$pair = $stmt->fetch();

if (!$pair) {
    echo json_encode(['error' => 'Not authorized for this chat', 'messages' => []]);
    exit;
}

// Get messages after last_id
$stmt = $pdo->prepare("
    SELECT m.*, u.full_name as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.pair_id = ? AND m.id > ?
    ORDER BY m.sent_at ASC
    LIMIT 50
");
$stmt->execute([$pair_id, $last_id]);
$messages = $stmt->fetchAll();

// Format timestamps
foreach ($messages as &$msg) {
    $msg['sent_at'] = date('h:i A', strtotime($msg['sent_at']));
}

echo json_encode([
    'messages' => $messages,
    'count' => count($messages),
    'timestamp' => time()
]);
