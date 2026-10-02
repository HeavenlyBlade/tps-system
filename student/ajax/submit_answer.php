<?php
/**
 * Submit pair answer endpoint
 */

require_once '../../config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isStudent()) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$student_id = $_SESSION['user_id'];
$pair_id = (int)($_POST['pair_id'] ?? 0);
$answer = strtoupper(trim($_POST['answer'] ?? ''));

if (!$pair_id || !$answer) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

// Validate answer is A, B, C, or D
if (!in_array($answer, ['A', 'B', 'C', 'D'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid answer format']);
    exit;
}

// Verify student is in this pair
$stmt = $pdo->prepare("SELECT * FROM pairs WHERE id = ? AND (student1_id = ? OR student2_id = ?)");
$stmt->execute([$pair_id, $student_id, $student_id]);
$pair = $stmt->fetch();

if (!$pair) {
    echo json_encode(['success' => false, 'error' => 'Invalid pair']);
    exit;
}

// Check if already submitted
if (!empty($pair['pair_answer'])) {
    echo json_encode(['success' => false, 'error' => 'Answer already submitted']);
    exit;
}

// Submit the answer to pairs table
$stmt = $pdo->prepare("UPDATE pairs SET pair_answer = ?, answered_at = NOW() WHERE id = ?");
$stmt->execute([$answer, $pair_id]);

// Sync pair answer back to BOTH students' assigned_questions rows
// and auto-grade by comparing against the correct answer
// Uses the latest assigned question (sequential pretest support)
$stmt = $pdo->prepare("
    SELECT p.game_id, p.student1_id, p.student2_id,
           aq.question_id, q.correct_answer
    FROM pairs p
    JOIN assigned_questions aq ON aq.game_id = p.game_id AND aq.student_id = p.student1_id
        AND aq.id = (SELECT MAX(id) FROM assigned_questions WHERE game_id = p.game_id AND student_id = p.student1_id)
    JOIN questions q ON aq.question_id = q.id
    WHERE p.id = ?
");
$stmt->execute([$pair_id]);
$pair_data = $stmt->fetch();

if ($pair_data) {
    $is_correct = ($answer === $pair_data['correct_answer']) ? 1 : 0;

    // Update both students' assigned_questions with the pair answer and grade
    $update = $pdo->prepare("
        UPDATE assigned_questions
        SET student_answer = ?, is_correct = ?, answered_at = NOW()
        WHERE game_id = ? AND student_id = ? AND question_id = ?
    ");
    $update->execute([$answer, $is_correct, $pair_data['game_id'], $pair_data['student1_id'], $pair_data['question_id']]);
    $update->execute([$answer, $is_correct, $pair_data['game_id'], $pair_data['student2_id'], $pair_data['question_id']]);
}

echo json_encode(['success' => true, 'message' => 'Answer submitted']);
