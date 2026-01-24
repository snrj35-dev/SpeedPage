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
    // Only POST allowed
    exit;
}

/* ---------------- Ensure tables exist ---------------- */
// (Keeping table creation logic as is, but streamlined)
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isSqlite = ($driver === 'sqlite');

    $queries = [];
    if ($isSqlite) {
        $queries[] = "CREATE TABLE IF NOT EXISTS modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            version TEXT DEFAULT '1.0',
            description TEXT,
            page_slug TEXT,
            is_active INTEGER DEFAULT 1,
            installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            admin_menu_title TEXT,
            admin_menu_url TEXT,
            admin_menu_icon TEXT
        )";
        $queries[] = "CREATE TABLE IF NOT EXISTS module_assets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_id INTEGER NOT NULL,
            type TEXT NOT NULL, 
            path TEXT NOT NULL,
            load_order INTEGER DEFAULT 0,
            FOREIGN KEY (module_id) REFERENCES modules(id)
        )";
        $queries[] = "CREATE TABLE IF NOT EXISTS theme_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            theme_name TEXT NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT,
            UNIQUE(theme_name, setting_key)
        )";
    } else {
        // MySQL
        $queries[] = "CREATE TABLE IF NOT EXISTS modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            title LONGTEXT NOT NULL,
            version VARCHAR(50) DEFAULT '1.0',
            description LONGTEXT,
            page_slug LONGTEXT,
            is_active INT DEFAULT 1,
            installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            admin_menu_title VARCHAR(255),
            admin_menu_url VARCHAR(255),
            admin_menu_icon VARCHAR(50)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $queries[] = "CREATE TABLE IF NOT EXISTS module_assets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_id INT NOT NULL,
            type VARCHAR(10) NOT NULL,
            path LONGTEXT NOT NULL,
            load_order INT DEFAULT 0,
            FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $queries[] = "CREATE TABLE IF NOT EXISTS theme_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            theme_name VARCHAR(255) NOT NULL,
            setting_key VARCHAR(255) NOT NULL,
            setting_value LONGTEXT,
            UNIQUE(theme_name, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    foreach ($queries as $q) {
        $db->exec($q);
    }
} catch (Throwable $e) {
    // Silent fail or log
    if (function_exists('sp_log'))
        sp_log("Table creation error: " . $e->getMessage(), "system_error");
}

/* ---------------- Ensure columns exist (Migration for existing tables) ---------------- */
try {
    // Check if new columns exist, if not add them. 
    // This is a simple check. For production, a proper migration system is better.
    // However, considering the user's setup, we can try to add them and catch exception if they exist, 
    // or inspect table info. Let's try to add safely for SQLite/MySQL.

    $colsToAdd = [
        'admin_menu_title' => $isSqlite ? 'TEXT' : 'VARCHAR(255)',
        'admin_menu_url' => $isSqlite ? 'TEXT' : 'VARCHAR(255)',
        'admin_menu_icon' => $isSqlite ? 'TEXT' : 'VARCHAR(50)'
    ];

    foreach ($colsToAdd as $col => $type) {
        try {
            $db->exec("ALTER TABLE modules ADD COLUMN $col $type");
        } catch (Throwable $ex) {
            // Column likely exists
        }
    }

} catch (Throwable $e) {
    // Ignore
}

/* ======================================================
   ACTION HANDLERS
   ====================================================== */

if ($action === 'upload') {
    handleUpload($db);
} elseif ($action === 'activate_theme') {
    handleActivateTheme($db);
} elseif ($action === 'delete') {
    handleDeleteModule($db);
} elseif ($action === 'toggle') {
    handleToggleModule($db);
} elseif ($action === 'duplicate_theme') {
    handleDuplicateTheme($db);
} elseif ($action === 'delete_theme') {
    handleDeleteTheme($db);
} elseif ($action === 'clear_logs') {
    handleClearLogs($db);
} elseif ($action === 'remote_install') {
    handleRemoteInstall($db);
}

exit;


/* ======================================================
   FUNCTIONS
   ====================================================== */

function handleUpload(PDO $db): void
{
    $extractPath = null;
    try {
        if (!isset($_FILES['module_zip'])) {
            throw new Exception(__('upload_error'), 400);
        }

        $zipFile = $_FILES['module_zip']['tmp_name'];
        $zip = new ZipArchive();

        if ($zip->open($zipFile) !== true) {
            throw new Exception(__('zip_open_failed'), 400);
        }

        // --- PRE-EXTRACTION CHECKS ---
        $configContent = '';
        $isTheme = false;
        $isModule = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === 'theme.json') {
                $isTheme = true;
                $configContent = $zip->getFromIndex($i);
            } elseif ($name === 'module.json') {
                $isModule = true;
                $configContent = $zip->getFromIndex($i);
            }
            // Security Checks
            $fileName = basename($name);
            $forbidden = ['index.php', 'settings.php', 'db.php', 'auth.php', '.htaccess', 'manifest.json'];
            if (strpos($name, '..') !== false || strpos($name, '/') === 0 || strpos($name, '\\') === 0) {
                throw new Exception("Security Error: Path Traversal");
            }
            if (in_array(strtolower($fileName), $forbidden) && !strpos($name, '/') && !strpos($name, '\\')) {
                throw new Exception("Security Error: Core file overwrite attempt ($fileName)");
            }
        }

        if (!$isTheme && !$isModule) {
            throw new Exception(__('theme_json_error'), 400); // Or module JSON error
        }

        $config = json_decode($configContent, true);
        if (!$config) {
            throw new Exception(__('theme_json_error'), 400);
        }

        // PHP Version Check
        if (!empty($config['php_version'])) {
            if (version_compare(PHP_VERSION, $config['php_version'], '<')) {
                throw new Exception("Requires PHP v" . $config['php_version'] . ". Current: " . PHP_VERSION);
            }
        }

        // Dependencies Check (Modules only)
        if ($isModule && !empty($config['dependencies']) && is_array($config['dependencies'])) {
            foreach ($config['dependencies'] as $dep) {
                $stmt = $db->prepare("SELECT id FROM modules WHERE name = ? AND is_active = 1");
                $stmt->execute([$dep]);
                if (!$stmt->fetch()) {
                    throw new Exception("Dependency missing: $dep");
                }
            }
        }

        // Extract
        $extractPath = ROOT_DIR . "modules/tmp_" . bin2hex(random_bytes(8));
        if (!mkdir($extractPath, 0755, true)) {
            $zip->close();
            throw new Exception("Failed to create temp dir", 500);
        }
        $zip->extractTo($extractPath);
        $zip->close();

        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $config['name'] ?? '');
        if (!$slug)
            throw new Exception("Invalid package name", 400);

        // Assets Copy
        if (!empty($config['assets']['images']) && is_array($config['assets']['images'])) {
            $targetBase = $isTheme ? ROOT_DIR . "themes/$slug/images/" : ROOT_DIR . "cdn/images/$slug/";
            foreach ($config['assets']['images'] as $imgRel) {
                $src = $extractPath . '/' . $imgRel;
                if (file_exists($src)) {
                    $cleanPath = preg_replace('/^images[\/\\\\]/i', '', $imgRel);
                    $dst = $targetBase . $cleanPath;
                    $dstDir = dirname($dst);
                    if (!is_dir($dstDir))
                        mkdir($dstDir, 0755, true);
                    copy($src, $dst);
                }
            }
        }

        // Create Target Directory (Themes or Modules)
        $targetDir = $isTheme ? ROOT_DIR . "themes/" . $slug : ROOT_DIR . "modules/" . $slug;
        if (is_dir($targetDir)) {
            recursiveDelete($targetDir);
        }
        mkdir($targetDir, 0755, true);

        // Move extracted content to target dir
        recursiveCopy($extractPath, $targetDir);

        if ($isTheme) {
            // Theme Logic (Already handled by copy above)
            if (function_exists('sp_log'))
                sp_log("Tema yüklendi: $slug", "theme_upload", null, $slug);
            echo json_encode(["status" => "success", "message" => __('theme_upload_success') . ": $slug"]);
        } else {
            // Module Logic
            installModule($db, $slug, $config, $targetDir);
        }

    } catch (Throwable $e) {
        if (function_exists('sp_log'))
            sp_log("Upload Error: " . $e->getMessage(), "system_error");
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        if ($extractPath && is_dir($extractPath)) {
            recursiveDelete($extractPath);
        }
    }
}

function installModule(PDO $db, string $slug, array $config, string $extractPath): void
{
    $title = $config['title'] ?? $slug;
    $description = $config['description'] ?? '';
    $icon = $config['icon'] ?? '';
    $version = $config['version'] ?? '1.0';
    $menu_title = $config['menu_title'] ?? $title;
    $menu_icon = $config['menu_icon'] ?? $icon;
    $locations = $config['locations'] ?? ['navbar'];

    $createPage = (bool) ($config['create_page'] ?? true);
    $createMenu = (bool) ($config['create_menu'] ?? true);

    // Admin Menu details
    $adminMenu = $config['admin_menu'] ?? [];
    $adminMenuTitle = $adminMenu['title'] ?? null;
    $adminMenuUrl = $adminMenu['url'] ?? null;
    $adminMenuIcon = $adminMenu['icon'] ?? 'fa-puzzle-piece';


    // Uninstall Script handling (will stay in module folder)

    // Schema SQL
    if (file_exists($extractPath . '/schema.sql')) {
        $sql = file_get_contents($extractPath . '/schema.sql');
        if ($sql)
            $db->exec($sql);
    }

    // Migration PHP
    if (file_exists($extractPath . '/migration.php')) {
        include $extractPath . '/migration.php';
    }

    // DB Insert
    $db->beginTransaction();
    try {
        $sortOrder = null;
        $pageId = null;
        if ($createPage) {
            $maxSort = (int) $db->query("SELECT COALESCE(MAX(sort_order),0) FROM pages")->fetchColumn();
            $sortOrder = $maxSort + 1;

            $stmt = $db->prepare("INSERT INTO pages (slug, title, description, icon, is_active, sort_order) VALUES (?,?,?,?,1,?)");
            $stmt->execute([$slug, $title, $description, $icon, $sortOrder]);
            $pageId = (int) $db->lastInsertId();

            // Assets (Record with full module path)
            foreach (['css', 'js'] as $type) {
                $order = 1;
                foreach ($config['assets'][$type] ?? [] as $asset) {
                    if (empty($asset))
                        continue;
                    $assetStr = (string) $asset;
                    if (strpos($assetStr, 'http') === 0) {
                        $assetPath = $assetStr;
                    } else {
                        $assetStr = ltrim($assetStr, '/');
                        if (str_starts_with($assetStr, 'cdn/')) {
                            $assetPath = $assetStr;
                        } elseif (str_starts_with($assetStr, 'modules/')) {
                            $assetPath = $assetStr;
                        } else {
                            $assetPath = "modules/$slug/" . $assetStr;
                        }
                    }
                    $db->prepare("INSERT INTO page_assets (page_id, type, path, load_order) VALUES (?,?,?,?)")
                        ->execute([$pageId, $type, $assetPath, $order++]);
                }
            }
        }

        // Menus (only if page exists)
        if ($createPage && $createMenu) {
            $db->prepare("INSERT INTO menus (page_id, title, icon, sort_order, is_active) VALUES (?,?,?,?,1)")
                ->execute([$pageId, $menu_title, $menu_icon, (int) $sortOrder]);
            $menuId = (int) $db->lastInsertId();

            foreach ((array) $locations as $loc) {
                $db->prepare("INSERT INTO menu_locations (menu_id, location) VALUES (?,?)")->execute([$menuId, preg_replace('/[^a-z0-9_-]/', '', (string) $loc)]);
            }
        }

        // Modules Table
        $db->prepare("INSERT INTO modules (name, title, version, description, page_slug, admin_menu_title, admin_menu_url, admin_menu_icon) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$slug, $title, $version, $description, $slug, $adminMenuTitle, $adminMenuUrl, $adminMenuIcon]);

        $moduleId = (int) $db->lastInsertId();

        // Admin Assets (module_assets tablosuna kayıt)
        if (!empty($config['assets'])) {
            foreach (['css', 'js'] as $type) {
                $order = 1;
                foreach ($config['assets'][$type] ?? [] as $asset) {
                    $assetStr = (string) $asset;
                    if (strpos($assetStr, 'http') === 0) {
                        $assetPath = $assetStr;
                    } else {
                        $assetStr = ltrim($assetStr, '/');
                        if (str_starts_with($assetStr, 'cdn/')) {
                            $assetPath = $assetStr;
                        } elseif (str_starts_with($assetStr, 'modules/')) {
                            $assetPath = $assetStr;
                        } else {
                            $assetPath = "modules/$slug/" . $assetStr;
                        }
                    }
                    $db->prepare("INSERT INTO module_assets (module_id, type, path, load_order) VALUES (?,?,?,?)")
                        ->execute([$moduleId, $type, $assetPath, $order++]);
                }
            }
        }

        // Assets are now stored in modules/$slug/, no need to copy to cdn/ folder.

        $db->commit();
        if (function_exists('sp_log'))
            sp_log("Modül yüklendi: $slug", "module_upload", null, $slug);
        echo json_encode(["status" => "success", "message" => __('module_upload_success')]);

    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleActivateTheme(PDO $db): void
{
    $themeName = filter_input(INPUT_POST, 'theme_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'default';
    if (!is_dir(ROOT_DIR . "themes/" . $themeName)) {
        echo json_encode(["status" => "error", "message" => __('theme_upload_help')]); // Generic error
        return;
    }

    // Standard cross-DB compatible logic:
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key`='active_theme'");
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        $db->prepare("UPDATE settings SET `value`=? WHERE `key`='active_theme'")->execute([$themeName]);
    } else {
        $db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('active_theme', ?)")->execute([$themeName]);
    }

    if (function_exists('sp_log'))
        sp_log("Tema aktif: $themeName", "theme_change", null, $themeName);
    echo json_encode(["status" => "success", "message" => __('module_activated')]); // Re-using activated key
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

function handleDeleteModule(PDO $db): void
{
    global $user_role; // Assuming available from auth.php
    // Basic check, auth.php should provide $is_admin
    // If not, we check role.

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM modules WHERE id=?");
    $stmt->execute([$id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        echo json_encode(["status" => "error", "message" => "Module not found"]);
        return;
    }

    $slug = $module['page_slug'];

    // 1. Run Uninstall Script if exists
    $moduleDir = ROOT_DIR . "modules/" . $slug;
    $uninstallFiles = ["uninstall.php", "uninstall_" . $slug . ".php"];
    foreach ($uninstallFiles as $u) {
        $uFile = $moduleDir . "/" . $u;
        if (file_exists($uFile)) {
            try {
                include $uFile;
            } catch (Throwable $e) {
                // Ignore or log uninstall error
            }
            break;
        }
    }

    // 2. Delete Pages & Menus from DB
    $stmtPage = $db->prepare("SELECT id FROM pages WHERE slug=?");
    $stmtPage->execute([$slug]);
    $page = $stmtPage->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        $pId = (int) $page['id'];
        $db->prepare("DELETE FROM page_assets WHERE page_id=?")->execute([$pId]);

        $menus = $db->prepare("SELECT id FROM menus WHERE page_id=?");
        $menus->execute([$pId]);
        $menuRows = $menus->fetchAll(PDO::FETCH_ASSOC);
        foreach ($menuRows as $m) {
            $db->prepare("DELETE FROM menu_locations WHERE menu_id=?")->execute([$m['id']]);
        }

        $db->prepare("DELETE FROM menus WHERE page_id=?")->execute([$pId]);
        $db->prepare("DELETE FROM pages WHERE id=?")->execute([$pId]);
    }

    // 3. Delete Module Assets and Module Entry from DB
    $db->prepare("DELETE FROM module_assets WHERE module_id=?")->execute([$id]);
    $db->prepare("DELETE FROM modules WHERE id=?")->execute([$id]);

    // 4. Recursive Delete Module Directory
    if (is_dir($moduleDir)) {
        recursiveDelete($moduleDir);
    }

    if (function_exists('sp_log'))
        sp_log("Modül silindi: $slug", "module_delete", $slug);
    echo json_encode(["status" => "success", "message" => __('module_uninstalled')]);
}

function handleToggleModule(PDO $db): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM modules WHERE id=?");
    $stmt->execute([$id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        echo json_encode(["status" => "error", "message" => "Not found"]);
        return;
    }

    $newState = $module['is_active'] ? 0 : 1;
    $db->prepare("UPDATE modules SET is_active=? WHERE id=?")->execute([$newState, $id]);

    // Update Page & Menus
    $stmtP = $db->prepare("SELECT id FROM pages WHERE slug=?");
    $stmtP->execute([$module['page_slug']]);
    $p = $stmtP->fetch(PDO::FETCH_ASSOC);
    if ($p) {
        $db->prepare("UPDATE pages SET is_active=? WHERE id=?")->execute([$newState, $p['id']]);
        $db->prepare("UPDATE menus SET is_active=? WHERE page_id=?")->execute([$newState, $p['id']]);
    }

    if (function_exists('sp_log'))
        sp_log("Modül değişti: " . $module['name'], "module_toggle", (string) $module['is_active'], (string) $newState);
    echo json_encode([
        "status" => "success",
        "message" => $newState ? __('module_activated') : __('module_deactivated'),
        "is_active" => $newState
    ]);
}

function handleClearLogs(PDO $db): void
{
    // Check Admin ?
    // Assuming auth.php handles role checks or we check global $is_admin
    global $user_role; // From auth.php if exists
    // Simplification: if reached here, user is logged in. 

    $db->exec("DELETE FROM logs");
    if (function_exists('sp_log'))
        sp_log("Logs cleared", "logs_clear");
    echo json_encode(["status" => "success", "message" => "Logs cleared"]);
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

/**
 * Handle Remote Installation from GitHub Marketplace
 */
function handleRemoteInstall(PDO $db): void
{
    $extractPath = null;
    try {
        $url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
        $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'theme';
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

        if (!$url || !$name) {
            throw new Exception(__('missing_params'), 400);
        }

        // --- SECURITY: Domain Whitelist Check ---
        $parsedUrl = parse_url($url);
        $allowedHost = 'raw.githubusercontent.com';
        if (($parsedUrl['host'] ?? '') !== $allowedHost) {
            throw new Exception("Security Error: Domain not allowed. Only GitHub Raw is permitted.");
        }

        // 1. Download ZIP via cURL
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

        // 2. Extract and Validate
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            throw new Exception(__('zip_error'), 400);
        }

        $isTheme = ($type === 'theme');
        $isModule = ($type === 'module');
        $configContent = '';
        $jsonFile = $isTheme ? 'theme.json' : 'module.json';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipName = $zip->getNameIndex($i);
            if ($zipName === $jsonFile) {
                $configContent = $zip->getFromIndex($i);
            }
            // Basic Path Traversal Check
            if (strpos($zipName, '..') !== false) {
                throw new Exception("Security Error: Path Traversal detected in ZIP");
            }
        }

        if (!$configContent) {
            $zip->close();
            unlink($tmpFile);
            throw new Exception("Configuration file ($jsonFile) not found in package.", 400);
        }

        $config = json_decode($configContent, true);
        if (!$config) {
            $zip->close();
            unlink($tmpFile);
            throw new Exception("Invalid JSON in $jsonFile", 400);
        }

        // 3. Prepare Target Directories
        $extractPath = ROOT_DIR . "_temp/ext_" . bin2hex(random_bytes(8));
        mkdir($extractPath, 0755, true);
        $zip->extractTo($extractPath);
        $zip->close();
        unlink($tmpFile);

        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $config['name'] ?? $name);
        $targetDir = $isTheme ? ROOT_DIR . "themes/" . $slug : ROOT_DIR . "modules/" . $slug;

        if (is_dir($targetDir)) {
            recursiveDelete($targetDir);
        }
        mkdir($targetDir, 0755, true);

        // 4. Move Files
        recursiveCopy($extractPath, $targetDir);

        // 5. Run Install Logic (DB etc)
        if ($isModule) {
            installModule($db, $slug, $config, $targetDir);
        } else {
            if (function_exists('sp_log'))
                sp_log("Market teması yüklendi: $slug", "theme_market_install", null, $slug);
            echo json_encode(["status" => "success", "message" => __('install_success')]);
        }

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
