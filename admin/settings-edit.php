<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        die("CSRF verification failed");
    }

    // Güncellenecek potansiyel tüm ayar anahtarları
    // Checkbox'lar post edilmezse '0' kabul edilsin diye bu listeyi kullanıyoruz
    $available_settings = [
        'registration_enabled' => '0',
        'email_verification' => '0',
        'site_public' => '0',
        'site_name' => 'SpeedPage',
        'site_slogan' => '',
        'meta_description' => '',
        'logo_url' => '',
        'allow_username_change' => '0',
        'allow_password_change' => '1',
        'session_duration' => '3600',
        'login_captcha' => '0',
        'max_login_attempts' => '5',
        'login_block_duration' => '15',
    ];



    try {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE settings SET `value` = ? WHERE `key` = ?");

        foreach ($available_settings as $key => $default_value) {
            // Eğer formdan değer gelmişse onu kullan (Checkbox işaretliyse '1' gelir)
            $val = isset($_POST[$key]) ? $_POST[$key] : $default_value;

            $stmt->execute([$val, $key]);
        }

        $db->commit();
        header("Location: index.php?status=ok");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        die('<div class="alert alert-danger small"><span lang="errdata"></span></div>');
    }
} else {
    header("Location: index.php");
    exit;
}
