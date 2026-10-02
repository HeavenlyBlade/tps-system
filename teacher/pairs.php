<?php

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_pair'])) {
        $id = (int)$_POST['pair_id'];
        $stmt = $pdo->prepare("SELECT * FROM pairs WHERE id = ?");
        $stmt->execute([$id]);
        $pair = $stmt->fetch();
        if ($pair) {
            $pdo->prepare("UPDATE game_entries SET status = 'ready_for_pairing' WHERE game_id = ? AND student_id IN (?, ?)")->execute([$pair['game_id'], $pair['student1_id'], $pair['student2_id']]);
            $pdo->prepare("DELETE FROM pairs WHERE id = ?")->execute([$id]);
            $message = 'Pair deleted.';
        }
    }
    if (isset($_POST['clear_all'])) {
        $gid = (int)$_POST['game_id'];
        $pdo->prepare("UPDATE game_entries SET status = 'ready_for_pairing' WHERE game_id = ? AND status IN ('paired', 'chatting')")->execute([$gid]);
        $pdo->prepare("DELETE FROM pairs WHERE game_id = ?")->execute([$gid]);
        $message = 'All pairs cleared.';
    }
}

$game_filter = (int)($_GET['game_id'] ?? 0);
$stmt = $pdo->query("SELECT id, name FROM games ORDER BY created_at DESC");
$games = $stmt->fetchAll();

$sql = "SELECT p.*, u1.full_name as s1, u2.full_name as s2, g.name as game,
        (SELECT COUNT(*) FROM messages WHERE pair_id = p.id) as msgs,
        p.pair_answer, p.answered_at,
        q.correct_answer, q.question_text
        FROM pairs p
        JOIN users u1 ON p.student1_id = u1.id
        JOIN users u2 ON p.student2_id = u2.id
        JOIN games g ON p.game_id = g.id
        LEFT JOIN assigned_questions aq ON aq.game_id = p.game_id AND aq.student_id = p.student1_id
        LEFT JOIN questions q ON aq.question_id = q.id";
$params = [];
if ($game_filter) { $sql .= " WHERE p.game_id = ?"; $params[] = $game_filter; }
$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pairs = $stmt->fetchAll();

$pageTitle = 'Pairs';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Pairs</li>
        </ol>
    </nav>
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-people text-info me-2"></i>Student Pairs</h1>
        <p class="text-muted mb-0">View pair combinations and their answers</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?php echo $message; ?></div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="game_id" class="form-select form-select-sm">
                <option value="0">All Games</option>
                <?php foreach ($games as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $game_filter == $g['id'] ? 'selected' : ''; ?>><?php echo escape($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-filter"></i></button>
        </form>
        <?php if ($game_filter): ?>
            <form method="POST" class="mt-2">
                <input type="hidden" name="game_id" value="<?php echo $game_filter; ?>">
                <button type="submit" name="clear_all" class="action-btn action-btn-delete w-100" onclick="return confirm('Clear all pairs for this game?')">
                    <i class="bi bi-trash-fill"></i> Clear All Pairs
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>All Pairs</span>
        <span class="badge bg-primary"><?php echo count($pairs); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pairs)): ?>
            <div class="text-center py-4 text-muted">No pairs yet</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($pairs as $p): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold"><?php echo escape($p['s1']); ?> & <?php echo escape($p['s2']); ?></div>
                            <small class="text-muted"><?php echo escape($p['game']); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary"><?php echo $p['msgs']; ?> msgs</span>
                            <?php if ($p['pair_answer']): ?>
                                <span class="badge bg-success ms-1"><i class="bi bi-check"></i> Answered</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-1">No answer</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($p['pair_answer']): ?>
                    <div class="alert alert-success py-2 mb-2">
                        <small class="text-uppercase fw-bold d-block mb-1"><i class="bi bi-pencil-square me-1"></i>Pair Answer</small>
                        <div>
                            <span class="badge bg-primary fs-6"><?php echo escape($p['pair_answer']); ?></span>
                            <?php if ($p['correct_answer']): ?>
                                <?php if ($p['pair_answer'] === $p['correct_answer']): ?>
                                    <span class="badge bg-success ms-2"><i class="bi bi-check-circle"></i> Correct</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-2"><i class="bi bi-x-circle"></i> Wrong (Correct: <?php echo escape($p['correct_answer']); ?>)</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($p['answered_at']): ?>
                            <small class="text-muted d-block mt-1">Submitted: <?php echo date('M j, g:i A', strtotime($p['answered_at'])); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="action-btns mt-2">
                        <a href="chat_logs.php?pair_id=<?php echo $p['id']; ?>" class="action-btn action-btn-chat">
                            <i class="bi bi-chat-dots-fill"></i> View Chat
                        </a>
                        <form method="POST" class="d-inline m-0">
                            <input type="hidden" name="pair_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" name="delete_pair" class="action-btn action-btn-delete" onclick="return confirm('Delete this pair?')">
                                <i class="bi bi-trash-fill"></i> Delete
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
