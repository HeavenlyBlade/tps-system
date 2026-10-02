<?php
/**
 * Teacher game action endpoint
 * Handles start, pause, pairing, restart actions
 */

require_once '../../config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isTeacher()) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$game_id = (int)($_POST['game_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$game_id || !$action) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

// Get game
$stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch();

if (!$game) {
    echo json_encode(['success' => false, 'error' => 'Game not found']);
    exit;
}

try {
    switch ($action) {
        case 'start_game':
            // Get all questions ordered by week then id (Week 1 → Week 8)
            $stmt = $pdo->query("
                SELECT id FROM questions
                ORDER BY CAST(REPLACE(week, 'Week ', '') AS UNSIGNED), id
            ");
            $all_question_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($all_question_ids)) {
                echo json_encode(['success' => false, 'error' => 'No questions available. Please add questions first.']);
                exit;
            }

            // Get ALL students in this game
            $stmt = $pdo->prepare("SELECT student_id FROM game_entries WHERE game_id = ?");
            $stmt->execute([$game_id]);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($students)) {
                echo json_encode(['success' => false, 'error' => 'No students have joined yet.']);
                exit;
            }

            $total_questions = count($all_question_ids);
            $current_order = 1; // Start at question 1
            $question_id = $all_question_ids[0];

            // Update game status, timer, and question tracking
            $pdo->prepare("
                UPDATE games SET status = 'active', started_at = NOW(),
                    question_end_time = ?,
                    current_question_order = ?,
                    total_questions = ?
                WHERE id = ?
            ")->execute([
                date('Y-m-d H:i:s', time() + QUESTION_TIME_SECONDS),
                $current_order,
                $total_questions,
                $game_id
            ]);

            // Assign first question to every student
            $ins = $pdo->prepare("INSERT INTO assigned_questions (game_id, student_id, question_id) VALUES (?, ?, ?)");
            $upd = $pdo->prepare("UPDATE game_entries SET status = 'answering' WHERE game_id = ? AND student_id = ?");
            foreach ($students as $sid) {
                $ins->execute([$game_id, $sid, $question_id]);
                $upd->execute([$game_id, $sid]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Game started! Question 1 of ' . $total_questions . '. ' . count($students) . ' students are now in Think phase.'
            ]);
            break;
            
        case 'end_timer':
            // Move all answering students to ready for pairing
            $pdo->prepare("UPDATE game_entries SET status = 'ready_for_pairing' WHERE game_id = ? AND status = 'answering'")
                ->execute([$game_id]);
            $pdo->prepare("UPDATE games SET status = 'pairing', question_end_time = NOW() WHERE id = ?")
                ->execute([$game_id]);
            
            echo json_encode(['success' => true, 'message' => 'Timer ended! Students moved to Pair phase.']);
            break;
            
        case 'run_pairing':
            // Get students ready for pairing
            $stmt = $pdo->prepare("SELECT student_id FROM game_entries WHERE game_id = ? AND status = 'ready_for_pairing' ORDER BY RAND()");
            $stmt->execute([$game_id]);
            $ready = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($ready) < 2) {
                echo json_encode(['success' => false, 'error' => 'Need at least 2 students ready for pairing.']);
                exit;
            }
            
            // Create pairs
            $pairsCreated = 0;
            for ($i = 0; $i < count($ready) - 1; $i += 2) {
                $pdo->prepare("INSERT INTO pairs (game_id, student1_id, student2_id) VALUES (?, ?, ?)")
                    ->execute([$game_id, $ready[$i], $ready[$i + 1]]);
                $pdo->prepare("UPDATE game_entries SET status = 'paired' WHERE game_id = ? AND student_id IN (?, ?)")
                    ->execute([$game_id, $ready[$i], $ready[$i + 1]]);
                $pairsCreated++;
            }
            
            $pdo->prepare("UPDATE games SET status = 'chatting' WHERE id = ?")->execute([$game_id]);
            
            $leftover = count($ready) % 2;
            $msg = "Created {$pairsCreated} pairs!";
            if ($leftover) {
                $msg .= " (1 student waiting for partner)";
            }
            
            echo json_encode(['success' => true, 'message' => $msg]);
            break;
            
        case 'next_question':
            // Advance to the next question in order, keeping pairs intact
            $stmt = $pdo->prepare("SELECT current_question_order, total_questions FROM games WHERE id = ?");
            $stmt->execute([$game_id]);
            $gdata = $stmt->fetch();

            $next_order = ($gdata['current_question_order'] ?? 0) + 1;
            $total = $gdata['total_questions'] ?? 0;

            if ($next_order > $total) {
                echo json_encode(['success' => false, 'error' => 'All questions have been completed. Please end the game.']);
                exit;
            }

            // Get ordered question list
            $stmt = $pdo->query("
                SELECT id FROM questions
                ORDER BY CAST(REPLACE(week, 'Week ', '') AS UNSIGNED), id
            ");
            $all_question_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $question_id = $all_question_ids[$next_order - 1];

            // Reset pair answers so pairs can answer the new question
            $pdo->prepare("UPDATE pairs SET pair_answer = NULL, answered_at = NULL WHERE game_id = ?")
                ->execute([$game_id]);

            // Move all students back to answering
            $pdo->prepare("UPDATE game_entries SET status = 'answering' WHERE game_id = ?")
                ->execute([$game_id]);

            // Assign new question to all students
            $stmt = $pdo->prepare("SELECT student_id FROM game_entries WHERE game_id = ?");
            $stmt->execute([$game_id]);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $ins = $pdo->prepare("INSERT INTO assigned_questions (game_id, student_id, question_id) VALUES (?, ?, ?)");
            foreach ($students as $sid) {
                $ins->execute([$game_id, $sid, $question_id]);
            }

            // Update game to active with new timer and question order
            $pdo->prepare("
                UPDATE games SET status = 'active',
                    question_end_time = ?,
                    current_question_order = ?
                WHERE id = ?
            ")->execute([
                date('Y-m-d H:i:s', time() + QUESTION_TIME_SECONDS),
                $next_order,
                $game_id
            ]);

            echo json_encode([
                'success' => true,
                'message' => "Question {$next_order} of {$total} started! Students are now in Think phase."
            ]);
            break;

        case 'end_game':
            // Sync any unsynced pair answers to assigned_questions before ending
            // Handles multiple questions per pair (sequential pretest)
            $stmt = $pdo->prepare("
                SELECT p.id, p.game_id, p.student1_id, p.student2_id, p.pair_answer,
                       aq.question_id, q.correct_answer, aq.id as aq_id
                FROM pairs p
                JOIN assigned_questions aq ON aq.game_id = p.game_id AND aq.student_id = p.student1_id
                JOIN questions q ON aq.question_id = q.id
                WHERE p.game_id = ? AND p.pair_answer IS NOT NULL AND aq.student_answer IS NULL
            ");
            $stmt->execute([$game_id]);
            $answered_pairs = $stmt->fetchAll();

            $update = $pdo->prepare("
                UPDATE assigned_questions
                SET student_answer = ?, is_correct = ?, answered_at = COALESCE(answered_at, NOW())
                WHERE game_id = ? AND student_id = ? AND question_id = ? AND student_answer IS NULL
            ");
            foreach ($answered_pairs as $ap) {
                $correct = ($ap['pair_answer'] === $ap['correct_answer']) ? 1 : 0;
                $update->execute([$ap['pair_answer'], $correct, $ap['game_id'], $ap['student1_id'], $ap['question_id']]);
                $update->execute([$ap['pair_answer'], $correct, $ap['game_id'], $ap['student2_id'], $ap['question_id']]);
            }

            $pdo->prepare("UPDATE games SET status = 'ended' WHERE id = ?")->execute([$game_id]);
            echo json_encode(['success' => true, 'message' => 'Game ended and results saved.']);
            break;

        case 'restart_game':
            // Delete all game data cleanly
            $pdo->prepare("DELETE FROM messages WHERE pair_id IN (SELECT id FROM pairs WHERE game_id = ?)")->execute([$game_id]);
            $pdo->prepare("DELETE FROM pairs WHERE game_id = ?")->execute([$game_id]);
            $pdo->prepare("DELETE FROM assigned_questions WHERE game_id = ?")->execute([$game_id]);
            $pdo->prepare("UPDATE game_entries SET status = 'waiting' WHERE game_id = ?")->execute([$game_id]);
            $pdo->prepare("UPDATE games SET status = 'waiting', started_at = NULL, question_end_time = NULL, current_question_order = 0, total_questions = 0 WHERE id = ?")
                ->execute([$game_id]);
            
            echo json_encode(['success' => true, 'message' => 'Game restarted! All progress has been reset.']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
