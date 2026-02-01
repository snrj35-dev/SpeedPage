<?php
declare(strict_types=1);
// auth.php — Session & access control

require_once __DIR__ . '/db.php';
/** @var PDO $db */
global $db;

// Session duration and start are now handled in settings.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Note: CSRF token is now generated automatically in settings.php

// If user is not logged in → redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

// Logged-in username
// Username + role
$username = htmlspecialchars($_SESSION['username'] ?? 'Unknown');

// Ensure role exists in session; if missing, attempt to load from DB
$role = $_SESSION['role'] ?? null;
if (!$role && isset($_SESSION['user_id'])) {
    try {
        // We already required db.php above
        $stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
        $stmt->execute([(int) $_SESSION['user_id']]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $role = $r['role'] ?? 'user';
        $_SESSION['role'] = $role;
    } catch (Throwable $e) {
        $role = 'user';
    }
}

// Helper: is current user admin or editor?
$role = $role ?? 'user';
$is_admin = ($role === 'admin');
$is_editor = ($role === 'editor');

// Eğer admin veya editor değilse index.php'ye gönder
if (!$is_admin && !$is_editor) {
    header("Location: ../index.php");
    exit(); // Kodun devamının çalışmasını engellemek için kritik
}

// Buradan aşağısı sadece adminlerin görebileceği kısımdır