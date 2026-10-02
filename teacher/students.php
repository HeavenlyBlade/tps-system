<?php

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

$message = '';
$error = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_student'])) {
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['full_name'] ?? '');
        $pass = $_POST['password'] ?? '';
        
        if (empty($username) || empty($name) || empty($pass)) $error = 'Fill all fields.';
        elseif (strlen($pass) < 6) $error = 'Password: min 6 chars.';
        else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) $error = 'Username taken.';
            else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'student')")->execute([$username, $hash, $name]);
                $message = 'Student created!';
            }
        }
    }
    if (isset($_POST['update_student'])) {
        $id = (int)$_POST['student_id'];
        $name = trim($_POST['full_name'] ?? '');
        $pass = $_POST['password'] ?? '';
        
        if (empty($name)) $error = 'Enter name.';
        else {
            if ($pass) {
                if (strlen($pass) < 6) $error = 'Password: min 6 chars.';
                else {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE id = ? AND role = 'student'")->execute([$name, $hash, $id]);
                    $message = 'Updated!';
                }
            } else {
                $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ? AND role = 'student'")->execute([$name, $id]);
                $message = 'Updated!';
            }
        }
    }
    if (isset($_POST['delete_student'])) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([(int)$_POST['student_id']]);
        $message = 'Deleted!';
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY full_name");
$students = $stmt->fetchAll();

$pageTitle = 'Students';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Students</li>
        </ol>
    </nav>
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-people text-warning me-2"></i>Student Management</h1>
        <p class="text-muted mb-0">View and manage registered students</p>
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
        <i class="bi bi-<?php echo $edit ? 'pencil' : 'person-plus'; ?> me-2"></i>
        <?php echo $edit ? 'Edit Student' : 'New Student'; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php if ($edit): ?>
                <input type="hidden" name="student_id" value="<?php echo $edit['id']; ?>">
            <?php endif; ?>
            
            <?php if (!$edit): ?>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" placeholder="e.g., john_doe" required>
            </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="full_name" placeholder="e.g., John Doe" value="<?php echo escape($edit['full_name'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password<?php echo $edit ? ' (leave blank to keep)' : ''; ?></label>
                <input type="password" class="form-control" name="password" placeholder="Min 6 characters" <?php echo $edit ? '' : 'required'; ?>>
            </div>
            
            <?php if ($edit): ?>
                <div class="d-flex gap-2">
                    <button type="submit" name="update_student" class="btn btn-primary flex-fill"><i class="bi bi-check me-1"></i>Update</button>
                    <a href="students.php" class="action-btn action-btn-cancel"><i class="bi bi-x-lg"></i> Cancel</a>
                </div>
            <?php else: ?>
                <button type="submit" name="create_student" class="btn btn-success w-100"><i class="bi bi-person-plus me-2"></i>Create</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>All Students</span>
        <span class="badge bg-primary"><?php echo count($students); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="text-center py-4 text-muted">No students yet</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($students as $s): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                    <div>
                        <div class="fw-bold"><?php echo escape($s['full_name']); ?></div>
                        <small class="text-muted">@<?php echo escape($s['username']); ?></small>
                    </div>
                    <div class="action-btns">
                        <a href="?edit=<?php echo $s['id']; ?>" class="action-btn action-btn-edit">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </a>
                        <form method="POST" class="d-inline m-0">
                            <input type="hidden" name="student_id" value="<?php echo $s['id']; ?>">
                            <button type="submit" name="delete_student" class="action-btn action-btn-delete" onclick="return confirm('Delete <?php echo escape($s['full_name']); ?>?')">
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
