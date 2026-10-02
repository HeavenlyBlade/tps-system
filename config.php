<?php
/**
 * TPS SYSTEM - Configuration
 * Reads from environment variables for production (Render/Vercel).
 * Falls back to XAMPP defaults for local development.
 */

// ── Error reporting ──────────────────────────────────────────────────────────
// Detect environment: set APP_ENV=production on your hosting platform.
$isProduction = (getenv('APP_ENV') === 'production');

if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    // Render/hosting writes logs to stderr; php://stderr works everywhere.
    ini_set('error_log', 'php://stderr');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ── Output buffering & session ───────────────────────────────────────────────
ob_start();

// Secure session settings
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if ($isProduction) {
    ini_set('session.cookie_secure', '1');   // HTTPS only in production
    ini_set('session.cookie_samesite', 'Lax');
}

session_start();

// ── Database credentials (env vars → XAMPP fallback) ────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3307');        // XAMPP default; Render MySQL uses 3306
define('DB_NAME', getenv('DB_NAME') ?: 'tps_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ── Base URL ─────────────────────────────────────────────────────────────────
// On Render the app is deployed at the root (/), not /tps-system/.
// Locally it lives at /tps-system/.
if ($isProduction) {
    // Derive from the incoming request so it works on any domain.
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $scheme . '://' . $host);
    define('ASSET_BASE', '');   // assets are at /assets/… relative to root
} else {
    define('BASE_URL', '/tps-system');
    define('ASSET_BASE', '/tps-system');
}

// ── Game settings ────────────────────────────────────────────────────────────
define('QUESTION_TIME_SECONDS', (int)(getenv('QUESTION_TIME_SECONDS') ?: 300)); // 5 min default

// ── Database connection ──────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    if ($isProduction) {
        http_response_code(503);
        die('Service temporarily unavailable. Please try again later.');
    }
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// ── Stale-session guard ──────────────────────────────────────────────────────
// Validates the session user still exists in DB; catches stale sessions after
// a DB reset. Skipped on auth pages to avoid redirect loops.
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';
$is_auth_page   = strpos($current_script, '/auth/') !== false;

if (isset($_SESSION['user_id']) && !$is_auth_page) {
    $chk = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $chk->execute([$_SESSION['user_id']]);
    if (!$chk->fetch()) {
        session_destroy();
        $loginPath = $isProduction ? '/auth/login.php' : BASE_URL . '/auth/login.php';
        header('Location: ' . $loginPath);
        exit();
    }
}

// ── Helper functions ─────────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isTeacher(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isStudent(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function redirect(string $url): void {
    if (ob_get_level()) ob_end_clean();
    header('Location: ' . $url);
    exit();
}

function escape(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
