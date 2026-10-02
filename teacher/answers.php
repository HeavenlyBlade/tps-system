<?php

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade'])) {
    $id = (int)$_POST['id'];
    $correct = (int)$_POST['correct'];
    $pdo->prepare("UPDATE assigned_questions SET is_correct = ? WHERE id = ?")->execute([$correct, $id]);
    $message = 'Graded!';
}

// Auto-grade answers based on correct_answer comparison
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_grade'])) {
    $stmt = $pdo->query("
        UPDATE assigned_questions aq
        JOIN questions q ON aq.question_id = q.id
        SET aq.is_correct = CASE 
            WHEN aq.student_answer = q.correct_answer THEN 1 
            ELSE 0 
        END
        WHERE aq.student_answer IS NOT NULL AND aq.is_correct IS NULL
    ");
    $affected = $stmt->rowCount();
    $message = "Auto-graded {$affected} answers!";
}

$game_filter = (int)($_GET['game_id'] ?? 0);
$stmt = $pdo->query("SELECT id, name FROM games ORDER BY created_at DESC");
$games = $stmt->fetchAll();

$sql = "SELECT aq.*, u.full_name as student, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, g.name as game
        FROM assigned_questions aq
        JOIN users u ON aq.student_id = u.id
        JOIN questions q ON aq.question_id = q.id
        JOIN games g ON aq.game_id = g.id
        WHERE aq.student_answer IS NOT NULL";
$params = [];
if ($game_filter) { $sql .= " AND aq.game_id = ?"; $params[] = $game_filter; }
$sql .= " ORDER BY aq.answered_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$answers = $stmt->fetchAll();

$pageTitle = 'Answers';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Answers</li>
        </ol>
    </nav>
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-check2-square text-success me-2"></i>Validate Answers</h1>
            <p class="text-muted mb-0">Review and grade student submissions</p>
        </div>
        <div class="ms-auto">
            <div class="btn-group">
                <button type="button" class="btn btn-success dropdown-toggle shadow-sm" data-bs-toggle="dropdown">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="reports.php">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Summary Report
                    </a></li>
                    <li><a class="dropdown-item" href="reports.php">
                        <i class="bi bi-bar-chart me-2"></i>Pre/Post Comparison
                    </a></li>
                    <li><a class="dropdown-item" href="reports.php">
                        <i class="bi bi-file-text me-2"></i>Detailed Report
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?php echo $message; ?></div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <select name="game_id" class="form-select form-select-sm">
                <option value="0">All Games</option>
                <?php foreach ($games as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $game_filter == $g['id'] ? 'selected' : ''; ?>><?php echo escape($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-filter"></i></button>
        </form>
        <form method="POST" class="mt-2">
            <button type="submit" name="auto_grade" class="btn btn-success btn-sm w-100" onclick="return confirm('Auto-grade all pending answers?')">
                <i class="bi bi-lightning me-1"></i>Auto-Grade All
            </button>
        </form>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-list-check me-2"></i>Answers</span>
        <span class="badge bg-primary"><?php echo count($answers); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($answers)): ?>
            <div class="text-center py-4 text-muted">No answers yet</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($answers as $a): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold"><?php echo escape($a['student']); ?></div>
                            <small class="text-muted"><?php echo escape($a['game']); ?></small>
                        </div>
                        <?php if ($a['is_correct'] === null): ?>
                            <span class="badge bg-secondary">Pending</span>
                        <?php elseif ($a['is_correct'] == 1): ?>
                            <span class="badge bg-success"><i class="bi bi-check"></i> Correct</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x"></i> Wrong</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="small mb-2">
                        <div class="text-muted mb-2"><strong>Q:</strong> <?php echo escape(substr($a['question_text'], 0, 100)); ?>...</div>
                        
                        <!-- Options with color-coded comparison -->
                        <?php 
                        $opts = ['A' => $a['option_a'], 'B' => $a['option_b'], 'C' => $a['option_c'], 'D' => $a['option_d']];
                        foreach ($opts as $letter => $text):
                            $is_student = $a['student_answer'] === $letter;
                            $is_correct = $a['correct_answer'] === $letter;
                            $cls = '';
                            $icon = '';
                            if ($is_correct && $is_student) {
                                $cls = 'border border-success bg-success bg-opacity-10 text-success fw-bold';
                                $icon = '<i class="bi bi-check-circle-fill text-success ms-1"></i>';
                            } elseif ($is_correct) {
                                $cls = 'border border-success bg-success bg-opacity-10 text-success fw-bold';
                                $icon = '<i class="bi bi-check-circle-fill text-success ms-1"></i>';
                            } elseif ($is_student) {
                                $cls = 'border border-danger bg-danger bg-opacity-10 text-danger fw-bold';
                                $icon = '<i class="bi bi-x-circle-fill text-danger ms-1"></i>';
                            }
                        ?>
                        <div class="d-flex align-items-center gap-1 mb-1 px-2 py-1 rounded <?php echo $cls; ?>">
                            <span class="me-1"><?php echo $letter; ?>.</span>
                            <span class="flex-grow-1"><?php echo escape($text); ?></span>
                            <?php echo $icon; ?>
                            <?php if ($is_student): ?><span class="badge bg-primary ms-1 small">Student</span><?php endif; ?>
                            <?php if ($is_correct): ?><span class="badge bg-success ms-1 small">Correct</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="action-btns mt-2">
                        <form method="POST" class="d-inline m-0">
                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                            <input type="hidden" name="correct" value="1">
                            <button type="submit" name="grade" class="action-btn action-btn-control">
                                <i class="bi bi-check-circle-fill"></i> Mark Correct
                            </button>
                        </form>
                        <form method="POST" class="d-inline m-0">
                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                            <input type="hidden" name="correct" value="0">
                            <button type="submit" name="grade" class="action-btn action-btn-delete">
                                <i class="bi bi-x-circle-fill"></i> Mark Wrong
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../inc/footer.php'; ?>
