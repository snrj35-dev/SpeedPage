<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = filter_input(INPUT_POST, 'csrf', FILTER_SANITIZE_SPECIAL_CHARS);
    if (!$csrf || $csrf !== $_SESSION['csrf']) {
        echo json_encode(["status" => "error", "message" => __('csrf_error'), "message_key" => "csrf_error"]);
        exit;
    }
} else {
    exit;
}

if ($action === 'activate_theme') {
    handleActivateTheme($db);
} elseif ($action === 'duplicate_theme') {
    handleDuplicateTheme($db);
} elseif ($action === 'delete_theme') {
    handleDeleteTheme($db);
} elseif ($action === 'remote_install') {
    handleRemoteInstallTheme($db);
}

exit;

function handleActivateTheme(PDO $db): void
{
    $themeName = filter_input(INPUT_POST, 'theme_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'default';
    if (!is_dir(ROOT_DIR . "themes/" . $themeName)) {
        echo json_encode(["status" => "error", "message" => __('theme_upload_help')]);
        return;
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key`='active_theme'");
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        $db->prepare("UPDATE settings SET `value`=? WHERE `key`='active_theme'")->execute([$themeName]);
    } else {
        $db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('active_theme', ?)")->execute([$themeName]);
    }

    if (function_exists('sp_log'))
        sp_log("Tema aktif: $themeName", "theme_change", null, $themeName);
    echo json_encode(["status" => "success", "message" => __('module_activated')]);
}

function handleDuplicateTheme(PDO $db): void
{
    $source = filter_input(INPUT_POST, 'source', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'default';
    $newName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['new_name'] ?? '');
    $newTitle = strip_tags($_POST['new_title'] ?? $newName);

    if (!$newName) {
        echo json_encode(["status" => "error", "message" => __('missing_parameters')]);
        return;
    }

    $sourceDir = ROOT_DIR . "themes/" . $source;
    $targetDir = ROOT_DIR . "themes/" . $newName;

    if (!is_dir($sourceDir)) {
        echo json_encode(["status" => "error", "message" => __('theme_settings_not_found')]);
        return;
    }
    if (is_dir($targetDir)) {
        echo json_encode(["status" => "error", "message" => 'Theme already exists']);
        return;
    }

    mkdir($targetDir, 0755, true);
    recursiveCopy($sourceDir, $targetDir);

    $jsonPath = $targetDir . "/theme.json";
    if (file_exists($jsonPath)) {
        $conf = json_decode(file_get_contents($jsonPath), true);
        if ($conf) {
            $conf['name'] = $newName;
            $conf['title'] = $newTitle;
            file_put_contents($jsonPath, json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    if (function_exists('sp_log'))
        sp_log("Tema kopyalandı: $newName", "theme_duplicate", $source, $newName);
    echo json_encode(["status" => "success", "message" => __('operation_failed') === "İşlem başarısız." ? "Tema Kopyalandı" : "Theme Copied"]);
}

function handleDeleteTheme(PDO $db): void
{
    $themeName = filter_input(INPUT_POST, 'theme_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    if ($themeName === 'default') {
        echo json_encode(["status" => "error", "message" => "Default theme cannot be deleted"]);
        return;
    }

    $dir = ROOT_DIR . "themes/" . $themeName;
    if (!is_dir($dir)) {
        echo json_encode(["status" => "error", "message" => __('theme_settings_not_found')]);
        return;
    }

    recursiveDelete($dir);
    $db->prepare("DELETE FROM theme_settings WHERE theme_name = ?")->execute([$themeName]);

    if (function_exists('sp_log'))
        sp_log("Tema silindi: $themeName", "theme_delete", $themeName);
    echo json_encode(["status" => "success", "message" => "Tema silindi"]);
}

function handleRemoteInstallTheme(PDO $db): void
{
    $extractPath = null;
    try {
        $url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

        if (!$url || !$name) {
            throw new Exception(__('missing_params'), 400);
        }

        $parsedUrl = parse_url($url);
        $allowedHost = 'raw.githubusercontent.com';
        if (($parsedUrl['host'] ?? '') !== $allowedHost) {
            throw new Exception("Security Error: Domain not allowed. Only GitHub Raw is permitted.");
        }

        $tmpFile = ROOT_DIR . '_temp/remote_' . bin2hex(random_bytes(8)) . '.zip';
        if (!is_dir(ROOT_DIR . '_temp/')) {
            mkdir(ROOT_DIR . '_temp/', 0755, true);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SpeedPage-CMS-Remote-Installer');
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$data) {
            throw new Exception(__('download_error') . " (HTTP $httpCode)", 400);
        }

        file_put_contents($tmpFile, $data);

        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            throw new Exception(__('zip_error'), 400);
        }

        $configContent = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipName = $zip->getNameIndex($i);
            if ($zipName === 'theme.json') {
                $configContent = $zip->getFromIndex($i);
            }
            if (strpos($zipName, '..') !== false) {
                throw new Exception("Security Error: Path Traversal detected in ZIP");
            }
        }

        if (!$configContent) {
            $zip->close();
            unlink($tmpFile);
            throw new Exception("Configuration file (theme.json) not found in package.", 400);
        }

        $config = json_decode($configContent, true);
        if (!$config) {
            $zip->close();
            unlink($tmpFile);
            throw new Exception("Invalid JSON in theme.json", 400);
        }

        $extractPath = ROOT_DIR . "_temp/ext_" . bin2hex(random_bytes(8));
        mkdir($extractPath, 0755, true);
        $zip->extractTo($extractPath);
        $zip->close();
        unlink($tmpFile);

        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $config['name'] ?? $name);
        $targetDir = ROOT_DIR . "themes/" . $slug;

        if (is_dir($targetDir)) {
            recursiveDelete($targetDir);
        }
        mkdir($targetDir, 0755, true);

        recursiveCopy($extractPath, $targetDir);

        if (function_exists('sp_log'))
            sp_log("Market teması yüklendi: $slug", "theme_market_install", null, $slug);
        echo json_encode(["status" => "success", "message" => __('install_success')]);

    } catch (Throwable $e) {
        if (function_exists('sp_log'))
            sp_log("Remote Install Error: " . $e->getMessage(), "system_error");
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        if ($extractPath && is_dir($extractPath)) {
            recursiveDelete($extractPath);
        }
    }
}

function recursiveCopy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recursiveCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function recursiveDelete($dir)
{
    if (!is_dir($dir))
        return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}
