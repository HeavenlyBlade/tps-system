<?php
require_once '../config.php';
if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$game_filter = (int)($_GET['game_id'] ?? 0);

$stmt = $pdo->query("SELECT id, name FROM games ORDER BY created_at DESC");
$games = $stmt->fetchAll();

// Overall rankings across all games (or filtered by game)
$rank_sql = "
    SELECT 
        u.id,
        u.full_name,
        COUNT(aq.id) as total_answered,
        SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) as correct,
        SUM(CASE WHEN aq.is_correct = 0 THEN 1 ELSE 0 END) as wrong,
        SUM(CASE WHEN aq.is_correct IS NULL THEN 1 ELSE 0 END) as pending,
        ROUND((SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(CASE WHEN aq.is_correct IS NOT NULL THEN 1 END), 0)) * 100, 1) as score_pct
    FROM users u
    JOIN assigned_questions aq ON u.id = aq.student_id
    WHERE u.role = 'student'
";
$rank_params = [];
if ($game_filter) {
    $rank_sql .= " AND aq.game_id = ?";
    $rank_params[] = $game_filter;
}
$rank_sql .= " GROUP BY u.id, u.full_name HAVING total_answered > 0 ORDER BY score_pct DESC, correct DESC";
$stmt = $pdo->prepare($rank_sql);
$stmt->execute($rank_params);
$rankings = $stmt->fetchAll();

// Per-question answer breakdown (correct answer vs student answers)
$q_sql = "
    SELECT 
        q.id as question_id,
        q.question_text,
        q.option_a, q.option_b, q.option_c, q.option_d,
        q.correct_answer,
        COUNT(aq.id) as total_submissions,
        SUM(CASE WHEN aq.student_answer = q.correct_answer THEN 1 ELSE 0 END) as correct_count,
        SUM(CASE WHEN aq.student_answer = 'A' THEN 1 ELSE 0 END) as picked_a,
        SUM(CASE WHEN aq.student_answer = 'B' THEN 1 ELSE 0 END) as picked_b,
        SUM(CASE WHEN aq.student_answer = 'C' THEN 1 ELSE 0 END) as picked_c,
        SUM(CASE WHEN aq.student_answer = 'D' THEN 1 ELSE 0 END) as picked_d
    FROM questions q
    JOIN assigned_questions aq ON q.id = aq.question_id
    WHERE aq.student_answer IS NOT NULL
";
$q_params = [];
if ($game_filter) {
    $q_sql .= " AND aq.game_id = ?";
    $q_params[] = $game_filter;
}
$q_sql .= " GROUP BY q.id ORDER BY correct_count ASC";
$stmt = $pdo->prepare($q_sql);
$stmt->execute($q_params);
$question_stats = $stmt->fetchAll();

// Class summary
$total_graded = array_sum(array_column($rankings, 'correct')) + array_sum(array_column($rankings, 'wrong'));
$total_correct = array_sum(array_column($rankings, 'correct'));
$class_avg = $total_graded > 0 ? round(($total_correct / $total_graded) * 100, 1) : 0;

$pageTitle = 'Rankings';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Rankings</li>
        </ol>
    </nav>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-trophy-fill text-warning me-2"></i>Student Rankings</h1>
            <p class="text-muted mb-0">Scores compared against correct answers</p>
        </div>
        <a href="export_scores.php?type=summary&format=csv<?php echo $game_filter ? '&game_id='.$game_filter : ''; ?>" class="btn btn-success shadow-sm">
            <i class="bi bi-download me-1"></i>Export
        </a>
    </div>
</div>

<!-- Game Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <select name="game_id" class="form-select form-select-sm">
                <option value="0">All Games</option>
                <?php foreach ($games as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $game_filter == $g['id'] ? 'selected' : ''; ?>>
                        <?php echo escape($g['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-filter"></i></button>
        </form>
    </div>
</div>

<!-- Class Summary -->
<?php if (!empty($rankings)): ?>
<div class="row g-2 mb-3">
    <div class="col-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-radius: 12px;">
                <div class="fs-3 fw-bold"><?php echo count($rankings); ?></div>
                <small class="fw-semibold">Students Ranked</small>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3" style="background: linear-gradient(135deg, #bfdbfe, #93c5fd); border-radius: 12px;">
                <div class="fs-3 fw-bold"><?php echo $class_avg; ?>%</div>
                <small class="fw-semibold">Class Average</small>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3" style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 12px;">
                <div class="fs-3 fw-bold"><?php echo $total_correct; ?>/<?php echo $total_graded; ?></div>
                <small class="fw-semibold">Total Correct</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Rankings Table -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header py-3">
        <div class="d-flex align-items-center justify-content-between">
            <span class="fw-bold"><i class="bi bi-list-ol me-2"></i>Leaderboard</span>
            <span class="badge bg-primary"><?php echo count($rankings); ?> students</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rankings)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clipboard-data fs-1 d-block mb-2"></i>
                No graded answers yet. Run Auto-Grade in <a href="answers.php">Answers</a> first.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 60px;">Rank</th>
                            <th>Student</th>
                            <th class="text-center">Correct</th>
                            <th class="text-center">Wrong</th>
                            <th class="text-center">Pending</th>
                            <th class="text-center">Score</th>
                            <th class="text-center">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rankings as $i => $r): 
                            $rank = $i + 1;
                            $pct = $r['score_pct'] ?? 0;
                            $grade = $pct >= 90 ? 'A' : ($pct >= 80 ? 'B' : ($pct >= 70 ? 'C' : ($pct >= 60 ? 'D' : 'F')));
                            $grade_color = $pct >= 80 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td class="ps-3">
                                <?php if ($rank === 1): ?>
                                    <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                        <i class="bi bi-trophy-fill text-white"></i>
                                    </div>
                                <?php elseif ($rank === 2): ?>
                                    <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                        <i class="bi bi-award-fill text-white"></i>
                                    </div>
                                <?php elseif ($rank === 3): ?>
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;background:#cd7f32;">
                                        <i class="bi bi-star-fill text-white"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                        <span class="fw-bold text-muted small"><?php echo $rank; ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo escape($r['full_name']); ?></div>
                                <small class="text-muted"><?php echo $r['total_answered']; ?> questions answered</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success fs-6"><?php echo $r['correct']; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-danger fs-6"><?php echo $r['wrong']; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?php echo $r['pending']; ?></span>
                            </td>
                            <td class="text-center">
                                <div class="fw-bold fs-5 text-<?php echo $grade_color; ?>"><?php echo $pct; ?>%</div>
                                <div class="progress mt-1" style="height:6px;width:80px;margin:0 auto;">
                                    <div class="progress-bar bg-<?php echo $grade_color; ?>" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $grade_color; ?> fs-6 px-3"><?php echo $grade; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Question Analysis: Correct Answer vs Student Picks -->
<?php if (!empty($question_stats)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3">
        <span class="fw-bold"><i class="bi bi-bar-chart-fill text-info me-2"></i>Question Analysis — Correct Answer vs Student Picks</span>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <?php foreach ($question_stats as $idx => $qs):
                $options = ['A' => $qs['option_a'], 'B' => $qs['option_b'], 'C' => $qs['option_c'], 'D' => $qs['option_d']];
                $picks   = ['A' => $qs['picked_a'], 'B' => $qs['picked_b'], 'C' => $qs['picked_c'], 'D' => $qs['picked_d']];
                $total   = $qs['total_submissions'];
                $correct_rate = $total > 0 ? round(($qs['correct_count'] / $total) * 100) : 0;
            ?>
            <div class="list-group-item py-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="flex-grow-1 me-3">
                        <div class="fw-bold small mb-1">Q<?php echo $idx + 1; ?>. <?php echo escape(mb_strimwidth($qs['question_text'], 0, 100, '...')); ?></div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle me-1"></i>Correct: <?php echo $qs['correct_answer']; ?> — <?php echo escape($options[$qs['correct_answer']]); ?>
                            </span>
                            <span class="text-muted small"><?php echo $qs['correct_count']; ?>/<?php echo $total; ?> got it right</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-5 text-<?php echo $correct_rate >= 70 ? 'success' : ($correct_rate >= 40 ? 'warning' : 'danger'); ?>">
                            <?php echo $correct_rate; ?>%
                        </div>
                        <small class="text-muted">correct rate</small>
                    </div>
                </div>
                
                <!-- Answer distribution bars -->
                <div class="row g-1 mt-2">
                    <?php foreach (['A','B','C','D'] as $opt): 
                        $count = $picks[$opt] ?? 0;
                        $pct_bar = $total > 0 ? round(($count / $total) * 100) : 0;
                        $is_correct = $opt === $qs['correct_answer'];
                    ?>
                    <div class="col-6 col-md-3">
                        <div class="p-2 rounded <?php echo $is_correct ? 'border border-success bg-success bg-opacity-10' : 'bg-light'; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="fw-bold <?php echo $is_correct ? 'text-success' : ''; ?>">
                                    <?php echo $opt; ?>. <?php echo escape(mb_strimwidth($options[$opt], 0, 25, '...')); ?>
                                    <?php if ($is_correct): ?><i class="bi bi-check-circle-fill text-success ms-1"></i><?php endif; ?>
                                </small>
                                <small class="fw-bold"><?php echo $count; ?></small>
                            </div>
                            <div class="progress" style="height:5px;">
                                <div class="progress-bar <?php echo $is_correct ? 'bg-success' : 'bg-secondary'; ?>" style="width:<?php echo $pct_bar; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $pct_bar; ?>% picked this</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../inc/footer.php'; ?>
