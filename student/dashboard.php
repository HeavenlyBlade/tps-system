<?php

require_once '../config.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$student_id = $_SESSION['user_id'];

// Handle leaving a game (from "Join Another Game" button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_game'])) {
    $leave_game_id = (int)$_POST['leave_game'];
    // Mark the student's entry as completed so they can join a new game
    $pdo->prepare("DELETE FROM game_entries WHERE game_id = ? AND student_id = ?")->execute([$leave_game_id, $student_id]);
}

$stmt = $pdo->prepare("SELECT ge.*, g.name as game_name, g.status as game_status FROM game_entries ge
                       JOIN games g ON ge.game_id = g.id WHERE ge.student_id = ? AND g.status != 'ended'
                       ORDER BY ge.joined_at DESC LIMIT 1");
$stmt->execute([$student_id]);
$current = $stmt->fetch();

if ($current) {
    // Redirect to unified game page for all active states
    redirect('game.php?game_id=' . $current['game_id']);
}

// Show waiting games + active games the student was already in (reconnect)
$stmt = $pdo->query("SELECT g.*, u.full_name as teacher,
                     (SELECT COUNT(*) FROM game_entries WHERE game_id = g.id) as students
                     FROM games g LEFT JOIN users u ON g.created_by = u.id
                     WHERE g.status = 'waiting' ORDER BY g.created_at DESC");
$games = $stmt->fetchAll();

// Check for active games this student was part of (disconnected/kicked)
$stmt = $pdo->prepare("
    SELECT g.*, u.full_name as teacher,
           (SELECT COUNT(*) FROM game_entries WHERE game_id = g.id) as students,
           ge.status as my_status
    FROM games g
    LEFT JOIN users u ON g.created_by = u.id
    JOIN game_entries ge ON ge.game_id = g.id AND ge.student_id = ?
    WHERE g.status IN ('active','pairing','chatting')
    ORDER BY g.created_at DESC
");
$stmt->execute([$student_id]);
$rejoinable = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_game'])) {
    $game_id = (int)$_POST['game_id'];
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ? AND status = 'waiting'");
    $stmt->execute([$game_id]);
    if ($stmt->fetch()) {
        $pdo->prepare("INSERT INTO game_entries (game_id, student_id, status) VALUES (?, ?, 'waiting')
                       ON DUPLICATE KEY UPDATE status = 'waiting'")->execute([$game_id, $student_id]);
        redirect('game.php?game_id=' . $game_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rejoin_game'])) {
    $game_id = (int)$_POST['game_id'];
    redirect('game.php?game_id=' . $game_id);
}

$pageTitle = 'Dashboard';
require_once '../inc/header.php';
?>

<!-- Welcome Header -->
<div class="mb-4">
    <h1 class="display-6 fw-bold mb-1">
        <i class="bi bi-emoji-smile-fill text-primary me-2"></i>
        Hi, <?php echo escape($_SESSION['full_name']); ?>!
    </h1>
    <p class="text-muted">Ready to learn with Think-Pair-Share?</p>
</div>

<?php if (empty($games) && empty($rejoinable)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                <i class="bi bi-controller text-muted" style="font-size: 3rem;"></i>
            </div>
            <h4 class="fw-bold mb-2">No Games Available</h4>
            <p class="text-muted mb-4">Your teacher hasn't created any games yet</p>
            <div class="d-flex align-items-center justify-content-center text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div>
                <small>Checking for new games...</small>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if (!empty($rejoinable)): ?>
    <!-- Rejoin Active Games -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold mb-0 text-warning">
            <i class="bi bi-arrow-repeat me-2"></i>Reconnect to Game
        </h5>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ($rejoinable as $game): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 border-warning border-2 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-2"><?php echo escape($game['name']); ?></h5>
                        <span class="badge bg-warning text-dark mb-3">
                            <i class="bi bi-play-circle me-1"></i>Game In Progress
                        </span>
                        <div class="d-flex align-items-center text-muted mb-3">
                            <i class="bi bi-person-workspace me-2"></i>
                            <small><?php echo escape($game['teacher'] ?? 'Teacher'); ?></small>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                            <button type="submit" name="rejoin_game" class="btn btn-warning w-100 shadow-sm">
                                <i class="bi bi-arrow-repeat me-2"></i>Reconnect
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($games)): ?>
    <!-- Available Games Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-controller me-2"></i>Available Games
        </h5>
        <span class="badge bg-primary rounded-pill"><?php echo count($games); ?> game<?php echo count($games) > 1 ? 's' : ''; ?></span>
    </div>

    <div class="row g-3">
        <?php foreach ($games as $game): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-2"><?php echo escape($game['name']); ?></h5>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-3 py-2">
                                    <i class="bi bi-hourglass-split me-1"></i>Waiting to Start
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center text-muted mb-2">
                                <i class="bi bi-person-workspace me-2"></i>
                                <small><?php echo escape($game['teacher'] ?? 'Teacher'); ?></small>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                    <i class="bi bi-people-fill text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-primary"><?php echo $game['students']; ?></div>
                                    <small class="text-muted">students joined</small>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                            <button type="submit" name="join_game" class="btn btn-success w-100 shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Join Game
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// Auto-refresh to check for new games
setTimeout(() => location.reload(), 15000);
</script>

<?php require_once '../inc/footer.php'; ?>