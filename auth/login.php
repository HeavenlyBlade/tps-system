<?php
/**
 * TPS SYSTEM - Login (Mobile-First, Grade-school friendly)
 */

require_once '../config.php';

if (isLoggedIn()) {
    redirect(isLoggedIn() && isTeacher() ? '../teacher/dashboard.php' : '../student/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        $error = 'Please enter your username.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['role'] === 'student') {
            // Students log in with username only
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            redirect('../student/dashboard.php');
        } elseif ($user && $user['role'] === 'teacher') {
            // Teachers need password — redirect to teacher login tab
            $error = 'Teachers must use the Teacher Login tab.';
        } else {
            $error = 'Username not found. Ask your teacher to register you.';
        }
    }
}

// Teacher login (separate POST action)
$teacher_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_login'])) {
    $t_username = trim($_POST['t_username'] ?? '');
    $t_password = $_POST['t_password'] ?? '';

    if (empty($t_username) || empty($t_password)) {
        $teacher_error = 'Please fill in both fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'teacher'");
        $stmt->execute([$t_username]);
        $user = $stmt->fetch();

        if ($user && password_verify($t_password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            redirect('../teacher/dashboard.php');
        } else {
            $teacher_error = 'Invalid teacher username or password.';
        }
    }
}

$pageTitle = 'Login';
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
#loginTabs .nav-link.active {
    background: linear-gradient(135deg, #FF6B35, #FF6B9D) !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(255,107,53,0.35);
}
#loginTabs .nav-link:not(.active) {
    color: #FF6B35 !important;
}
#loginTabs .nav-link:not(.active):hover {
    background: rgba(255,107,53,0.1);
}
</style>

<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;background:linear-gradient(135deg,#FF6B35,#FF6B9D);box-shadow:0 6px 20px rgba(255,107,53,0.4);font-size:2.2rem;">
                        🎓
                    </div>
                    <h4 class="fw-bold mb-1">Kumusta! 👋</h4>
                    <p class="text-secondary small">Sign in to join the game</p>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-pills nav-fill mb-4" id="loginTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="student-tab" onclick="switchTab('student')">
                            <i class="bi bi-person me-1"></i>Student
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="teacher-tab" onclick="switchTab('teacher')">
                            <i class="bi bi-person-workspace me-1"></i>Teacher
                        </button>
                    </li>
                </ul>

                <!-- Student Login -->
                <div id="student-panel">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-circle me-1"></i><?php echo escape($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-semibold fs-5">
                                👤 Your Username
                            </label>
                            <input type="text" class="form-control form-control-lg" name="username"
                                   placeholder="Type your username here"
                                   value="<?php echo escape($_POST['username'] ?? ''); ?>"
                                   required autofocus autocomplete="username">
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 mb-3 btn-float">
                            🚀 Enter Game
                        </button>
                    </form>

                    <div class="mt-3 pt-3 border-top text-center">
                        <p class="text-secondary small mb-2">New student?</p>
                        <a href="register.php" class="btn btn-outline-success w-100 btn-float-outline d-flex align-items-center justify-content-center gap-2">
                            ✏️ Create Username
                        </a>
                    </div>
                </div>

                <!-- Teacher Login -->
                <div id="teacher-panel" style="display:none;">
                    <?php if ($teacher_error): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-circle me-1"></i><?php echo escape($teacher_error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                🧑‍🏫 Teacher Username
                            </label>
                            <input type="text" class="form-control form-control-lg" name="t_username"
                                   placeholder="Enter your username"
                                   value="<?php echo escape($_POST['t_username'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                🔒 Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" name="t_password" id="t_password" placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('t_password','toggleIcon')">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="teacher_login" class="btn btn-success btn-lg w-100 btn-float">
                            🚀 Sign In
                        </button>
                    </form>

                    <div class="mt-3 pt-3 border-top text-center">
                        <p class="text-secondary small mb-2">New teacher account?</p>
                        <a href="register.php" class="btn btn-outline-success w-100 btn-float-outline d-flex align-items-center justify-content-center gap-2">
                            ✏️ Create Account
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('student-panel').style.display = tab === 'student' ? 'block' : 'none';
    document.getElementById('teacher-panel').style.display = tab === 'teacher' ? 'block' : 'none';
    document.getElementById('student-tab').classList.toggle('active', tab === 'student');
    document.getElementById('teacher-tab').classList.toggle('active', tab === 'teacher');
}

function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    icon.classList.toggle('bi-eye', !isHidden);
    icon.classList.toggle('bi-eye-slash', isHidden);
}

<?php if ($teacher_error): ?>
// Auto-switch to teacher tab if teacher error
switchTab('teacher');
<?php endif; ?>
</script>

<?php require_once '../inc/footer.php'; ?>
