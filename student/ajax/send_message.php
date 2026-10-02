<?php

require_once '../../config.php';

// Prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check if logged in
if (!isLoggedIn() || !isStudent()) {
    echo json_encode(['error' => 'Not authorized', 'success' => false]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method', 'success' => false]);
    exit;
}

$student_id = $_SESSION['user_id'];
$pair_id = (int)($_POST['pair_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

// Validate input
if (!$pair_id) {
    echo json_encode(['error' => 'Invalid pair ID', 'success' => false]);
    exit;
}

if (empty($message)) {
    echo json_encode(['error' => 'Message cannot be empty', 'success' => false]);
    exit;
}

// Limit message length
if (strlen($message) > 1000) {
    echo json_encode(['error' => 'Message too long (max 1000 characters)', 'success' => false]);
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
    echo json_encode(['error' => 'Not authorized for this chat', 'success' => false]);
    exit;
}

try {
    // Insert the message
    $stmt = $pdo->prepare("
        INSERT INTO messages (pair_id, sender_id, message) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$pair_id, $student_id, $message]);
    
    echo json_encode([
        'success' => true,
        'message_id' => $pdo->lastInsertId(),
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to send message', 'success' => false]);
}
