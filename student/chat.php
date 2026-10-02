<?php
// Redirect to unified game page
require_once '../config.php';
if (!isLoggedIn() || !isStudent()) redirect('../auth/login.php');
$game_id = (int)($_GET['game_id'] ?? 0);
if ($game_id) redirect('game.php?game_id=' . $game_id);
redirect('dashboard.php');
?>
