<?php
declare(strict_types=1);
session_start();

// Tüm oturum değişkenlerini temizle
$_SESSION = array();

// Oturum çerezini (cookie) sonlandır
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Oturumu tamamen yok et
session_destroy();

// Kullanıcıyı kamusal ana sayfaya yönlendir
header("Location: ../index.php");
exit();
?>