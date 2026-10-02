<?php
/**
 * Teacher Game Control Panel
 * Real-time updates via JavaScript polling
 */

require_once '../config.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$game_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch();

if (!$game) redirect('games.php');

$pageTitle = 'Game Control';
require_once '../inc/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="games.php" class="text-decoration-none">Games</a></li>
            <li class="breadcrumb-item active">Control Panel</li>
        </ol>
    </nav>
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-joystick text-primary me-2"></i>Game Control Panel</h1>
        <p class="text-muted mb-0">Monitor and control game in real-time</p>
    </div>
</div>

<!-- Messages -->
<div id="message-area"></div>

<!-- Game Header Card -->
<div class="card mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="card-body text-white">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <h4 class="fw-bold mb-2" id="game-name"><?php echo escape($game['name']); ?></h4>
                <span class="badge bg-white bg-opacity-25 px-3 py-2" id="game-status-badge" style="font-size: 0.9rem;">
                    <i class="bi bi-circle-fill me-1" style="font-size: 0.6rem;"></i>
                    <span id="status-text">Loading...</span>
                </span>
            </div>
            <div class="text-end">
                <div class="display-6 fw-bold" id="student-count">-</div>
                <small class="opacity-75">students joined</small>
            </div>
        </div>
        
        <div class="mt-3 p-3 bg-white bg-opacity-10 rounded" id="timer-info" style="display: none;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-alarm me-2"></i>
                    <span class="fw-semibold">Think Time Remaining</span>
                </div>
                <div class="fs-3 fw-bold font-monospace" id="timer-display">--:--</div>
            </div>
        </div>
        
        <div class="mt-3 p-3 bg-white bg-opacity-10 rounded" id="question-progress-info" style="display: none;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-list-ol me-2"></i>
                    <span class="fw-semibold">Question Progress</span>
                </div>
                <div class="fs-5 fw-bold" id="question-progress-display">-/-</div>
            </div>
        </div>
    </div>
</div>

<!-- Controls Card -->
<div class="card mb-3">
    <div class="card-header bg-white">
        <i class="bi bi-controller me-2"></i>
        <span class="fw-semibold">Game Controls</span>
    </div>
    <div class="card-body">
        <div class="d-grid gap-2" id="controls-area">
            <!-- Controls will be rendered by JavaScript -->
        </div>
    </div>
</div>

<!-- Status Grid -->
<div class="card mb-3">
    <div class="card-header bg-white">
        <i class="bi bi-pie-chart me-2"></i>
        <span class="fw-semibold">Student Status Overview</span>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-6 col-md-4">
                <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                    <div class="display-6 fw-bold" style="color: #92400e;" id="count-waiting">0</div>
                    <small class="text-uppercase fw-semibold" style="color: #b45309; font-size: 0.7rem;">Waiting</small>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #cffafe, #a5f3fc);">
                    <div class="display-6 fw-bold" style="color: #0e7490;" id="count-answering">0</div>
                    <small class="text-uppercase fw-semibold" style="color: #0891b2; font-size: 0.7rem;">Thinking</small>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #e2e8f0, #cbd5e1);">
                    <div class="display-6 fw-bold" style="color: #475569;" id="count-ready">0</div>
                    <small class="text-uppercase fw-semibold" style="color: #64748b; font-size: 0.7rem;">Ready</small>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0);">
                    <div class="display-6 fw-bold" style="color: #065f46;" id="count-paired">0</div>
                    <small class="text-uppercase fw-semibold" style="color: #059669; font-size: 0.7rem;">Paired</small>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #ddd6fe, #c4b5fd);">
                    <div class="display-6 fw-bold" style="color: #5b21b6;" id="count-chatting">0</div>
                    <small class="text-uppercase fw-semibold" style="color: #7c3aed; font-size: 0.7rem;">Sharing</small>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #f3f4f6, #e5e7eb);">
                    <div class="display-6 fw-bold text-dark" id="count-total">0</div>
                    <small class="text-uppercase fw-semibold text-muted" style="font-size: 0.7rem;">Total</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Students List -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-people me-2"></i>
            <span class="fw-semibold">Students</span>
        </span>
        <span class="badge bg-primary rounded-pill" id="students-badge">0</span>
    </div>
    <div class="card-body p-0" id="students-list">
        <div class="text-center py-4 text-muted">Loading...</div>
    </div>
</div>

<!-- Pairs -->
<div class="card" id="pairs-card" style="display: none;">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-people-fill me-2"></i>
            <span class="fw-semibold">Pairs & Answers</span>
        </span>
        <span class="badge bg-success rounded-pill" id="pairs-badge">0</span>
    </div>
    <div class="card-body p-0" id="pairs-list">
        <div class="text-center py-4 text-muted">No pairs yet</div>
    </div>
</div>

<script>
const GAME_ID = <?php echo $game_id; ?>;
let gameStatus = null;
let pollInterval = null;

// Show message
function showMessage(type, text) {
    const area = document.getElementById('message-area');
    area.innerHTML = `<div class="alert alert-${type} py-2"><i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${text}</div>`;
    setTimeout(() => area.innerHTML = '', 3000);
}

// Perform action
async function doAction(action) {
    if (!confirm(`Are you sure you want to ${action.replace('_', ' ')}?`)) return;
    
    try {
        const response = await fetch('ajax/game_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `game_id=${GAME_ID}&action=${action}`
        });
        const data = await response.json();
        
        if (data.success) {
            showMessage('success', data.message);
            pollGameStatus(); // Refresh immediately
        } else {
            showMessage('danger', data.error);
        }
    } catch (error) {
        showMessage('danger', 'Action failed');
    }
}

// Update controls based on game status
function updateControls(status, currentQ, totalQ) {
    const area = document.getElementById('controls-area');
    let html = '';
    
    if (status === 'waiting') {
        html = `<button class="btn btn-success btn-lg shadow-sm" onclick="doAction('start_game')" style="padding: 1rem;">
            <i class="bi bi-play-fill me-2" style="font-size: 1.5rem;"></i>
            <span class="fs-5">START GAME</span>
        </button>`;
    }
    
    if (status === 'active') {
        html += `<button class="btn btn-warning btn-lg shadow-sm" onclick="doAction('end_timer')" style="padding: 1rem;">
            <i class="bi bi-alarm me-2" style="font-size: 1.5rem;"></i>
            <span class="fs-5">End Timer Now</span>
        </button>`;
    }
    
    if (['active', 'pairing', 'chatting'].includes(status)) {
        html += `<button class="btn btn-primary btn-lg shadow-sm" onclick="doAction('run_pairing')" style="padding: 1rem;">
            <i class="bi bi-shuffle me-2" style="font-size: 1.5rem;"></i>
            <span class="fs-5">Run Pairing</span>
        </button>`;
    }

    if (['pairing', 'chatting'].includes(status)) {
        const hasMore = totalQ > 0 && currentQ < totalQ;
        if (hasMore) {
            html += `<button class="btn btn-success btn-lg shadow-sm" onclick="doAction('next_question')" style="padding: 1rem;">
                <i class="bi bi-arrow-right-circle-fill me-2" style="font-size: 1.5rem;"></i>
                <span class="fs-5">Next Question (${currentQ + 1}/${totalQ})</span>
            </button>`;
        }
        html += `<button class="btn btn-danger btn-lg shadow-sm" onclick="doAction('end_game')" style="padding: 1rem;">
            <i class="bi bi-flag-fill me-2" style="font-size: 1.5rem;"></i>
            <span class="fs-5">End Game & Save Results</span>
        </button>`;
    }
    
    if (status !== 'waiting') {
        html += `<button class="btn btn-outline-secondary shadow-sm" onclick="doAction('restart_game')" style="padding: 0.75rem;">
            <i class="bi bi-arrow-counterclockwise me-2"></i>
            <span>Restart Game (clears all data)</span>
        </button>`;
    }
    
    area.innerHTML = html || '<p class="text-muted mb-0 text-center py-3">No actions available</p>';
}

// Update status badge
function updateStatusBadge(status) {
    const badge = document.getElementById('game-status-badge');
    const statusText = document.getElementById('status-text');
    const statusMap = {
        'waiting': { text: 'Waiting to Start', icon: 'hourglass-split' },
        'active': { text: 'Game Active', icon: 'play-circle-fill' },
        'pairing': { text: 'Pairing Students', icon: 'shuffle' },
        'chatting': { text: 'Students Sharing', icon: 'chat-dots-fill' },
        'ended': { text: 'Game Ended', icon: 'check-circle-fill' }
    };
    
    const info = statusMap[status] || { text: status, icon: 'circle-fill' };
    statusText.innerHTML = `<i class="bi bi-${info.icon} me-1"></i>${info.text}`;
}

// Update students list
function updateStudentsList(students) {
    const list = document.getElementById('students-list');
    document.getElementById('students-badge').textContent = students.length;
    
    if (students.length === 0) {
        list.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-people" style="font-size: 3rem; opacity: 0.3;"></i><p class="mt-2 mb-0">No students joined yet</p></div>';
        return;
    }
    
    let html = '<div class="list-group list-group-flush">';
    students.forEach(s => {
        const statusIcons = {
            'waiting': 'hourglass-split',
            'answering': 'lightbulb',
            'ready_for_pairing': 'check-circle',
            'paired': 'people',
            'chatting': 'chat-dots'
        };
        const statusColors = {
            'waiting': 'warning',
            'answering': 'info',
            'ready_for_pairing': 'secondary',
            'paired': 'success',
            'chatting': 'primary'
        };
        const icon = statusIcons[s.status] || 'circle';
        const color = statusColors[s.status] || 'secondary';
        const statusText = s.status.replace(/_/g, ' ');
        
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-${color} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-person-fill text-${color}"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${escapeHtml(s.full_name)}</div>
                        <small class="text-muted">${s.username}</small>
                    </div>
                </div>
                <span class="badge bg-${color} rounded-pill">
                    <i class="bi bi-${icon} me-1"></i>${statusText}
                </span>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

// Update pairs list with BOTH students' individual thinking and pair answer
function updatePairsList(pairs) {
    const card = document.getElementById('pairs-card');
    const list = document.getElementById('pairs-list');
    document.getElementById('pairs-badge').textContent = pairs.length;
    
    if (pairs.length === 0) {
        card.style.display = 'none';
        return;
    }
    
    card.style.display = 'block';
    
    let html = '<div class="list-group list-group-flush">';
    pairs.forEach((p, i) => {
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <small class="text-muted">Pair ${i + 1}</small>
                        <div class="fw-semibold">${escapeHtml(p.student1_name)} & ${escapeHtml(p.student2_name)}</div>
                    </div>
                    <div>
                        ${p.pair_answer ? '<span class="badge bg-success me-1"><i class="bi bi-check"></i> Answered</span>' : '<span class="badge bg-secondary me-1">Discussing</span>'}
                        <a href="chat_logs.php?pair_id=${p.id}&game_id=<?php echo $game_id; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-chat-dots"></i> ${p.message_count || 0}
                        </a>
                    </div>
                </div>
                
                ${p.pair_answer ? `
                <div class="alert alert-success py-2 mb-0">
                    <strong><i class="bi bi-check-circle me-1"></i>Selected Answer: ${escapeHtml(p.pair_answer)}</strong>
                    ${p.correct_answer ? `<span class="badge ${p.pair_answer === p.correct_answer ? 'bg-success' : 'bg-danger'} ms-2">${p.pair_answer === p.correct_answer ? 'Correct' : 'Wrong'}</span>` : ''}
                </div>
                ` : ''}
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

// Poll game status
async function pollGameStatus() {
    try {
        const response = await fetch(`ajax/game_status.php?game_id=${GAME_ID}&t=${Date.now()}`, {
            cache: 'no-store'
        });
        const data = await response.json();
        
        if (data.error) {
            console.error('Status error:', data.error);
            return;
        }
        
        gameStatus = data.game.status;
        
        // Update UI
        updateStatusBadge(data.game.status);
        updateControls(data.game.status, data.game.current_question_order, data.game.total_questions);
        
        // Update counts
        document.getElementById('student-count').textContent = data.total_students;
        document.getElementById('count-waiting').textContent = data.counts.waiting || 0;
        document.getElementById('count-answering').textContent = data.counts.answering || 0;
        document.getElementById('count-ready').textContent = data.counts.ready_for_pairing || 0;
        document.getElementById('count-paired').textContent = data.counts.paired || 0;
        document.getElementById('count-chatting').textContent = data.counts.chatting || 0;
        document.getElementById('count-total').textContent = data.total_students;
        
        // Update timer
        if (data.game.remaining_seconds !== null && data.game.status === 'active') {
            document.getElementById('timer-info').style.display = 'block';
            const mins = Math.floor(data.game.remaining_seconds / 60);
            const secs = data.game.remaining_seconds % 60;
            document.getElementById('timer-display').textContent = 
                String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

            // Auto-trigger end_timer when server says time is up
            if (data.game.remaining_seconds <= 0 && gameStatus === 'active') {
                fetch('ajax/game_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `game_id=${GAME_ID}&action=end_timer`
                }).then(r => r.json()).then(d => {
                    if (d.success) {
                        showMessage('success', 'Timer ended! Students moved to Pair phase.');
                        pollGameStatus();
                    }
                });
            }
        } else {
            document.getElementById('timer-info').style.display = 'none';
        }

        // Update question progress
        if (data.game.total_questions > 0 && data.game.status !== 'waiting') {
            document.getElementById('question-progress-info').style.display = 'block';
            document.getElementById('question-progress-display').textContent =
                data.game.current_question_order + ' / ' + data.game.total_questions;
        } else {
            document.getElementById('question-progress-info').style.display = 'none';
        }
        
        // Update lists
        updateStudentsList(data.students);
        updatePairsList(data.pairs);
        
    } catch (error) {
        console.error('Poll error:', error);
    }
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Start polling
pollGameStatus();
pollInterval = setInterval(pollGameStatus, 2000);

// Cleanup
window.addEventListener('beforeunload', () => clearInterval(pollInterval));
</script>

<?php require_once '../inc/footer.php'; ?>
