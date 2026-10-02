<?php

require_once '../../config.php';

// Prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if logged in
if (!isLoggedIn() || !isStudent()) {
    echo json_encode(['error' => 'Not authorized', 'redirect' => '../auth/login.php']);
    exit;
}

$student_id = $_SESSION['user_id'];
$game_id = (int)($_GET['game_id'] ?? 0);

if (!$game_id) {
    echo json_encode(['error' => 'No game ID', 'redirect' => 'dashboard.php']);
    exit;
}

// Get current status
$stmt = $pdo->prepare("
    SELECT ge.status, g.status as game_status, g.question_end_time
    FROM game_entries ge
    JOIN games g ON ge.game_id = g.id
    WHERE ge.game_id = ? AND ge.student_id = ?
");
$stmt->execute([$game_id, $student_id]);
$entry = $stmt->fetch();

if (!$entry) {
    echo json_encode(['error' => 'Not in game', 'redirect' => 'dashboard.php']);
    exit;
}

$response = [
    'status' => $entry['status'],
    'game_status' => $entry['game_status'],
    'redirect' => null,
    'timestamp' => time()
];

// Determine redirect based on status
switch ($entry['status']) {
    case 'waiting':
        if ($entry['game_status'] === 'active') {
            $response['redirect'] = 'question.php?game_id=' . $game_id;
        }
        break;
        
    case 'answering':
        // Check if timer has expired
        if ($entry['question_end_time']) {
            $end_time = strtotime($entry['question_end_time']);
            $remaining = max(0, $end_time - time());
            $response['remaining_seconds'] = $remaining;
            
            if ($remaining <= 0) {
                // Timer expired, update status
                $stmt = $pdo->prepare("
                    UPDATE game_entries SET status = 'ready_for_pairing' 
                    WHERE game_id = ? AND student_id = ?
                ");
                $stmt->execute([$game_id, $student_id]);
                $response['redirect'] = 'pairing_wait.php?game_id=' . $game_id;
            }
        }
        break;
        
    case 'ready_for_pairing':
        // Check if paired
        $stmt = $pdo->prepare("
            SELECT id FROM pairs 
            WHERE game_id = ? AND (student1_id = ? OR student2_id = ?)
        ");
        $stmt->execute([$game_id, $student_id, $student_id]);
        if ($stmt->fetch()) {
            // Update status to paired
            $pdo->prepare("UPDATE game_entries SET status = 'paired' WHERE game_id = ? AND student_id = ?")->execute([$game_id, $student_id]);
            $response['redirect'] = 'chat.php?game_id=' . $game_id;
        }
        break;
        
    case 'paired':
    case 'chatting':
        $response['redirect'] = 'chat.php?game_id=' . $game_id;
        break;
}

// Check if game ended
if ($entry['game_status'] === 'ended') {
    $response['redirect'] = 'dashboard.php';
    $response['message'] = 'Game has ended';
}

echo json_encode($response);
