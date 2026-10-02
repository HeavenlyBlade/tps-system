<?php
/**
 * Real-time game status endpoint for students
 * Returns current game state without page refresh
 */

require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isLoggedIn() || !isStudent()) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$game_id = (int)($_GET['game_id'] ?? 0);

if (!$game_id) {
    echo json_encode(['error' => 'No game ID']);
    exit;
}

// Get comprehensive game state (latest assigned question for this student)
$stmt = $pdo->prepare("
    SELECT 
        ge.status as student_status,
        g.id as game_id,
        g.name as game_name,
        g.status as game_status,
        g.question_end_time,
        g.started_at,
        g.current_question_order,
        g.total_questions,
        q.question_text,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        q.correct_answer,
        aq.student_answer
    FROM game_entries ge
    JOIN games g ON ge.game_id = g.id
    LEFT JOIN assigned_questions aq ON aq.game_id = ge.game_id AND aq.student_id = ge.student_id
        AND aq.id = (SELECT MAX(id) FROM assigned_questions WHERE game_id = ge.game_id AND student_id = ge.student_id)
    LEFT JOIN questions q ON aq.question_id = q.id
    WHERE ge.game_id = ? AND ge.student_id = ?
");
$stmt->execute([$game_id, $student_id]);
$data = $stmt->fetch();

if (!$data) {
    echo json_encode(['error' => 'Not in game', 'action' => 'redirect', 'url' => 'dashboard.php']);
    exit;
}

$response = [
    'success' => true,
    'game_id' => $data['game_id'],
    'game_name' => $data['game_name'],
    'game_status' => $data['game_status'],
    'student_status' => $data['student_status'],
    'question' => $data['question_text'],
    'option_a' => $data['option_a'],
    'option_b' => $data['option_b'],
    'option_c' => $data['option_c'],
    'option_d' => $data['option_d'],
    'current_question_order' => (int)($data['current_question_order'] ?? 0),
    'total_questions' => (int)($data['total_questions'] ?? 0),
    'timestamp' => time()
];

// Calculate remaining time if in active phase
if ($data['question_end_time']) {
    $end_time = strtotime($data['question_end_time']);
    $response['remaining_seconds'] = max(0, $end_time - time());
    $response['timer_ended'] = $response['remaining_seconds'] <= 0;
}

// Check for pair info if paired/chatting
if (in_array($data['student_status'], ['paired', 'chatting'])) {
    $stmt = $pdo->prepare("
        SELECT p.id as pair_id, p.pair_answer,
               CASE WHEN p.student1_id = ? THEN u2.full_name ELSE u1.full_name END as partner_name
        FROM pairs p
        JOIN users u1 ON p.student1_id = u1.id
        JOIN users u2 ON p.student2_id = u2.id
        WHERE p.game_id = ? AND (p.student1_id = ? OR p.student2_id = ?)
    ");
    $stmt->execute([$student_id, $game_id, $student_id, $student_id]);
    $pair = $stmt->fetch();
    
    if ($pair) {
        $response['pair_id'] = $pair['pair_id'];
        $response['partner_name'] = $pair['partner_name'];
        $response['has_submitted'] = !empty($pair['pair_answer']);
        $response['pair_answer'] = $pair['pair_answer'];
    }
}

// Determine what phase/screen to show
$response['phase'] = 'unknown';
$response['action'] = null;

switch ($data['student_status']) {
    case 'waiting':
        if ($data['game_status'] === 'active') {
            // Game started, move to question
            $response['phase'] = 'question';
            $response['action'] = 'show_question';
        } else {
            $response['phase'] = 'waiting';
        }
        break;
        
    case 'answering':
        if ($response['timer_ended'] ?? false) {
            // Timer ended, update status and move to pairing
            $pdo->prepare("UPDATE game_entries SET status = 'ready_for_pairing' WHERE game_id = ? AND student_id = ?")
                ->execute([$game_id, $student_id]);
            $response['phase'] = 'pairing_wait';
            $response['action'] = 'show_pairing';
            $response['student_status'] = 'ready_for_pairing';
        } else {
            $response['phase'] = 'question';
        }
        break;
        
    case 'ready_for_pairing':
        // Check if paired
        $stmt = $pdo->prepare("SELECT id FROM pairs WHERE game_id = ? AND (student1_id = ? OR student2_id = ?)");
        $stmt->execute([$game_id, $student_id, $student_id]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE game_entries SET status = 'paired' WHERE game_id = ? AND student_id = ?")
                ->execute([$game_id, $student_id]);
            $response['phase'] = 'chat';
            $response['action'] = 'show_chat';
            $response['student_status'] = 'paired';
        } else {
            $response['phase'] = 'pairing_wait';
        }
        break;
        
    case 'paired':
    case 'chatting':
        $response['phase'] = 'chat';
        break;
}

// Check if game ended
if ($data['game_status'] === 'ended') {
    $response['phase'] = 'ended';
    $response['action'] = 'game_ended';
}

echo json_encode($response);
