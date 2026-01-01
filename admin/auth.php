<?php
// auth.php — Session & access control

require_once __DIR__ . '/db.php';

// Session süresini ayarla
try {
    $s_stmt = $db->query("SELECT value FROM settings WHERE `key`='session_duration'");
    $s_duration = $s_stmt->fetchColumn();
    if ($s_duration) {
        ini_set('session.gc_maxlifetime', (int) $s_duration);
        session_set_cookie_params((int) $s_duration);
    }
} catch (Exception $e) {
}

session_start();

// XSS Protection Helper
if (!function_exists('e')) {
    function e($str)
    {
        if ($str === null)
            return '';
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

// CSRF Token generation
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

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
        if (file_exists(__DIR__ . '/db.php')) {
            require_once __DIR__ . '/db.php';
            $stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
            $stmt->execute([(int) $_SESSION['user_id']]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $role = $r['role'] ?? 'user';
            $_SESSION['role'] = $role;
        } else {
            $role = 'user';
        }
    } catch (Throwable $e) {
        $role = 'user';
    }
}

// ... (Önceki kodlarınız: session_start, DB bağlantısı vb.)

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