<?php

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_game'])) {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) $error = 'Enter a name.';
        else {
            $pdo->prepare("INSERT INTO games (name, status, created_by) VALUES (?, 'waiting', ?)")->execute([$name, $teacher_id]);
            $message = 'Game created!';
        }
    }
    if (isset($_POST['delete_game'])) {
        $pdo->prepare("DELETE FROM games WHERE id = ?")->execute([(int)$_POST['game_id']]);
        $message = 'Game deleted!';
    }
    if (isset($_POST['end_game'])) {
        $pdo->prepare("UPDATE games SET status = 'ended' WHERE id = ?")->execute([(int)$_POST['game_id']]);
        $message = 'Game ended!';
    }
}

$stmt = $pdo->query("SELECT g.*, (SELECT COUNT(*) FROM game_entries WHERE game_id = g.id) as students
                     FROM games g ORDER BY g.created_at DESC");
$games = $stmt->fetchAll();

$pageTitle = 'Games';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Games</li>
        </ol>
    </nav>
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-controller text-primary me-2"></i>Manage Games</h1>
        <p class="text-muted mb-0">Create and control TPS game sessions</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Create -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-plus-circle me-2"></i>New Game</div>
    <div class="card-body">
        <form method="POST">
            <div class="input-group">
                <input type="text" class="form-control" name="name" placeholder="Game name..." required>
                <button type="submit" name="create_game" class="btn btn-success">
                    <i class="bi bi-plus"></i><span class="d-none d-sm-inline ms-1">Create</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>All Games</span>
        <span class="badge bg-primary"><?php echo count($games); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($games)): ?>
            <div class="text-center py-4 text-muted">No games yet</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($games as $g): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold"><?php echo escape($g['name']); ?></div>
                            <small class="text-muted"><?php echo date('M j, g:i A', strtotime($g['created_at'])); ?></small>
                        </div>
                        <span class="badge status-<?php echo $g['status']; ?>"><?php echo ucfirst($g['status']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted"><i class="bi bi-people me-1"></i><?php echo $g['students']; ?> students</small>
                        <div class="action-btns">
                            <?php if ($g['status'] !== 'ended'): ?>
                                <a href="game_control.php?id=<?php echo $g['id']; ?>" class="action-btn action-btn-control">
                                    <i class="bi bi-gear-fill"></i> Control
                                </a>
                                <form method="POST" class="d-inline m-0">
                                    <input type="hidden" name="game_id" value="<?php echo $g['id']; ?>">
                                    <button type="submit" name="end_game" class="action-btn action-btn-end" onclick="return confirm('End this game?')">
                                        <i class="bi bi-stop-circle-fill"></i> End
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline m-0">
                                <input type="hidden" name="game_id" value="<?php echo $g['id']; ?>">
                                <button type="submit" name="delete_game" class="action-btn action-btn-delete" onclick="return confirm('Delete this game?')">
                                    <i class="bi bi-trash-fill"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../inc/footer.php'; ?>
