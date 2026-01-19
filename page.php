<?php
declare(strict_types=1);

// SpeedPage SPA API Layer
header("Content-Type: application/json; charset=utf-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/settings.php";
require_once __DIR__ . "/admin/db.php";

/** @var PDO $db */
global $db;

$response = [
    "ok" => false,
    "html" => "",
    "assets" => ["css" => [], "js" => []]
];

try {
    // 1. Initial Logic Checks
    if (!$db) {
        throw new RuntimeException("db_connection_error");
    }

    // Load dynamic settings
    $settingsStmt = $db->query("SELECT `key`, `value` FROM settings");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    require_once PHP_DIR . "theme-init.php";

    // 2. Input Handling
    $rawSlug = (string) ($_GET["page"] ?? $_POST["page"] ?? "home");
    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($rawSlug)) ?: "home";

    if (empty($slug)) {
        $response['html'] = "<div class='alert alert-danger'><span lang='invalid_page_slug'>" . __('invalid_page_slug') . "</span></div>";
        echo json_encode($response);
        exit;
    }

    // 3. Hierarchy & Rendering Logic
    $content = null;
    $found = false;
    $page_id = null;

    // A. DB Check
    $stmt = $db->prepare("SELECT id, is_active, content FROM pages WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $pageRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pageRow) {
        $page_id = (int) $pageRow['id'];
        if (!(int) ($pageRow['is_active'] ?? 1)) {
            $response['html'] = "<div class='alert alert-secondary'><span lang='page_passive'>" . __('page_passive') . "</span></div>";
            echo json_encode($response);
            exit;
        }

        if (!empty($pageRow['content'])) {
            $rawContent = $pageRow['content'];

            // Secure PHP Execution: Write to temp file and include
            $tempDir = ROOT_DIR . 'admin/_temp/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
                file_put_contents($tempDir . '.htaccess', "Deny from all");
                file_put_contents($tempDir . 'index.html', "");
            }
            $tempFile = $tempDir . 'page_' . $slug . '_' . md5($rawContent) . '.php';

            if (!file_exists($tempFile)) {
                // Clean old temp files for this slug
                array_map('unlink', glob($tempDir . 'page_' . $slug . '_*.php'));
                file_put_contents($tempFile, $rawContent);
            }

            ob_start();
            try {
                include $tempFile;
            } catch (Throwable $e) {
                echo "<div class='alert alert-danger'>Execution Error: " . e($e->getMessage()) . "</div>";
            }
            $content = ob_get_clean();
            $found = true;
        }
    }

    // B. Module Check
    if (!$found) {
        $moduleFiles = [
            ROOT_DIR . "modules/$slug/$slug.php",
            ROOT_DIR . "modules/$slug/index.php"
        ];

        foreach ($moduleFiles as $mFile) {
            if (file_exists($mFile)) {
                ob_start();
                include $mFile;
                $content = ob_get_clean();
                $found = true;
                break;
            }
        }
    }

    // C. Theme Check
    if (!$found) {
        $themeFile = ROOT_DIR . "themes/" . ACTIVE_THEME . "/$slug.php";
        if (file_exists($themeFile)) {
            ob_start();
            include $themeFile;
            $content = ob_get_clean();
            $found = true;
        }
    }

    // D. Fallback (404)
    if (!$found) {
        if ($slug !== 'home') {
            $response['html'] = "<div class='alert alert-warning'><span lang='page_not_found'>" . __('page_not_found') . "</span></div>";
            echo json_encode($response);
            exit;
        }
    }

    // 4. Assets Collection
    if ($page_id) {
        $assetStmt = $db->prepare("SELECT type, path FROM page_assets WHERE page_id = ? ORDER BY load_order ASC");
        $assetStmt->execute([$page_id]);
        $assetRows = $assetStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($assetRows as $a) {
            $path = (string) $a['path'];
            $finalPath = $path;

            // Smart Path Resolution
            if (!str_starts_with($path, 'modules/') && !str_starts_with($path, 'themes/') && !str_starts_with($path, 'http') && !str_starts_with($path, '/')) {
                $prefix = ($a['type'] === 'css') ? 'cdn/css/' : 'cdn/js/';
                $finalPath = $prefix . $path;
            }

            if ($a['type'] === 'css') {
                $response['assets']['css'][] = $finalPath;
            } elseif ($a['type'] === 'js') {
                $response['assets']['js'][] = $finalPath;
            }
        }
    }

    // 5. Final Output Filter
    if (function_exists('run_hook')) {
        $content = (string) run_hook('content_filter', $content);
    }

    $response['ok'] = true;
    $response['html'] = $content;

} catch (Throwable $e) {
    if (function_exists('sp_log')) {
        sp_log("SPA API Critical Error ($slug): " . $e->getMessage(), "system_error");
    }
    $response['ok'] = false;
    $response['html'] = "<div class='alert alert-danger'><span lang='system_error_generic'>" . __('system_error_generic') . "</span> (" . e($e->getMessage()) . ")</div>";
}

echo json_encode($response);
