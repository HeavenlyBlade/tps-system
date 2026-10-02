<?php
require_once '../config.php';
if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$format  = $_GET['format'] ?? 'csv';
$type    = $_GET['type']    ?? 'summary';
$game_id = $_GET['game_id'] ?? 'all';

// Resolve game filter
$game_filter_sql    = '';
$game_filter_params = [];
$game_label         = 'All Games';

if ($game_id !== 'all' && is_numeric($game_id)) {
    $game_id = (int)$game_id;
    $game_filter_sql    = ' AND aq.game_id = ?';
    $game_filter_params = [$game_id];

    $gs = $pdo->prepare("SELECT name FROM games WHERE id = ?");
    $gs->execute([$game_id]);
    $gname = $gs->fetchColumn();
    $game_label = $gname ?: "Game #$game_id";
} else {
    $game_id = 'all';
}

// Sanitize game label for filename
$safe_label = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $game_label);
$filename   = 'tps_' . $type . '_' . $safe_label . '_' . date('Y-m-d');

if ($format !== 'csv') exit('Unsupported format');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

// ── SUMMARY ──────────────────────────────────────────────────────────────────
if ($type === 'summary') {

    $sql = "
        SELECT
            u.id, u.username, u.full_name,
            COUNT(DISTINCT aq.game_id)                                                        AS games_played,
            COUNT(aq.id)                                                                       AS total_questions,
            SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END)                               AS correct_answers,
            SUM(CASE WHEN aq.is_correct = 0 THEN 1 ELSE 0 END)                               AS wrong_answers,
            COUNT(CASE WHEN aq.is_correct IS NULL THEN 1 END)                                 AS pending_answers,
            ROUND(
                (SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) /
                 NULLIF(COUNT(CASE WHEN aq.is_correct IS NOT NULL THEN 1 END), 0)) * 100, 2)  AS score_percentage
        FROM users u
        LEFT JOIN assigned_questions aq ON u.id = aq.student_id
        WHERE u.role = 'student' $game_filter_sql
        GROUP BY u.id, u.username, u.full_name
        ORDER BY score_percentage DESC, correct_answers DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($game_filter_params);
    $students = $stmt->fetchAll();

    fputcsv($out, ['TPS SYSTEM — SCORE SUMMARY REPORT']);
    fputcsv($out, ['Game: ' . $game_label]);
    fputcsv($out, ['Generated: ' . date('F j, Y g:i A')]);
    fputcsv($out, []);
    fputcsv($out, ['Rank', 'Student ID', 'Username', 'Full Name', 'Games Played',
                   'Total Questions', 'Correct', 'Wrong', 'Pending', 'Score %']);

    $rank = 1;
    foreach ($students as $s) {
        fputcsv($out, [
            $rank++,
            $s['id'],
            $s['username'],
            $s['full_name'],
            $s['games_played'],
            $s['total_questions'],
            $s['correct_answers'],
            $s['wrong_answers'],
            $s['pending_answers'],
            $s['score_percentage'] ?? 'N/A',
        ]);
    }

// ── COMPARISON ───────────────────────────────────────────────────────────────
} elseif ($type === 'comparison') {

    $sql = "
        SELECT
            u.id, u.full_name,
            ROUND(
                (SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) /
                 NULLIF(COUNT(CASE WHEN aq.is_correct IS NOT NULL THEN 1 END), 0)) * 100, 2) AS post_test_score
        FROM users u
        LEFT JOIN assigned_questions aq ON u.id = aq.student_id
        WHERE u.role = 'student' $game_filter_sql
        GROUP BY u.id, u.full_name
        ORDER BY u.full_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($game_filter_params);
    $students = $stmt->fetchAll();

    fputcsv($out, ['TPS SYSTEM — PRE-TEST vs POST-TEST COMPARISON']);
    fputcsv($out, ['Game: ' . $game_label]);
    fputcsv($out, ['Generated: ' . date('F j, Y g:i A')]);
    fputcsv($out, ['Instructions: Fill in the Pre-Test Score column manually from your paper tests.']);
    fputcsv($out, []);
    fputcsv($out, ['Student ID', 'Full Name', 'Pre-Test Score (%)', 'Post-Test Score (TPS) (%)', 'Improvement (%)', 'Status']);

    $row_num = 7; // data starts at row 7 (after 5 header rows + 1 blank)
    foreach ($students as $s) {
        $post = $s['post_test_score'] ?? 0;
        $imp  = '=D' . $row_num . '-C' . $row_num;
        $stat = '=IF(E' . $row_num . '>0,"Improved",IF(E' . $row_num . '<0,"Declined","No Change"))';
        fputcsv($out, [
            $s['id'],
            $s['full_name'],
            '',    // blank — teacher fills manually
            $post,
            $imp,
            $stat,
        ]);
        $row_num++;
    }

// ── DETAILED ─────────────────────────────────────────────────────────────────
} else {

    $sql = "
        SELECT
            u.id AS student_id, u.username, u.full_name,
            g.name AS game_name,
            q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
            q.correct_answer,
            aq.student_answer,
            CASE
                WHEN aq.is_correct = 1 THEN 'Correct'
                WHEN aq.is_correct = 0 THEN 'Wrong'
                ELSE 'Pending'
            END AS result,
            aq.answered_at
        FROM users u
        JOIN assigned_questions aq ON u.id = aq.student_id
        JOIN games g ON aq.game_id = g.id
        JOIN questions q ON aq.question_id = q.id
        WHERE u.role = 'student' $game_filter_sql
        ORDER BY u.full_name, aq.answered_at
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($game_filter_params);

    fputcsv($out, ['TPS SYSTEM — DETAILED SCORE REPORT']);
    fputcsv($out, ['Game: ' . $game_label]);
    fputcsv($out, ['Generated: ' . date('F j, Y g:i A')]);
    fputcsv($out, []);
    fputcsv($out, ['Student ID', 'Username', 'Full Name', 'Game',
                   'Question', 'Option A', 'Option B', 'Option C', 'Option D',
                   'Correct Answer', 'Student Answer', 'Result', 'Answered At']);

    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['student_id'],
            $row['username'],
            $row['full_name'],
            $row['game_name'],
            $row['question_text'],
            $row['option_a'],
            $row['option_b'],
            $row['option_c'],
            $row['option_d'],
            $row['correct_answer'],
            $row['student_answer'],
            $row['result'],
            $row['answered_at'],
        ]);
    }
}

fclose($out);
exit;
