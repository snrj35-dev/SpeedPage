<?php
declare(strict_types=1);

// user_auth.php — Login Check + CSRF Token Generation

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Note: CSRF token and check_csrf() are now in settings.php

