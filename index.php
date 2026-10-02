<?php

require_once 'config.php';

// If user is logged in, redirect to their dashboard
if (isLoggedIn()) {
    if (isTeacher()) {
        redirect('teacher/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}

// If not logged in, redirect to login page
redirect('auth/login.php');
?>
