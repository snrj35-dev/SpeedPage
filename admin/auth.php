<?php
// auth.php — Session & access control

session_start();

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
            $stmt->execute([(int)$_SESSION['user_id']]);
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

// Helper: is current user admin?
$role = $role ?? 'user';
$is_admin = ($role === 'admin');

// Eğer admin değilse index.php'ye gönder
if (!$is_admin) {
    header("Location: ../index.php");
    exit(); // Kodun devamının çalışmasını engellemek için kritik
}

// Buradan aşağısı sadece adminlerin görebileceği kısımdır