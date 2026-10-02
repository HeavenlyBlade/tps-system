<?php
require_once '../config.php';
if (!isLoggedIn() || !isTeacher()) redirect('../auth/login.php');

// Load all games for the selector
$stmt = $pdo->query("
    SELECT g.id, g.name, g.status, g.created_at,
           COUNT(DISTINCT aq.student_id) as students_answered,
           COUNT(aq.id) as total_answers,
           SUM(CASE WHEN aq.is_correct IS NOT NULL THEN 1 ELSE 0 END) as graded
    FROM games g
    LEFT JOIN assigned_questions aq ON g.id = aq.game_id
    GROUP BY g.id
    ORDER BY g.created_at DESC
");
$games = $stmt->fetchAll();

// Overall stats
$total_students  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_graded    = $pdo->query("SELECT COUNT(*) FROM assigned_questions WHERE is_correct IS NOT NULL")->fetchColumn();
$students_scored = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM assigned_questions WHERE is_correct IS NOT NULL")->fetchColumn();

$pageTitle = 'Reports & Export';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Reports & Export</li>
        </ol>
    </nav>
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph text-success me-2"></i>Reports & Export</h1>
        <p class="text-muted mb-0">Download data for thesis analysis</p>
    </div>
</div>

<!-- Overall Stats -->
<div class="row g-2 mb-4">
    <div class="col-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-primary"><?php echo $total_students; ?></div>
                <small class="text-muted">Students</small>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-success"><?php echo $students_scored; ?></div>
                <small class="text-muted">With Scores</small>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-warning"><?php echo $total_graded; ?></div>
                <small class="text-muted">Graded</small>
            </div>
        </div>
    </div>
</div>

<!-- Export Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-download me-2"></i>Export Options
    </div>
    <div class="card-body">
        <form method="GET" action="export_scores.php" id="exportForm">
            <input type="hidden" name="format" value="csv">

            <!-- Step 1: Choose Game -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="bi bi-controller me-1 text-primary"></i>Step 1 — Select Game
                </label>
                <select name="game_id" class="form-select" id="gameSelect" required>
                    <option value="">— Choose a game —</option>
                    <option value="all">All Games (combined)</option>
                    <?php foreach ($games as $g): ?>
                        <option value="<?php echo $g['id']; ?>">
                            <?php echo escape($g['name']); ?>
                            (<?php echo $g['students_answered']; ?> students,
                            <?php echo $g['graded']; ?>/<?php echo $g['total_answers']; ?> graded,
                            <?php echo ucfirst($g['status']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($games)): ?>
                    <div class="form-text text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No games found. Create a game first.</div>
                <?php endif; ?>
            </div>

            <!-- Step 2: Choose Report Type -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="bi bi-file-earmark-text me-1 text-success"></i>Step 2 — Select Report Type
                </label>
                <div class="d-grid gap-2">

                    <label class="report-option" id="opt-summary">
                        <input type="radio" name="type" value="summary" class="d-none" required>
                        <div class="d-flex align-items-center p-3 rounded-3 border-2 border report-card">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-2 me-3 flex-shrink-0">
                                <i class="bi bi-file-earmark-spreadsheet fs-4 text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Summary Report</div>
                                <small class="text-muted">Overall scores per student — correct, wrong, percentage</small>
                            </div>
                            <i class="bi bi-circle report-check text-muted fs-5"></i>
                        </div>
                    </label>

                    <label class="report-option" id="opt-comparison">
                        <input type="radio" name="type" value="comparison" class="d-none">
                        <div class="d-flex align-items-center p-3 rounded-3 border-2 border report-card">
                            <div class="bg-success bg-opacity-10 rounded-3 p-2 me-3 flex-shrink-0">
                                <i class="bi bi-bar-chart fs-4 text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Pre-Test vs Post-Test Comparison</div>
                                <small class="text-muted">Template with blank pre-test column + TPS scores filled in</small>
                            </div>
                            <i class="bi bi-circle report-check text-muted fs-5"></i>
                        </div>
                    </label>

                    <label class="report-option" id="opt-detailed">
                        <input type="radio" name="type" value="detailed" class="d-none">
                        <div class="d-flex align-items-center p-3 rounded-3 border-2 border report-card">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-2 me-3 flex-shrink-0">
                                <i class="bi bi-file-text fs-4 text-warning"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Detailed Report</div>
                                <small class="text-muted">Question-by-question breakdown with correct vs student answer</small>
                            </div>
                            <i class="bi bi-circle report-check text-muted fs-5"></i>
                        </div>
                    </label>

                </div>
            </div>

            <!-- Preview of selection -->
            <div id="exportPreview" class="alert alert-info d-none mb-3">
                <i class="bi bi-info-circle me-2"></i>
                <span id="previewText"></span>
            </div>

            <button type="submit" class="btn btn-success w-100 btn-lg" id="exportBtn" disabled>
                <i class="bi bi-download me-2"></i>Download CSV
            </button>
        </form>
    </div>
</div>

<!-- Instructions -->
<div class="card">
    <div class="card-header"><i class="bi bi-info-circle me-2"></i>How to Use for Thesis</div>
    <div class="card-body">
        <h6 class="fw-bold mb-2">For Pre-Test vs Post-Test Analysis:</h6>
        <ol class="small mb-3">
            <li>Select the specific game session you want to export</li>
            <li>Choose "Pre-Test vs Post-Test Comparison"</li>
            <li>Open the CSV in Excel or Google Sheets</li>
            <li>Fill in Pre-Test scores manually from your paper tests</li>
            <li>The Improvement column auto-calculates the difference</li>
        </ol>
        <h6 class="fw-bold mb-2">Report Types:</h6>
        <ul class="small mb-0">
            <li><strong>Summary:</strong> Best for overall class performance per game</li>
            <li><strong>Comparison:</strong> Best for thesis data — traditional vs TPS learning</li>
            <li><strong>Detailed:</strong> Best for question-level error analysis</li>
        </ul>
    </div>
</div>

<style>
.report-card {
    cursor: pointer;
    transition: all 0.15s ease;
    border-color: #e2e8f0 !important;
    background: #fff;
}
.report-card:hover {
    border-color: #667eea !important;
    background: #f8f7ff;
}
.report-option input:checked + .report-card {
    border-color: #667eea !important;
    background: linear-gradient(135deg, rgba(102,126,234,0.08), rgba(118,75,162,0.08));
}
.report-option input:checked + .report-card .report-check {
    color: #667eea !important;
}
.report-option input:checked + .report-card .report-check::before {
    content: "\f26b"; /* bi-check-circle-fill */
}
</style>

<script>
const gameSelect  = document.getElementById('gameSelect');
const exportBtn   = document.getElementById('exportBtn');
const preview     = document.getElementById('exportPreview');
const previewText = document.getElementById('previewText');
const radios      = document.querySelectorAll('input[name="type"]');

const typeLabels = {
    summary:    'Summary Report',
    comparison: 'Pre-Test vs Post-Test Comparison',
    detailed:   'Detailed Report'
};

function updateState() {
    const game     = gameSelect.options[gameSelect.selectedIndex];
    const typeEl   = document.querySelector('input[name="type"]:checked');
    const gameOk   = gameSelect.value !== '';
    const typeOk   = typeEl !== null;

    exportBtn.disabled = !(gameOk && typeOk);

    if (gameOk && typeOk) {
        const gameName = game.value === 'all' ? 'All Games' : game.text.split('(')[0].trim();
        previewText.textContent = `Exporting "${typeLabels[typeEl.value]}" for: ${gameName}`;
        preview.classList.remove('d-none');
    } else {
        preview.classList.add('d-none');
    }
}

gameSelect.addEventListener('change', updateState);
radios.forEach(r => r.addEventListener('change', updateState));
</script>

<?php require_once '../inc/footer.php'; ?>
