<?php

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_question'])) {
        $text = trim($_POST['question_text'] ?? '');
        $answer = trim($_POST['correct_answer'] ?? '');
        if (empty($text)) $error = 'Enter a question.';
        else {
            $pdo->prepare("INSERT INTO questions (question_text, correct_answer, created_by) VALUES (?, ?, ?)")->execute([$text, $answer, $teacher_id]);
            $message = 'Question created!';
        }
    }
    if (isset($_POST['update_question'])) {
        $id = (int)$_POST['question_id'];
        $text = trim($_POST['question_text'] ?? '');
        $answer = trim($_POST['correct_answer'] ?? '');
        if (empty($text)) $error = 'Enter a question.';
        else {
            $pdo->prepare("UPDATE questions SET question_text = ?, correct_answer = ? WHERE id = ?")->execute([$text, $answer, $id]);
            $message = 'Updated!';
        }
    }
    if (isset($_POST['delete_question'])) {
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([(int)$_POST['question_id']]);
        $message = 'Deleted!';
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM questions ORDER BY created_at DESC");
$questions = $stmt->fetchAll();

$pageTitle = 'Questions';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Questions</li>
        </ol>
    </nav>
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-patch-question text-success me-2"></i>Question Bank</h1>
        <p class="text-muted mb-0">Manage multiple choice questions for games</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Form -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-<?php echo $edit ? 'pencil' : 'plus-circle'; ?> me-2"></i>
        <?php echo $edit ? 'Edit' : 'New Question'; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php if ($edit): ?>
                <input type="hidden" name="question_id" value="<?php echo $edit['id']; ?>">
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label">Question</label>
                <textarea class="form-control" name="question_text" rows="3" required placeholder="Enter question..."><?php echo escape($edit['question_text'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Answer (optional)</label>
                <input type="text" class="form-control" name="correct_answer" placeholder="For grading reference" value="<?php echo escape($edit['correct_answer'] ?? ''); ?>">
            </div>
            
            <?php if ($edit): ?>
                <div class="d-flex gap-2">
                    <button type="submit" name="update_question" class="btn btn-primary flex-fill"><i class="bi bi-check me-1"></i>Update</button>
                    <a href="questions.php" class="action-btn action-btn-cancel"><i class="bi bi-x-lg"></i> Cancel</a>
                </div>
            <?php else: ?>
                <button type="submit" name="create_question" class="btn btn-success w-100"><i class="bi bi-plus-circle me-2"></i>Create</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>All Questions</span>
        <span class="badge bg-primary"><?php echo count($questions); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($questions)): ?>
            <div class="text-center py-4 text-muted">No questions yet</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($questions as $q): ?>
                <div class="list-group-item">
                    <div class="mb-2"><?php echo escape($q['question_text']); ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <?php if ($q['correct_answer']): ?>
                                <span class="badge bg-success bg-opacity-75 rounded-pill px-2">
                                    <i class="bi bi-check-circle me-1"></i><?php echo escape($q['correct_answer']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No answer set</span>
                            <?php endif; ?>
                        </small>
                        <div class="action-btns">
                            <a href="?edit=<?php echo $q['id']; ?>" class="action-btn action-btn-edit">
                                <i class="bi bi-pencil-fill"></i> Edit
                            </a>
                            <form method="POST" class="d-inline m-0">
                                <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                <button type="submit" name="delete_question" class="action-btn action-btn-delete" onclick="return confirm('Delete this question?')">
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
