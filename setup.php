<?php
/**
 * TPS SYSTEM - Database Setup Wizard
 *
 * SECURITY: This page is locked behind a one-time token.
 * Set the environment variable  SETUP_TOKEN=<your-secret>  before visiting.
 * Once setup is complete, either remove that env var or delete this file.
 *
 * Usage: https://your-app.onrender.com/setup.php?token=<your-secret>
 */

// ── Token guard ───────────────────────────────────────────────────────────────
$setupToken = getenv('SETUP_TOKEN');

// If no token is configured at all, block access entirely in production.
if (getenv('APP_ENV') === 'production' && empty($setupToken)) {
    http_response_code(404);
    exit('Not found.');
}

if (!empty($setupToken)) {
    $provided = $_GET['token'] ?? '';
    if (!hash_equals($setupToken, $provided)) {
        http_response_code(403);
        exit('Forbidden. Provide the correct ?token= to run setup.');
    }
}

// ── Read DB credentials from env vars (same as config.php) ───────────────────
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '3307';
$dbname   = getenv('DB_NAME') ?: 'tps_system';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPS System Setup</title>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 0; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 700px; margin: 0 auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); padding: 40px; }
        h2  { text-align: center; color: #1e293b; margin-bottom: 8px; }
        p.sub { text-align:center; color:#64748b; margin-bottom:24px; }
        pre { background: #1e293b; color: #22c55e; border-radius: 12px; padding: 20px; font-size: 13px; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        .ok   { color: #22c55e; }
        .err  { color: #ef4444; }
        .info { color: #60a5fa; }
        .alert { border-radius: 12px; padding: 16px 20px; margin-top: 24px; }
        .alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fffbeb; border: 1px solid #fde68a; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        .btn { display:inline-block; margin-top:24px; padding:12px 28px; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-radius:10px; text-decoration:none; font-weight:700; }
        h6 { margin:0 0 8px; font-size:0.95rem; }
        table { width:100%; border-collapse:collapse; margin-top:8px; font-size:0.9rem; }
        th,td { text-align:left; padding:6px 10px; border-bottom:1px solid #e2e8f0; }
        th { color:#64748b; font-weight:600; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>🎓 TPS System Setup</h2>
        <p class="sub">Database installation wizard</p>

        <pre><?php
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<span class='ok'>✅ Connected to MySQL at {$host}:{$port}</span>\n";

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    echo "<span class='ok'>✅ Database '{$dbname}' ready</span>\n\n";

    // ── Tables ────────────────────────────────────────────────────────────────
    echo "<span class='info'>📋 Creating tables...</span>\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('student','teacher') NOT NULL DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    echo "<span class='ok'>  ✅ users</span>\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_answer ENUM('A','B','C','D') NOT NULL,
        week VARCHAR(50),
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");
    echo "<span class='ok'>  ✅ questions</span>\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status ENUM('waiting','active','pairing','chatting','ended') DEFAULT 'waiting',
        started_at DATETIME DEFAULT NULL,
        question_end_time DATETIME DEFAULT NULL,
        current_question_order INT DEFAULT 0,
        total_questions INT DEFAULT 0,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");
    echo "<span class='ok'>  ✅ games</span>\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS game_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('waiting','answering','ready_for_pairing','paired','chatting') DEFAULT 'waiting',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_entry (game_id, student_id)
    ) ENGINE=InnoDB");
    echo "<span class='ok'>  ✅ game_entries</span>\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS assigned_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT NOT NULL,
        student_id INT NOT NULL,
        question_id INT NOT NULL,
        student_answer ENUM('A','B','C','D'),
        is_correct TINYINT(1) DEFAULT NULL,
        answered_at DATETIME DEFAULT NULL,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    echo "<span class='ok'>  ✅ assigned_questions</span>\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS pairs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT NOT NULL,
        student1_id INT NOT NULL,
        student2_id INT NOT NULL,
        pair_answer ENUM('A','B','C','D'),
        answered_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
        FOREIGN KEY (student1_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (student2_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    echo "<span class='ok'>  ✅ pairs</span>\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pair_id INT NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    echo "<span class='ok'>  ✅ messages</span>\n\n";

    // ── Default accounts ──────────────────────────────────────────────────────
    echo "<span class='info'>👤 Creating default accounts...</span>\n";
    $hash = password_hash('password', PASSWORD_DEFAULT);
    $accounts = [
        ['teacher',  $hash, 'Default Teacher', 'teacher'],
        ['student1', $hash, 'John Doe',         'student'],
        ['student2', $hash, 'Jane Smith',        'student'],
        ['student3', $hash, 'Bob Wilson',        'student'],
        ['student4', $hash, 'Alice Brown',       'student'],
    ];
    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $ins = $pdo->prepare("INSERT INTO users (username,password,full_name,role) VALUES (?,?,?,?)");
    foreach ($accounts as $acc) {
        $chk->execute([$acc[0]]);
        if ($chk->fetchColumn() == 0) {
            $ins->execute($acc);
            echo "<span class='ok'>  ✅ {$acc[0]} ({$acc[3]})</span>\n";
        } else {
            echo "<span class='info'>  ℹ️  {$acc[0]} already exists — skipped</span>\n";
        }
    }

    // ── Questions (38 post-test) ──────────────────────────────────────────────
    echo "\n<span class='info'>📝 Loading 38 questions...</span>\n";
    $qcount = (int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    if ($qcount >= 38) {
        echo "<span class='info'>  ℹ️  Questions already loaded ({$qcount} found) — skipped</span>\n";
    } else {
        $pdo->exec("DELETE FROM questions");
        $qs = [
            // Week 1
            ['Maria bought 3 pencils at ₱8.75 each and an eraser at ₱12.50. How much did she pay?','₱38.75','₱39.75','₱40.75','₱41.00','A','Week 1'],
            ['A bag costs ₱525.75. If you pay ₱1,000.00, how much change will you receive?','₱474.25','₱475.00','₱476.25','₱473.75','A','Week 1'],
            ['Compute: ₱250.00 – ₱125.75 = ?','₱124.25','₱125.00','₱126.25','₱123.75','A','Week 1'],
            ['A toy costs ₱99.50. If you buy 4 toys, how much will you pay?','₱398.00','₱399.50','₱400.00','₱401.00','A','Week 1'],
            ['Solve: ₱1,200.00 ÷ 8 = ?','₱150.00','₱160.00','₱140.00','₱125.00','A','Week 1'],
            // Week 2
            ['Simplify: (15 – 3) × 2 + 8','32','28','30','26','A','Week 2'],
            ['Solve: 36 ÷ (4 + 2)','6','8','9','12','A','Week 2'],
            ['Simplify: (25 – 5) ÷ 5','4','5','6','7','A','Week 2'],
            ['Compute: 10 + (6 × 3)','28','25','30','27','A','Week 2'],
            ['Solve: (48 ÷ 8) + 15','21','20','22','18','A','Week 2'],
            // Week 3
            ['Identify the solid figure with 2 circular faces and 1 curved surface.','Cone','Cylinder','Sphere','Pyramid','B','Week 3'],
            ['Name the solid figure with 1 circular face and a pointed top.','Cone','Cylinder','Sphere','Prism','A','Week 3'],
            ['Which solid figure has 6 rectangular faces?','Cube','Rectangular Prism','Pyramid','Cylinder','B','Week 3'],
            ['Identify the solid figure with 8 triangular faces.','Octahedron','Cube','Cone','Prism','A','Week 3'],
            // Week 4
            ['Which has two parallel bases?','Pyramid','Prism','Cone','Sphere','B','Week 4'],
            ['How many faces does a rectangular prism have?','4','6','8','12','B','Week 4'],
            ['How many edges does a triangular pyramid have?','6','8','10','12','A','Week 4'],
            ['Which solid figure has only one base and triangular faces?','Prism','Pyramid','Cone','Cylinder','B','Week 4'],
            // Week 5
            ['How many squares are needed for a cube net?','4','5','6','8','C','Week 5'],
            ['Which solid figure can be formed from a net with 2 triangles and 3 rectangles?','Cube','Triangular Prism','Square Pyramid','Cone','B','Week 5'],
            ['How many rectangles and triangles are needed for a triangular prism net?','2 rectangles, 3 triangles','3 rectangles, 2 triangles','4 rectangles, 2 triangles','2 rectangles, 4 triangles','B','Week 5'],
            ['Which solid figure can be formed from a net with 1 square and 4 triangles?','Cube','Square Pyramid','Triangular Prism','Cone','B','Week 5'],
            ['How many faces does the net of a cube have?','4','5','6','8','C','Week 5'],
            // Week 6
            ['A rectangular prism has dimensions 6 cm × 4 cm × 2 cm. Find its surface area.','52 cm²','88 cm²','100 cm²','104 cm²','B','Week 6'],
            ['A cube has an edge of 7 cm. Find its surface area.','294 cm²','343 cm²','392 cm²','420 cm²','A','Week 6'],
            ['A rectangular prism has dimensions 8 cm × 5 cm × 3 cm. Find its surface area.','158 cm²','230 cm²','242 cm²','248 cm²','A','Week 6'],
            ['A cube has an edge of 10 cm. Find its surface area.','600 cm²','800 cm²','1,000 cm²','1,200 cm²','A','Week 6'],
            ['A rectangular prism has dimensions 12 cm × 6 cm × 4 cm. Find its surface area.','288 cm²','300 cm²','320 cm²','336 cm²','A','Week 6'],
            // Week 7
            ['How many faces does a rectangular prism have?','4','6','8','12','B','Week 7'],
            ['How many edges does a cube have?','8','10','12','14','C','Week 7'],
            ['Compare: How are the vertices of a cube similar to those of a rectangular prism?','Both have 6 vertices','Both have 8 vertices','Both have 10 vertices','Both have 12 vertices','B','Week 7'],
            ['Which has rectangular faces: cube or rectangular prism?','Cube','Rectangular Prism','Both','Neither','C','Week 7'],
            ['How many vertices does a rectangular prism have?','6','8','10','12','B','Week 7'],
            // Week 8
            ['If a square is rotated 90° clockwise about a point, what does the image look like?','Same as original','Upside down','Rotated but same shape','Distorted','C','Week 8'],
            ['If a rectangle is rotated 180°, what does the image look like?','Same as original','Upside down','Mirror image','Distorted','A','Week 8'],
            ['If a triangle is rotated 90° counterclockwise, what does the image look like?','Same as original','Rotated but same shape','Mirror image','Distorted','B','Week 8'],
            ['If a square is rotated 270° clockwise, what does the image look like?','Same as original','Rotated but same shape','Mirror image','Distorted','B','Week 8'],
            ['What happens to the image of a hexagon when rotated 360°?','Same as original','Upside down','Mirror image','Smaller','A','Week 8'],
        ];
        $ins = $pdo->prepare("INSERT INTO questions (question_text,option_a,option_b,option_c,option_d,correct_answer,week,created_by) VALUES (?,?,?,?,?,?,?,1)");
        foreach ($qs as $q) { $ins->execute($q); }
        echo "<span class='ok'>  ✅ 38 questions loaded</span>\n";
    }

    // ── Sample game ───────────────────────────────────────────────────────────
    $gc = (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
    if ($gc == 0) {
        $pdo->exec("INSERT INTO games (name,status,created_by) VALUES ('Sample Game Session','waiting',1)");
        echo "<span class='ok'>  ✅ Sample game created</span>\n";
    }

    echo "\n<span class='ok'>════════════════════════════════════════</span>\n";
    echo "<span class='ok'>🎉 SETUP COMPLETE! System is ready.</span>\n";
    echo "<span class='ok'>════════════════════════════════════════</span>\n";
    echo "\n<span class='info'>⚠️  For security: remove or unset SETUP_TOKEN after setup.</span>\n";

} catch (PDOException $e) {
    echo "<span class='err'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    if (getenv('APP_ENV') !== 'production') {
        echo "<span class='info'>Host: {$host}:{$port}  DB: {$dbname}  User: {$username}</span>\n";
    }
}
        ?></pre>

        <div class="alert alert-info">
            <h6>🔑 Default Accounts</h6>
            <table>
                <tr><th>Role</th><th>Username</th><th>Password</th></tr>
                <tr><td>Teacher</td><td><code>teacher</code></td><td><code>password</code></td></tr>
                <tr><td>Student</td><td><code>student1</code> – <code>student4</code></td><td><code>password</code></td></tr>
            </table>
            <p style="margin:10px 0 0;font-size:0.85rem;color:#1e40af;">
                ⚠️ Change these passwords immediately after your first login in a production environment.
            </p>
        </div>

        <div class="alert alert-warning">
            <h6>🔒 Security reminder</h6>
            <p style="margin:0;font-size:0.85rem;color:#92400e;">
                After setup is complete, remove the <code>SETUP_TOKEN</code> environment variable
                on Render (Dashboard → Environment) or delete <code>setup.php</code> entirely.
                The <code>.htaccess</code> also blocks this file from direct access in production.
            </p>
        </div>

        <div style="text-align:center;">
            <a href="auth/login.php" class="btn">🚀 Go to Login</a>
        </div>
    </div>
</div>
</body>
</html>
