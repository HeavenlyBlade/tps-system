<?php

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$stats['students'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM questions");
$stats['questions'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM games WHERE status != 'ended'");
$stats['games'] = $stmt->fetchColumn();

// Get score statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT aq.student_id) as total_students_answered,
        SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
        COUNT(aq.id) as total_answers,
        ROUND(AVG(CASE WHEN aq.is_correct = 1 THEN 100 ELSE 0 END), 1) as avg_score
    FROM assigned_questions aq
    WHERE aq.is_correct IS NOT NULL
");
$score_stats = $stmt->fetch();

// Get top performing students
$stmt = $pdo->query("
    SELECT 
        u.full_name,
        COUNT(aq.id) as total_questions,
        SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) as correct,
        ROUND((SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(aq.id)) * 100, 1) as score_percentage
    FROM users u
    JOIN assigned_questions aq ON u.id = aq.student_id
    WHERE u.role = 'student' AND aq.is_correct IS NOT NULL
    GROUP BY u.id, u.full_name
    HAVING COUNT(aq.id) > 0
    ORDER BY score_percentage DESC, correct DESC
    LIMIT 5
");
$top_students = $stmt->fetchAll();

$stmt = $pdo->query("SELECT g.*, (SELECT COUNT(*) FROM game_entries WHERE game_id = g.id) as student_count
                     FROM games g WHERE g.status != 'ended' ORDER BY g.created_at DESC LIMIT 1");
$current_game = $stmt->fetch();

$pageTitle = 'Dashboard';
require_once '../inc/header.php';
?>

<!-- Welcome Header -->
<div class="mb-4">
    <h1 class="display-6 fw-bold mb-1">
        <i class="bi bi-sun-fill text-warning me-2"></i>
        Welcome back, <?php echo escape($_SESSION['full_name']); ?>!
    </h1>
    <p class="text-muted">Here's what's happening with your TPS System today</p>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body" style="background: linear-gradient(135deg, #ddd6fe, #c4b5fd); border-radius: 0.375rem;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="bg-black bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-people-fill text-white" style="font-size: 1.5rem;"></i>
                    </div>
                    <div class="text-end">
                        <div class="display-4 fw-bold text-black"><?php echo $stats['students']; ?></div>
                    </div>
                </div>
                <div class="text-black fw-semibold">Total Students</div>
                <small class="text-black text-opacity-75">Registered in system</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-radius: 0.375rem;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="bg-black bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-question-circle-fill text-white" style="font-size: 1.5rem;"></i>
                    </div>
                    <div class="text-end">
                        <div class="display-4 fw-bold text-black"><?php echo $stats['questions']; ?></div>
                    </div>
                </div>
                <div class="text-black fw-semibold">Questions</div>
                <small class="text-black text-opacity-75">Available in bank</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body" style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 0.375rem;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="bg-black bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-controller text-white" style="font-size: 1.5rem;"></i>
                    </div>
                    <div class="text-end">
                        <div class="display-4 fw-bold text-black"><?php echo $stats['games']; ?></div>
                    </div>
                </div>
                <div class="text-black fw-semibold">Active Games</div>
                <small class="text-black text-opacity-75">Currently running</small>
            </div>
        </div>
    </div>
</div>

<!-- Current Game -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <div class="d-flex align-items-center">
            <i class="bi bi-joystick text-primary me-2" style="font-size: 1.25rem;"></i>
            <span class="fw-bold">Current Game</span>
        </div>
    </div>
    <div class="card-body">
        <?php if ($current_game): ?>
            <div class="row align-items-center mb-3">
                <div class="col-md-8">
                    <h4 class="fw-bold mb-2"><?php echo escape($current_game['name']); ?></h4>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge status-<?php echo $current_game['status']; ?> px-3 py-2">
                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                            <?php echo ucfirst($current_game['status']); ?>
                        </span>
                        <span class="text-muted">•</span>
                        <span class="text-muted">
                            <i class="bi bi-people me-1"></i>
                            <?php echo $current_game['student_count']; ?> students joined
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="game_control.php?id=<?php echo $current_game['id']; ?>" class="btn btn-primary btn-lg shadow-sm">
                        <i class="bi bi-gear-fill me-2"></i>Control Panel
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-controller text-muted" style="font-size: 2.5rem;"></i>
                </div>
                <h5 class="fw-bold mb-2">No Active Game</h5>
                <p class="text-muted mb-4">Create a new game to start teaching with TPS</p>
                <a href="games.php" class="btn btn-primary btn-lg shadow-sm">
                    <i class="bi bi-plus-circle me-2"></i>Create New Game
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <div class="d-flex align-items-center">
            <i class="bi bi-lightning-fill text-warning me-2" style="font-size: 1.25rem;"></i>
            <span class="fw-bold">Quick Actions</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <a href="games.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 shadow-sm">
                    <i class="bi bi-controller mb-2" style="font-size: 2rem;"></i>
                    <span class="fw-semibold">Games</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="questions.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 shadow-sm">
                    <i class="bi bi-patch-question mb-2" style="font-size: 2rem;"></i>
                    <span class="fw-semibold">Questions</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="students.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 shadow-sm">
                    <i class="bi bi-people mb-2" style="font-size: 2rem;"></i>
                    <span class="fw-semibold">Students</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="reports.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 shadow-sm">
                    <i class="bi bi-file-earmark-bar-graph mb-2" style="font-size: 2rem;"></i>
                    <span class="fw-semibold">Reports</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="rankings.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 shadow-sm">
                    <i class="bi bi-trophy mb-2" style="font-size: 2rem;"></i>
                    <span class="fw-semibold">Rankings</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Score Summary -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <i class="bi bi-trophy-fill text-warning me-2" style="font-size: 1.25rem;"></i>
                <span class="fw-bold">Performance Summary</span>
            </div>
            <span class="badge bg-primary rounded-pill"><?php echo $score_stats['total_students_answered'] ?? 0; ?> students</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (($score_stats['total_answers'] ?? 0) > 0): ?>
            <!-- Overall Stats -->
            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="text-center p-3 rounded shadow-sm" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0);">
                        <div class="display-5 fw-bold text-white"><?php echo $score_stats['correct_answers']; ?></div>
                        <small class="text-white fw-semibold">Correct Answers</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="text-center p-3 rounded shadow-sm" style="background: linear-gradient(135deg, #fecaca, #fca5a5);">
                        <div class="display-5 fw-bold text-white"><?php echo $score_stats['total_answers'] - $score_stats['correct_answers']; ?></div>
                        <small class="text-white fw-semibold">Wrong Answers</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="text-center p-3 rounded shadow-sm" style="background: linear-gradient(135deg, #bfdbfe, #93c5fd);">
                        <div class="display-5 fw-bold text-white"><?php echo $score_stats['avg_score']; ?>%</div>
                        <small class="text-white fw-semibold">Average Score</small>
                    </div>
                </div>
            </div>
            
            <!-- Top Students -->
            <?php if (!empty($top_students)): ?>
                <div class="mb-3">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-star-fill text-warning me-2"></i>Top Performers
                    </h6>
                    <div class="list-group">
                        <?php foreach ($top_students as $index => $student): ?>
                            <div class="list-group-item border-0 mb-2 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <?php if ($index === 0): ?>
                                            <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                                <i class="bi bi-trophy-fill text-white fs-5"></i>
                                            </div>
                                        <?php elseif ($index === 1): ?>
                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                                <i class="bi bi-award-fill text-white fs-5"></i>
                                            </div>
                                        <?php elseif ($index === 2): ?>
                                            <div class="bg-danger bg-opacity-75 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                                <i class="bi bi-star-fill text-white fs-5"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                                <span class="fw-bold text-muted"><?php echo $index + 1; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo escape($student['full_name']); ?></div>
                                            <small class="text-muted">
                                                <i class="bi bi-check-circle me-1"></i>
                                                <?php echo $student['correct']; ?>/<?php echo $student['total_questions']; ?> correct
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-4 fw-bold text-<?php echo $student['score_percentage'] >= 80 ? 'success' : ($student['score_percentage'] >= 60 ? 'warning' : 'danger'); ?>">
                                            <?php echo $student['score_percentage']; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="d-grid gap-2">
                <a href="answers.php" class="btn btn-outline-primary shadow-sm">
                    <i class="bi bi-list-check me-2"></i>View All Answers
                </a>
                <a href="rankings.php" class="btn btn-warning shadow-sm">
                    <i class="bi bi-trophy me-2"></i>View Full Rankings
                </a>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success shadow-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-2"></i>Export Scores
                    </button>
                    <ul class="dropdown-menu w-100">
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
        <?php else: ?>
            <div class="text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-clipboard-data text-muted" style="font-size: 2.5rem;"></i>
                </div>
                <h5 class="fw-bold mb-2">No Scores Yet</h5>
                <p class="text-muted mb-0">Scores will appear after grading student answers</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Links -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <div class="d-flex align-items-center">
            <i class="bi bi-grid-3x3-gap-fill text-secondary me-2" style="font-size: 1.25rem;"></i>
            <span class="fw-bold">More Options</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <a href="reports.php" class="list-group-item list-group-item-action border-0 py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                        <i class="bi bi-file-earmark-bar-graph text-success fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Reports & Export</div>
                        <small class="text-muted">Download data for thesis analysis</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </a>
            <a href="rankings.php" class="list-group-item list-group-item-action border-0 py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                        <i class="bi bi-trophy-fill text-warning fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Student Rankings</div>
                        <small class="text-muted">Leaderboard & question analysis</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </a>
            <a href="chat_logs.php" class="list-group-item list-group-item-action border-0 py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                        <i class="bi bi-chat-dots text-primary fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Chat Logs</div>
                        <small class="text-muted">View student pair discussions</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </a>
            <a href="answers.php" class="list-group-item list-group-item-action border-0 py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                        <i class="bi bi-check2-square text-success fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Validate Answers</div>
                        <small class="text-muted">Grade and review submissions</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </a>
            <a href="pairs.php" class="list-group-item list-group-item-action border-0 py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                        <i class="bi bi-people text-info fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">View Pairings</div>
                        <small class="text-muted">See student pair combinations</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </a>
        </div>
    </div>
</div>

<?php require_once '../inc/footer.php'; ?>