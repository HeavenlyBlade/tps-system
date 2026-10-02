<?php
// TPS SYSTEM - Header
if (!isset($pdo)) {
    require_once __DIR__ . '/../config.php';
}
// ASSET_BASE is '' in production (root) and '/tps-system' locally.
$ab = defined('ASSET_BASE') ? ASSET_BASE : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FF6B35">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo isset($pageTitle) ? escape($pageTitle) . ' - ' : ''; ?>TPS System</title>

    <!-- PWA -->
    <link rel="manifest" href="<?php echo $ab; ?>/manifest.json">

    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌟</text></svg>">

    <!-- Bootstrap 5 CSS (local copy) -->
    <link href="<?php echo $ab; ?>/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $ab; ?>/bootstrap/css/bootstrap-grid.min.css" rel="stylesheet">
    <link href="<?php echo $ab; ?>/bootstrap/fonts/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo $ab; ?>/assets/style.css" rel="stylesheet">

    <style>
        * { -webkit-tap-highlight-color: transparent; }
        a, button, input, select, textarea, .btn { touch-action: manipulation; }

        /* ── Background ── */
        .video-background {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            z-index: 0;
            overflow: hidden;
            background: linear-gradient(160deg, #FF6B35 0%, #FF6B9D 40%, #C77DFF 70%, #4CC9F0 100%);
        }
        .video-background video {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            min-width: 100%; min-height: 100%;
            width: auto; height: auto;
            object-fit: cover;
            object-position: center top;
        }
        .video-background::after {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(160deg,
                rgba(255,107,53,0.72) 0%,
                rgba(255,107,157,0.65) 40%,
                rgba(199,125,255,0.60) 70%,
                rgba(76,201,240,0.65) 100%);
            pointer-events: none;
            z-index: 1;
        }

        html, body {
            background: transparent;
            min-height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        .navbar, main, footer { position: relative; z-index: 10; }
        main { flex: 1; }
        footer { margin-top: auto; }

        /* ── Navbar ── */
        .navbar {
            background: rgba(255, 255, 255, 0.18) !important;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 2px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 800;
            font-size: 1.3rem;
            color: white !important;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
            letter-spacing: 0.5px;
        }
        .navbar-brand:hover { color: #FFD93D !important; }
        .nav-link { color: rgba(255,255,255,0.92) !important; font-weight: 600; }
        .nav-link:hover { color: #FFD93D !important; }
        .navbar-toggler { border: 2px solid rgba(255,255,255,0.5) !important; }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255,255,255,0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }

        /* ── Cards ── */
        .card {
            border: none;
            border-radius: 20px !important;
            box-shadow: 0 6px 24px rgba(0,0,0,0.12);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(12px);
            margin-bottom: 1rem;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.18);
        }
        .card-header {
            background: transparent;
            border-bottom: 2px dashed rgba(0,0,0,0.07);
            font-weight: 700;
            padding: 1rem 1.25rem;
            border-radius: 20px 20px 0 0 !important;
        }

        /* ── Text colors ── */
        .text-muted { color: #6b7280 !important; text-shadow: none !important; }
        .mb-4 > p.text-muted,
        .mb-4 > div > p.text-muted {
            color: rgba(255,255,255,0.95) !important;
            text-shadow: 0 1px 4px rgba(0,0,0,0.2) !important;
        }

        /* ── Page Titles ── */
        .page-title    { color: white; font-weight: 800; text-shadow: 0 2px 12px rgba(0,0,0,0.25); margin-bottom: 0.25rem; }
        .page-subtitle { color: rgba(255,255,255,0.92); text-shadow: 0 1px 5px rgba(0,0,0,0.2); margin-bottom: 1.25rem; }

        /* ── Buttons ── */
        .btn {
            font-weight: 700;
            border-radius: 14px;
            min-height: 48px;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:active { transform: translateY(0); }
        .action-btn { min-height: unset; border-radius: 50rem; }

        .btn-primary {
            background: linear-gradient(135deg, #FF6B35, #FF6B9D);
            border: none;
            box-shadow: 0 4px 14px rgba(255,107,53,0.35);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #e85e2a, #e85e8e);
            box-shadow: 0 8px 20px rgba(255,107,53,0.45);
        }
        .btn-success {
            background: linear-gradient(135deg, #06D6A0, #00B4D8);
            border: none;
            box-shadow: 0 4px 14px rgba(6,214,160,0.35);
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #05c090, #009fc0);
            box-shadow: 0 8px 20px rgba(6,214,160,0.45);
        }
        .btn-warning {
            background: linear-gradient(135deg, #FFD93D, #FF6B35);
            border: none;
            color: white !important;
            box-shadow: 0 4px 14px rgba(255,107,53,0.3);
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #f0cc35, #e85e2a);
            box-shadow: 0 8px 20px rgba(255,107,53,0.4);
        }
        .btn-danger {
            background: linear-gradient(135deg, #FF6B9D, #EF233C);
            border: none;
            box-shadow: 0 4px 14px rgba(239,35,60,0.3);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #e85e8e, #d41e35);
            box-shadow: 0 8px 20px rgba(239,35,60,0.4);
        }
        .btn-outline-success {
            border: 2px solid #06D6A0;
            color: #06D6A0;
            background: transparent;
        }
        .btn-outline-success:hover {
            background: linear-gradient(135deg, #06D6A0, #00B4D8);
            border-color: transparent;
            color: white;
            box-shadow: 0 6px 18px rgba(6,214,160,0.35);
        }
        .btn-outline-secondary {
            border: 2px solid #CBD5E1;
            color: #64748B;
        }
        .btn-outline-secondary:hover {
            background: #F1F5F9;
            border-color: #94A3B8;
        }

        /* ── Forms ── */
        .form-control, .form-select {
            border-radius: 14px;
            font-size: 16px;
            border: 2px solid #E2E8F0;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #FF6B9D;
            box-shadow: 0 0 0 3px rgba(255,107,157,0.18);
        }

        /* ── Nav Pills (tabs) ── */
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #FF6B35, #FF6B9D) !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(255,107,53,0.35);
        }
        .nav-pills .nav-link:not(.active) {
            color: #FF6B35 !important;
        }
        .nav-pills .nav-link:not(.active):hover {
            background: rgba(255,107,53,0.1);
        }

        /* ── Timer ── */
        .timer-display {
            font-size: 3rem;
            font-weight: 800;
            font-family: monospace;
            background: linear-gradient(135deg, #FF6B35, #FF6B9D);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Chat ── */
        .chat-messages {
            height: 300px;
            overflow-y: auto;
            background: #FFF9F0;
            border-radius: 14px;
            -webkit-overflow-scrolling: touch;
        }
        .message { max-width: 85%; margin-bottom: 0.75rem; padding: 0.75rem 1rem; border-radius: 18px; }
        .message-sent {
            background: linear-gradient(135deg, #FF6B35, #FF6B9D);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        .message-received {
            background: white;
            border: 2px solid #FFE0CC;
            border-bottom-left-radius: 4px;
        }

        /* ── Stats Grid ── */
        .stat-card { text-align: center; padding: 1rem; border-radius: 14px; }
        .stat-number { font-size: 1.75rem; font-weight: 800; }
        .stat-label  { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
        .status-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
        @media (min-width: 768px) { .status-grid { grid-template-columns: repeat(5, 1fr); } }

        /* ── Footer ── */
        footer {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: rgba(255,255,255,0.9);
            padding: 1rem 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 600;
            border-top: 2px solid rgba(255,255,255,0.25);
        }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            input, textarea, select { font-size: 16px !important; }
            .timer-display { font-size: 2.5rem; }
        }
        @media (hover: none) {
            .btn:hover, .card:hover { transform: none; }
        }
    </style>
</head>
<body>
    <!-- Background -->
    <div class="video-background">
        <video autoplay muted loop playsinline preload="auto">
            <source src="<?php echo $ab; ?>/assets/bg/238285.mp4" type="video/mp4">
        </video>
    </div>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid px-3">
            <a class="navbar-brand" href="<?php echo $ab; ?>/">
                🌟 TPS
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <span class="nav-link opacity-90">
                                👤 <?php echo escape($_SESSION['full_name']); ?>
                                <span class="badge ms-1 text-dark" style="background:#FFD93D;"><?php echo ucfirst($_SESSION['role']); ?></span>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $ab; ?>/<?php echo isTeacher() ? 'teacher' : 'student'; ?>/dashboard.php">
                                🏠 Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $ab; ?>/auth/logout.php" onclick="return confirmLogout(event)" style="color:#FFD93D !important;">
                                👋 Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $ab; ?>/auth/login.php">🔑 Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $ab; ?>/auth/register.php">✏️ Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-3">
