<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = filter_input(INPUT_POST, 'csrf', FILTER_SANITIZE_SPECIAL_CHARS);
    if (!$csrf || $csrf !== $_SESSION['csrf']) {
        die("CSRF verification failed");
    }

    try {
        // Dil Yükleme
        if (!empty($_FILES['new_lang_file']['name'])) {
            $file = $_FILES['new_lang_file'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if ($ext === 'json') {
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
                $dest = __DIR__ . '/../cdn/lang/' . strtolower($safeName) . '.json';

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if (function_exists('sp_log')) {
                        sp_log("Yeni dil dosyası yüklendi: " . $safeName, "lang_upload");
                    }
                }
            }
        }

        $db->beginTransaction();

        $allowed_keys = $db->query("SELECT `key` FROM settings")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($_POST as $key => $value) {
            if (in_array($key, $allowed_keys) || $key === 'new_custom_setting') {
                $stmt = $db->prepare("UPDATE settings SET `value` = ? WHERE `key` = ?");
                $stmt->execute([$value, $key]);
            }
        }

        $checkbox_keys = [
            'registration_enabled',
            'email_verification',
            'site_public',
            'allow_username_change',
            'allow_password_change',
            'login_captcha',
            'allow_user_theme',
            'friendly_url'
        ];
        foreach ($checkbox_keys as $check) {
            if (!isset($_POST[$check])) {
                $db->prepare("UPDATE settings SET `value` = '0' WHERE `key` = ?")->execute([$check]);
            }
        }

        $db->commit();

        if (function_exists('sp_log')) {
            sp_log("Sistem ayarları toplu olarak güncellendi.", 'settings_update');
        }

        // Cache Flush
        if (function_exists('sp_cache_flush')) {
            sp_cache_flush();
        }

        // --- FRIENDLY URL & .HTACCESS OTOMASYONU ---
        $htaccessFile = ROOT_DIR . '.htaccess';
        $htaccessError = false;

        $friendlyUrl = filter_input(INPUT_POST, 'friendly_url', FILTER_SANITIZE_SPECIAL_CHARS);

        if ($friendlyUrl === '1') {
            $canWrite = is_writable(ROOT_DIR) || (file_exists($htaccessFile) && is_writable($htaccessFile));

            if ($canWrite) {
                $htaccessContent = "<IfModule mod_rewrite.c>\n";
                $htaccessContent .= "RewriteEngine On\n";
                $htaccessContent .= "RewriteBase " . BASE_PATH . "\n";
                $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
                $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
                $htaccessContent .= "RewriteRule ^([^/]+)/?$ index.php?page=$1 [L,QSA]\n";
                $htaccessContent .= "</IfModule>";

                if (file_put_contents($htaccessFile, $htaccessContent) === false) {
                    $htaccessError = 'write_error';
                } else {
                    if (function_exists('sp_log')) {
                        sp_log(".htaccess oluşturuldu/güncellendi.", "htaccess_update");
                    }
                }
            } else {
                $htaccessError = 'perm_error';
            }
        } else {
            // FRIENDLY URL KAPATILDIYSA
            if (file_exists($htaccessFile)) {
                if (!@unlink($htaccessFile)) {
                    file_put_contents($htaccessFile, "");
                    if (function_exists('sp_log')) {
                        sp_log(".htaccess silinemedi, içeriği temizlendi.", "htaccess_cleared");
                    }
                } else {
                    if (function_exists('sp_log')) {
                        sp_log(".htaccess başarıyla silindi.", "htaccess_deleted");
                    }
                }
            }
        }

        // Redirect
        $tab = filter_input(INPUT_POST, 'active_tab', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'genel';
        $errorParam = $htaccessError ? "&error=" . $htaccessError : "";
        header("Location: index.php?page=settings&status=ok&tab=" . urlencode($tab) . $errorParam);
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        die("Hata: " . htmlspecialchars($e->getMessage()));
    }
}