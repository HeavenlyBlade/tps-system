<?php
/**
 * TPS SYSTEM - Register (Grade-school friendly: username + full name only for students)
 */

require_once '../config.php';

if (isLoggedIn()) {
    redirect(isTeacher() ? '../teacher/dashboard.php' : '../student/dashboard.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($username) || empty($full_name)) {
        $error = 'Please fill in both fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only have letters, numbers, and underscores.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'That username is already taken. Try another one!';
        } else {
            // Students have no real password — store a locked placeholder
            $placeholder = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'student')")
                ->execute([$username, $placeholder, $full_name]);
            $success = 'Account created! You can now sign in with your username.';
            $_POST = [];
        }
    }
}

$pageTitle = 'Create Account';
require_once '../inc/header.php';
?>

<style>
.btn-float {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.btn-float:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(6,214,160,0.4);
}
.btn-float-outline {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.btn-float-outline:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(6,214,160,0.25);
}
</style>

<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;background:linear-gradient(135deg,#06D6A0,#4CC9F0);box-shadow:0 6px 20px rgba(6,214,160,0.4);font-size:2.2rem;">
                        ✏️
                    </div>
                    <h4 class="fw-bold mb-1">Create Your Username</h4>
                    <p class="text-secondary small">Pick a username to join the game</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small">
                        <i class="bi bi-exclamation-circle me-1"></i><?php echo escape($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2 small">
                        <i class="bi bi-check-circle me-1"></i><?php echo escape($success); ?>
                        <div class="mt-2"><a href="login.php" class="btn btn-success btn-sm w-100 btn-float">🚀 Go to Login</a></div>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            📛 Full Name
                        </label>
                        <input type="text" class="form-control form-control-lg" name="full_name"
                               placeholder="Your full name (e.g. Juan dela Cruz)"
                               value="<?php echo escape($_POST['full_name'] ?? ''); ?>" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            🏷️ Choose a Username
                        </label>
                        <input type="text" class="form-control form-control-lg" name="username"
                               placeholder="e.g. juan123 (letters and numbers only)"
                               value="<?php echo escape($_POST['username'] ?? ''); ?>"
                               pattern="[a-zA-Z0-9_]+" title="Letters, numbers, and underscores only"
                               required>
                        <div class="form-text">No spaces. You'll use this to sign in.</div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100 mb-3 btn-float">
                        🎉 Create My Account
                    </button>
                </form>
                <?php endif; ?>

                <div class="mt-3 pt-3 border-top text-center">
                    <p class="text-secondary small mb-2">Already have a username?</p>
                    <a href="login.php" class="btn btn-outline-success w-100 btn-float-outline d-flex align-items-center justify-content-center gap-2">
                        🔑 Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../inc/footer.php'; ?>
