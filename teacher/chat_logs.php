<?php

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$pair_id = (int)($_GET['pair_id'] ?? 0);
$from_game = (int)($_GET['game_id'] ?? 0); // track if opened from game_control
$selected = null;
$messages = [];

if ($pair_id) {
    $stmt = $pdo->prepare("SELECT p.*, u1.full_name as s1, u2.full_name as s2, g.name as game
                           FROM pairs p
                           JOIN users u1 ON p.student1_id = u1.id
                           JOIN users u2 ON p.student2_id = u2.id
                           JOIN games g ON p.game_id = g.id WHERE p.id = ?");
    $stmt->execute([$pair_id]);
    $selected = $stmt->fetch();
    
    if ($selected) {
        $stmt = $pdo->prepare("SELECT m.*, u.full_name as sender FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.pair_id = ? ORDER BY m.sent_at ASC");
        $stmt->execute([$pair_id]);
        $messages = $stmt->fetchAll();
    }
}

$stmt = $pdo->query("SELECT p.id, u1.full_name as s1, u2.full_name as s2, g.name as game,
                     (SELECT COUNT(*) FROM messages WHERE pair_id = p.id) as msgs
                     FROM pairs p
                     JOIN users u1 ON p.student1_id = u1.id
                     JOIN users u2 ON p.student2_id = u2.id
                     JOIN games g ON p.game_id = g.id ORDER BY p.created_at DESC");
$all_pairs = $stmt->fetchAll();

$pageTitle = 'Chat Logs';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Chat Logs</li>
        </ol>
    </nav>
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-chat-dots text-primary me-2"></i>Chat Logs</h1>
        <p class="text-muted mb-0">View pair discussions and conversations</p>
    </div>
</div>

<?php if ($selected): ?>
    <!-- Chat View -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold"><?php echo escape($selected['s1']); ?> & <?php echo escape($selected['s2']); ?></div>
                <small class="text-muted"><?php echo escape($selected['game']); ?></small>
            </div>
            <a href="<?php echo $from_game ? 'game_control.php?id=' . $from_game : 'chat_logs.php'; ?>" class="action-btn action-btn-cancel">
                <i class="bi bi-<?php echo $from_game ? 'joystick' : 'x-lg'; ?>"></i> <?php echo $from_game ? 'Back to Game Control' : 'Close'; ?>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="chat-messages p-3" style="max-height: 60vh;">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-4">No messages</div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <div class="message message-received mb-2">
                            <div class="small fw-bold text-primary mb-1"><?php echo escape($m['sender']); ?></div>
                            <div><?php echo escape($m['message']); ?></div>
                            <div class="small text-muted mt-1"><?php echo date('M j, g:i A', strtotime($m['sent_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer text-center small text-muted">
            <i class="bi bi-chat-dots me-1"></i><?php echo count($messages); ?> messages
        </div>
    </div>
<?php else: ?>
    <!-- Pair List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span><i class="bi bi-people me-2"></i>Select Pair</span>
            <span class="badge bg-primary"><?php echo count($all_pairs); ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($all_pairs)): ?>
                <div class="text-center py-4 text-muted">No pairs yet</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($all_pairs as $p): ?>
                    <a href="?pair_id=<?php echo $p['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?php echo escape($p['s1']); ?> & <?php echo escape($p['s2']); ?></div>
                                <small class="text-muted"><?php echo escape($p['game']); ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?php echo $p['msgs']; ?></span>
                                <i class="bi bi-chevron-right text-muted ms-2"></i>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../inc/footer.php'; ?>
