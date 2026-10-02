<?php
/**
 * Real-time game status endpoint for teachers
 * Returns current game state with all student info
 */

require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isLoggedIn() || !isTeacher()) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$game_id = (int)($_GET['game_id'] ?? 0);

if (!$game_id) {
    echo json_encode(['error' => 'No game ID']);
    exit;
}

// Get game info
$stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch();

if (!$game) {
    echo json_encode(['error' => 'Game not found']);
    exit;
}

// Get students
$stmt = $pdo->prepare("
    SELECT ge.*, u.full_name, aq.student_answer, aq.is_correct 
    FROM game_entries ge
    JOIN users u ON ge.student_id = u.id
    LEFT JOIN assigned_questions aq ON aq.game_id = ge.game_id AND aq.student_id = ge.student_id
        AND aq.id = (SELECT MAX(id) FROM assigned_questions WHERE game_id = ge.game_id AND student_id = ge.student_id)
    WHERE ge.game_id = ? 
    ORDER BY ge.status, u.full_name
");
$stmt->execute([$game_id]);
$students = $stmt->fetchAll();

// Count by status
$counts = ['waiting' => 0, 'answering' => 0, 'ready_for_pairing' => 0, 'paired' => 0, 'chatting' => 0];
foreach ($students as $s) {
    if (isset($counts[$s['status']])) {
        $counts[$s['status']]++;
    }
}

// Get pairs with BOTH students' answers and correct answer (current question only)
$stmt = $pdo->prepare("
    SELECT p.*, 
           u1.full_name as student1_name, 
           u2.full_name as student2_name,
           aq1.student_answer as student1_answer,
           aq2.student_answer as student2_answer,
           q.correct_answer,
           (SELECT COUNT(*) FROM messages WHERE pair_id = p.id) as message_count
    FROM pairs p
    JOIN users u1 ON p.student1_id = u1.id
    JOIN users u2 ON p.student2_id = u2.id
    LEFT JOIN assigned_questions aq1 ON aq1.game_id = p.game_id AND aq1.student_id = p.student1_id
        AND aq1.id = (SELECT MAX(id) FROM assigned_questions WHERE game_id = p.game_id AND student_id = p.student1_id)
    LEFT JOIN assigned_questions aq2 ON aq2.game_id = p.game_id AND aq2.student_id = p.student2_id
        AND aq2.id = (SELECT MAX(id) FROM assigned_questions WHERE game_id = p.game_id AND student_id = p.student2_id)
    LEFT JOIN questions q ON aq1.question_id = q.id
    WHERE p.game_id = ?
    ORDER BY p.created_at
");
$stmt->execute([$game_id]);
$pairs = $stmt->fetchAll();

// Calculate remaining time
$remaining_seconds = null;
if ($game['question_end_time']) {
    $remaining_seconds = max(0, strtotime($game['question_end_time']) - time());
}

echo json_encode([
    'success' => true,
    'game' => [
        'id' => $game['id'],
        'name' => $game['name'],
        'status' => $game['status'],
        'started_at' => $game['started_at'],
        'question_end_time' => $game['question_end_time'],
        'remaining_seconds' => $remaining_seconds,
        'current_question_order' => (int)($game['current_question_order'] ?? 0),
        'total_questions' => (int)($game['total_questions'] ?? 0)
    ],
    'students' => $students,
    'counts' => $counts,
    'pairs' => $pairs,
    'total_students' => count($students),
    'total_pairs' => count($pairs),
    'timestamp' => time()
]);
