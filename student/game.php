<?php
/**
 * Unified Student Game Page
 * Handles all phases: waiting, question, pairing, chat
 * Uses JavaScript for real-time updates without page refresh
 */

require_once '../config.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$student_id = $_SESSION['user_id'];
$game_id = (int)($_GET['game_id'] ?? 0);

// Verify student is in this game
$stmt = $pdo->prepare("
    SELECT ge.*, g.name as game_name, g.status as game_status 
    FROM game_entries ge
    JOIN games g ON ge.game_id = g.id 
    WHERE ge.game_id = ? AND ge.student_id = ?
");
$stmt->execute([$game_id, $student_id]);
$entry = $stmt->fetch();

if (!$entry) {
    redirect('dashboard.php');
}

$pageTitle = $entry['game_name'];
require_once '../inc/header.php';
?>

<!-- All phases are rendered here, shown/hidden by JavaScript -->
<div id="gameContainer">
    
    <!-- PHASE: Waiting for teacher to start -->
    <div id="phase-waiting" class="phase-screen" style="display: none;">
        <div class="card mx-auto" style="max-width: 450px;">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <div class="bg-warning bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="bi bi-hourglass-split text-warning" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
                
                <h4 class="fw-bold mb-2">Waiting for Teacher</h4>
                <p class="text-muted mb-4">The game will start soon</p>
                
                <div class="alert alert-light mb-4">
                    <small class="text-muted d-block">Game</small>
                    <span class="fw-bold text-primary" id="waiting-game-name"><?php echo escape($entry['game_name']); ?></span>
                </div>
                
                <!-- Instructions -->
                <div class="alert alert-info text-start mb-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>What to expect:</h6>
                    <ol class="mb-0 small ps-3">
                        <li class="mb-1"><strong>Think Phase:</strong> Read and think about the question</li>
                        <li class="mb-1"><strong>Pair Phase:</strong> Get matched with a partner</li>
                        <li><strong>Share Phase:</strong> Discuss and submit your answer together</li>
                    </ol>
                </div>
                
                <div class="d-flex align-items-center justify-content-center text-muted mb-3">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    <small>Waiting for teacher to start...</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PHASE: Question (Think) -->
    <div id="phase-question" class="phase-screen" style="display: none;">
        <div class="card mx-auto" style="max-width: 600px;">
            <div class="card-body">
                <div class="text-center mb-3">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                        <i class="bi bi-lightbulb me-1"></i> THINK PHASE
                    </span>
                    <span class="badge bg-secondary fs-6 px-3 py-2 ms-2" id="question-progress-badge" style="display:none;">
                        <i class="bi bi-list-ol me-1"></i> <span id="question-progress-text">Q 1/38</span>
                    </span>
                </div>
                
                <div class="text-center mb-4">
                    <small class="text-muted d-block mb-1"><i class="bi bi-alarm me-1"></i>Time to Think</small>
                    <div class="timer-display" id="question-timer">--:--</div>
                </div>
                
                <div class="alert alert-primary mb-4" id="question-container">
                    <small class="text-uppercase fw-bold opacity-75 d-block mb-2">
                        <i class="bi bi-question-circle me-1"></i>Read the Question
                    </small>
                    <div class="fs-5 fw-medium" id="question-text">Loading question...</div>
                </div>
                
                <div class="alert alert-light">
                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>Instructions</h6>
                    <ul class="mb-0 small">
                        <li>Read and understand the question carefully</li>
                        <li>Think about possible answers on your own</li>
                        <li>You will discuss with a partner in the next phase</li>
                        <li>Wait for the timer to finish</li>
                    </ul>
                </div>
                
                <div class="text-center mt-4" id="question-waiting-msg">
                    <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                    <small class="text-muted">Waiting for think time to end...</small>
                </div>
                
                <div class="text-center mt-4" id="timer-ended-msg" style="display: none;">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>Time's up! Moving to pairing phase...
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PHASE: Pairing Wait -->
    <div id="phase-pairing" class="phase-screen" style="display: none;">
        <div class="card mx-auto" style="max-width: 450px;">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <div class="bg-info bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="bi bi-shuffle text-info" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
                
                <h4 class="fw-bold mb-2">Finding Your Partner</h4>
                <p class="text-muted mb-4">Please wait while we match you with another student</p>
                
                <div class="alert alert-info text-start mb-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>What happens next:</h6>
                    <ul class="mb-0 small">
                        <li>You'll be paired with another student</li>
                        <li>Discuss the question together</li>
                        <li>Submit ONE answer as a pair</li>
                    </ul>
                </div>
                
                <div class="d-flex align-items-center justify-content-center text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    <small>Waiting for pairing...</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PHASE: Chat (Share) -->
    <div id="phase-chat" class="phase-screen" style="display: none;">
        <div class="card mx-auto" style="max-width: 700px;">
            <div class="card-header py-3" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                <div class="d-flex align-items-center text-white">
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">Paired with: <span id="partner-name">Loading...</span></div>
                        <small class="opacity-75" id="chat-game-name"><?php echo escape($entry['game_name']); ?></small>
                    </div>
                    <span class="badge bg-success">SHARE PHASE</span>
                </div>
            </div>
            
            <div class="card-body p-0">
                <!-- Question Display -->
                <div class="p-3 bg-light border-bottom">
                    <small class="text-uppercase fw-bold text-primary d-block mb-1">
                        <i class="bi bi-question-circle me-1"></i>Question to Discuss
                    </small>
                    <div class="fw-medium" id="chat-question-text">Loading...</div>
                </div>
                
                <!-- Chat Messages -->
                <div class="chat-messages p-3" id="chatMessages" style="height: 280px;">
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Loading messages...
                    </div>
                </div>
                
                <!-- Chat Input -->
                <div class="chat-input-container border-top" id="chat-input-area">
                    <div class="input-group">
                        <input type="text" class="form-control border-0" id="msgInput" 
                               placeholder="Discuss with your partner..." style="border-radius: 0;">
                        <button class="btn btn-primary px-3" id="sendMsgBtn" style="border-radius: 0;">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Answer Submission -->
                <div class="p-3 bg-light border-top" id="answer-section">
                    <div id="answer-form">
                        <label class="form-label fw-bold small">
                            <i class="bi bi-pencil-square me-1"></i>Submit Your Pair's Final Answer
                        </label>
                        
                        <!-- Multiple Choice Options -->
                        <div class="d-grid gap-2" id="answer-options">
                            <button type="button" class="btn btn-outline-primary text-start answer-option" data-answer="A">
                                <strong>A.</strong> <span id="option-a-text">Loading...</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary text-start answer-option" data-answer="B">
                                <strong>B.</strong> <span id="option-b-text">Loading...</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary text-start answer-option" data-answer="C">
                                <strong>C.</strong> <span id="option-c-text">Loading...</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary text-start answer-option" data-answer="D">
                                <strong>D.</strong> <span id="option-d-text">Loading...</span>
                            </button>
                        </div>
                        
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>Select an answer, then click Submit when ready
                        </small>
                        
                        <button type="button" class="btn btn-success w-100 mt-3" id="submitAnswerBtn" disabled>
                            <i class="bi bi-send-check me-2"></i>Submit Answer
                        </button>
                    </div>
                    
                    <div id="answer-submitted" style="display: none;">
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Answer Submitted!</strong>
                            <div class="small mt-1">Your answer: <strong id="submitted-answer-text"></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PHASE: Results/Complete -->
    <div id="phase-complete" class="phase-screen" style="display: none;">
        <div class="card mx-auto" style="max-width: 450px;">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <div class="bg-success bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="bi bi-check-lg text-success" style="font-size: 3rem;"></i>
                    </div>
                </div>
                
                <h4 class="fw-bold text-success mb-2">Great Work!</h4>
                <p class="text-muted mb-4">You've completed this round with <span id="complete-partner-name">your partner</span>!</p>
                
                <div class="alert alert-light text-start mb-4">
                    <small class="text-uppercase fw-bold text-muted d-block mb-1">
                        <i class="bi bi-pencil-square me-1"></i>Your Pair's Answer
                    </small>
                    <div class="fw-medium" id="complete-answer-text">-</div>
                </div>
                
                <div id="waiting-next-question" class="d-flex align-items-center justify-content-center text-muted mb-4">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    <span id="waiting-next-msg">Waiting for next question from teacher...</span>
                </div>
                
                <hr class="my-4" id="leave-game-divider">
                
                <p class="text-muted small mb-3">Want to leave?</p>
                <form method="POST" action="dashboard.php" id="leaveGameForm">
                    <input type="hidden" name="leave_game" value="<?php echo $game_id; ?>">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-box-arrow-left me-2"></i>Leave Game
                    </button>
                </form>
            </div>
        </div>
    </div>
    
</div>

<script>
// Game state
const GAME_ID = <?php echo $game_id; ?>;
const STUDENT_ID = <?php echo $student_id; ?>;
let currentPhase = null;
let pairId = null;
let lastMessageId = 0;
let timerInterval = null;
let pollInterval = null;
let remainingSeconds = 0;
let lastQuestionOrder = 0; // Track question changes for reset

// DOM Elements
const phases = {
    waiting: document.getElementById('phase-waiting'),
    question: document.getElementById('phase-question'),
    pairing: document.getElementById('phase-pairing'),
    chat: document.getElementById('phase-chat'),
    complete: document.getElementById('phase-complete')
};

// Show a specific phase
function showPhase(phase) {
    if (currentPhase === phase) return;
    
    // Hide all phases
    Object.values(phases).forEach(el => el.style.display = 'none');
    
    // Show requested phase
    if (phases[phase]) {
        phases[phase].style.display = 'block';
        currentPhase = phase;
        console.log('Switched to phase:', phase);
    }
}

// Update timer display
function updateTimer() {
    if (remainingSeconds <= 0) {
        document.getElementById('question-timer').textContent = '00:00';
        document.getElementById('question-timer').style.color = '#dc3545';
        document.getElementById('question-waiting-msg').style.display = 'none';
        document.getElementById('timer-ended-msg').style.display = 'block';
        return;
    }
    
    remainingSeconds--;
    const mins = Math.floor(remainingSeconds / 60);
    const secs = remainingSeconds % 60;
    const timerEl = document.getElementById('question-timer');
    timerEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    
    // Warning colors
    if (remainingSeconds < 60) {
        timerEl.style.color = '#dc3545';
        timerEl.style.animation = 'pulse 1s infinite';
    } else if (remainingSeconds < 180) {
        timerEl.style.color = '#f59e0b';
    }
}

// Poll for game status
async function pollGameStatus() {
    try {
        const response = await fetch(`ajax/game_status.php?game_id=${GAME_ID}&t=${Date.now()}`, {
            cache: 'no-store'
        });
        const data = await response.json();
        
        if (data.error) {
            console.error('Status error:', data.error);
            if (data.action === 'redirect') {
                window.location.href = data.url;
            }
            return;
        }
        
        // Update based on phase
        handlePhaseUpdate(data);
        
    } catch (error) {
        console.error('Poll error:', error);
    }
}

// Handle phase updates from server
function handlePhaseUpdate(data) {
    const phase = data.phase;
    
    // Detect new question — reset answer form and timer
    const currentOrder = data.current_question_order || 0;
    if (currentOrder > 0 && currentOrder !== lastQuestionOrder) {
        lastQuestionOrder = currentOrder;
        // Reset answer form for new question
        selectedAnswer = null;
        document.getElementById('answer-form').style.display = 'block';
        document.getElementById('answer-submitted').style.display = 'none';
        document.getElementById('submitAnswerBtn').disabled = true;
        document.getElementById('submitAnswerBtn').innerHTML = '<i class="bi bi-send-check me-2"></i>Submit Answer';
        document.querySelectorAll('.answer-option').forEach(b => {
            b.disabled = false;
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-primary');
        });
        // Reset chat messages for new question
        lastMessageId = 0;
        window.chatLoading = false;
        document.getElementById('chatMessages').innerHTML = `
            <div class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm me-2"></div>
                Loading messages...
            </div>
        `;
        // Reset timer
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        remainingSeconds = 0;
    }

    // Update question progress badge
    if (currentOrder > 0 && data.total_questions > 0) {
        document.getElementById('question-progress-badge').style.display = 'inline-block';
        document.getElementById('question-progress-text').textContent = 'Q ' + currentOrder + '/' + data.total_questions;
    }

    // Update question text and options if available
    if (data.question) {
        document.getElementById('question-text').textContent = data.question;
        document.getElementById('chat-question-text').textContent = data.question;
        
        // Update multiple choice options
        if (data.option_a) document.getElementById('option-a-text').textContent = data.option_a;
        if (data.option_b) document.getElementById('option-b-text').textContent = data.option_b;
        if (data.option_c) document.getElementById('option-c-text').textContent = data.option_c;
        if (data.option_d) document.getElementById('option-d-text').textContent = data.option_d;
    }
    
    // Update timer
    if (data.remaining_seconds !== undefined && phase === 'question') {
        // Only update if significantly different (avoid jitter)
        if (Math.abs(data.remaining_seconds - remainingSeconds) > 2) {
            remainingSeconds = data.remaining_seconds;
        }
    }
    
    // Update partner info
    if (data.partner_name) {
        document.getElementById('partner-name').textContent = data.partner_name;
        document.getElementById('complete-partner-name').textContent = data.partner_name;
    }
    
    // Store pair ID
    if (data.pair_id) {
        pairId = data.pair_id;
    }
    
    // Handle submitted answer
    if (data.has_submitted && data.pair_answer) {
        document.getElementById('answer-form').style.display = 'none';
        document.getElementById('answer-submitted').style.display = 'block';
        document.getElementById('submitted-answer-text').textContent = data.pair_answer;
        document.getElementById('complete-answer-text').textContent = data.pair_answer;
    }

    // Update complete screen messaging based on whether more questions remain
    const hasMoreQuestions = data.total_questions > 0 && currentOrder < data.total_questions;
    const waitingMsg = document.getElementById('waiting-next-msg');
    if (waitingMsg) {
        if (data.game_status === 'ended') {
            waitingMsg.textContent = 'Game has ended. Checking your results!';
        } else if (hasMoreQuestions) {
            waitingMsg.textContent = 'Waiting for next question from teacher...';
        } else {
            waitingMsg.textContent = 'All questions done! Waiting for teacher to end the game...';
        }
    }
    
    // Switch phase
    switch (phase) {
        case 'waiting':
            showPhase('waiting');
            break;
            
        case 'question':
            showPhase('question');
            // Start timer if not running
            if (!timerInterval && data.remaining_seconds > 0) {
                remainingSeconds = data.remaining_seconds;
                updateTimer();
                timerInterval = setInterval(updateTimer, 1000);
            }
            break;
            
        case 'pairing_wait':
            showPhase('pairing');
            // Clear timer
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            break;
            
        case 'chat':
            showPhase('chat');
            // Start loading messages if we have pair ID
            if (pairId && !window.chatLoading) {
                loadMessages();
            }
            break;
            
        case 'ended':
            showPhase('complete');
            break;
    }
    
    // If answer submitted, show complete screen
    if (data.has_submitted && phase === 'chat') {
        showPhase('complete');
    }
}

// Load chat messages
async function loadMessages() {
    if (!pairId) return;
    window.chatLoading = true;
    
    try {
        const response = await fetch(`ajax/get_messages.php?pair_id=${pairId}&last_id=${lastMessageId}&t=${Date.now()}`, {
            cache: 'no-store'
        });
        const data = await response.json();
        
        if (data.messages && data.messages.length > 0) {
            const container = document.getElementById('chatMessages');
            
            // Clear loading message on first load
            if (lastMessageId === 0) {
                container.innerHTML = '';
            }
            
            data.messages.forEach(msg => {
                if (document.getElementById('msg-' + msg.id)) return;
                
                const div = document.createElement('div');
                div.id = 'msg-' + msg.id;
                div.className = 'message ' + (msg.sender_id == STUDENT_ID ? 'message-sent' : 'message-received');
                div.innerHTML = `
                    <div class="small fw-bold mb-1 ${msg.sender_id == STUDENT_ID ? 'text-white-50' : 'text-primary'}">${escapeHtml(msg.sender_name)}</div>
                    <div>${escapeHtml(msg.message)}</div>
                    <div class="small mt-1 ${msg.sender_id == STUDENT_ID ? 'text-white-50' : 'text-muted'}">${msg.sent_at}</div>
                `;
                container.appendChild(div);
                lastMessageId = Math.max(lastMessageId, msg.id);
            });
            
            container.scrollTop = container.scrollHeight;
        } else if (lastMessageId === 0) {
            document.getElementById('chatMessages').innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="bi bi-chat-dots fs-1 mb-2 d-block"></i>
                    <p class="mb-0">Start discussing with your partner!</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Load messages error:', error);
    }
}

// Send message
async function sendMessage() {
    const input = document.getElementById('msgInput');
    const message = input.value.trim();
    if (!message || !pairId) return;
    
    const btn = document.getElementById('sendMsgBtn');
    btn.disabled = true;
    
    try {
        const response = await fetch('ajax/send_message.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `pair_id=${pairId}&message=${encodeURIComponent(message)}`
        });
        const data = await response.json();
        
        if (data.success) {
            input.value = '';
            await loadMessages();
        } else {
            alert(data.error || 'Failed to send');
        }
    } catch (error) {
        console.error('Send error:', error);
        alert('Failed to send message');
    } finally {
        btn.disabled = false;
        input.focus();
    }
}

// Submit pair answer
let selectedAnswer = null;

// Handle answer option clicks - select only, do NOT auto-submit
document.addEventListener('click', (e) => {
    if (e.target.closest('.answer-option')) {
        const btn = e.target.closest('.answer-option');
        selectedAnswer = btn.dataset.answer;
        
        // Remove selection from all buttons
        document.querySelectorAll('.answer-option').forEach(b => {
            b.classList.remove('btn-primary', 'btn-outline-primary');
            b.classList.add('btn-outline-primary');
        });
        
        // Highlight selected button
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary');
        
        // Enable submit button
        document.getElementById('submitAnswerBtn').disabled = false;
    }
});

// Submit button click
document.getElementById('submitAnswerBtn').addEventListener('click', () => {
    if (selectedAnswer) submitAnswer(selectedAnswer);
});

async function submitAnswer(answer) {
    if (!answer || !pairId) {
        alert('Please select an answer');
        return;
    }
    
    // Disable all buttons and show loading
    document.querySelectorAll('.answer-option').forEach(btn => btn.disabled = true);
    const submitBtn = document.getElementById('submitAnswerBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    
    try {
        const response = await fetch('ajax/submit_answer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `pair_id=${pairId}&answer=${encodeURIComponent(answer)}`
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('answer-form').style.display = 'none';
            document.getElementById('answer-submitted').style.display = 'block';
            document.getElementById('submitted-answer-text').textContent = answer;
            document.getElementById('complete-answer-text').textContent = answer;
            
            // Show complete screen
            setTimeout(() => showPhase('complete'), 1000);
        } else {
            alert(data.error || 'Failed to submit');
            document.querySelectorAll('.answer-option').forEach(btn => btn.disabled = false);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send-check me-2"></i>Submit Answer';
        }
    } catch (error) {
        console.error('Submit error:', error);
        alert('Failed to submit answer');
        document.querySelectorAll('.answer-option').forEach(btn => btn.disabled = false);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send-check me-2"></i>Submit Answer';
    }
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.getElementById('sendMsgBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendMessage();
});

// Start polling
pollGameStatus();
pollInterval = setInterval(pollGameStatus, 2000);

// Also poll messages when in chat phase
setInterval(() => {
    if (currentPhase === 'chat' && pairId) {
        loadMessages();
    }
}, 2000);

// Cleanup
window.addEventListener('beforeunload', () => {
    clearInterval(pollInterval);
    clearInterval(timerInterval);
});

// Add pulse animation
const style = document.createElement('style');
style.textContent = '@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }';
document.head.appendChild(style);
</script>

<?php require_once '../inc/footer.php'; ?>
