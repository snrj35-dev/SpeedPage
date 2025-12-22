<?php
require_once 'auth.php';
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Güncellenecek potansiyel tüm ayar anahtarları
    // Checkbox'lar post edilmezse '0' kabul edilsin diye bu listeyi kullanıyoruz
    $available_settings = [
    'registration_enabled' => '0',
    'email_verification'   => '0',
    'site_public'          => '0',
    'site_name'            => 'SpeedPage',
    'site_slogan'          => '',
    'meta_description'     => '',
    'logo_url'             => '',
    'allow_username_change' => '0',
    'allow_password_change' => '0',
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