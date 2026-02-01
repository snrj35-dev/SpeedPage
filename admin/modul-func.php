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
} elseif (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    // Only block direct GET access, not inclusion
    exit;
}

/* ======================================================
   ACTION HANDLERS
   ====================================================== */

if ($action === 'upload') {
    handleUpload($db);
} elseif ($action === 'delete') {
    handleDeleteModule($db);
} elseif ($action === 'toggle') {
    handleToggleModule($db);
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
            $forbidden = [
                'index.php', 'settings.php', 'db.php', 'auth.php', '.htaccess', 
                'manifest.json', 'config.php', 'db_switch.php', 'AiCore.php'
            ];
            if (strpos($name, '..') !== false || strpos($name, '/') === 0 || strpos($name, '\\') === 0) {
                throw new Exception("Security Error: Path Traversal");
            }
            // Prevent core file overwrite in root of ZIP (which would be extracted to modules/tmp_...)
            // But also prevent these names in the root of the ZIP just in case of logic flaws elsewhere.
            if (in_array(strtolower($fileName), $forbidden) && !strpos($name, '/') && !strpos($name, '\\')) {
                throw new Exception("Security Error: Core file name match ($fileName) in ZIP root.");
            }
        }

        if (!$isTheme && !$isModule) {
            throw new Exception(__('theme_json_error'), 400); // Or module JSON error
        }

        if ($isTheme) {
            throw new Exception("Please use Theme Upload for themes.");
        }

        $config = json_decode($configContent, true);
        if (!$config) {
            throw new Exception(__('theme_json_error'), 400);
        }

        // 1. Core Version Check
        if (!empty($config['min_core_version'])) {
            if (version_compare(APP_VERSION, $config['min_core_version'], '<')) {
                throw new Exception("Requires SpeedPage v" . $config['min_core_version'] . ". Current: " . APP_VERSION);
            }
        }

        // 2. PHP Version Check
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
            $targetBase = ROOT_DIR . "cdn/images/$slug/";
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

        // Create Target Directory (Modules)
        $targetDir = ROOT_DIR . "modules/" . $slug;
        if (is_dir($targetDir)) {
            recursiveDelete($targetDir);
        }
        mkdir($targetDir, 0755, true);

        // Move extracted content to target dir
        recursiveCopy($extractPath, $targetDir);

        // Module Logic
        installModule($db, $slug, $config, $targetDir);

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
    // db_setup handling
    $dbSetup = $config['db_setup'] ?? [];
    $installScript = $dbSetup['install'] ?? null;
    $uninstallScript = $dbSetup['uninstall'] ?? null;

    if ($installScript && file_exists($extractPath . '/' . $installScript)) {
        try {
            include_once $extractPath . '/' . $installScript;
        } catch (Throwable $e) {
            if (function_exists('sp_log')) sp_log("Module Install Script Error ($slug): " . $e->getMessage(), "system_error");
        }
    }
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
        // --- 1. CLEANUP PREVIOUS DATA (If exists) ---
        // Check for existing module record
        $stmtCheck = $db->prepare("SELECT id FROM modules WHERE name = ?");
        $stmtCheck->execute([$slug]);
        $existingModId = $stmtCheck->fetchColumn();

        if ($existingModId) {
            // Delete Assets
            $db->prepare("DELETE FROM module_assets WHERE module_id = ?")->execute([$existingModId]);
            // Delete Module record
            $db->prepare("DELETE FROM modules WHERE id = ?")->execute([$existingModId]);
        }

        // Check for existing page/menus for this slug
        $stmtPageCheck = $db->prepare("SELECT id FROM pages WHERE slug = ?");
        $stmtPageCheck->execute([$slug]);
        $existingPageId = $stmtPageCheck->fetchColumn();
        if ($existingPageId) {
            $db->prepare("DELETE FROM page_assets WHERE page_id = ?")->execute([$existingPageId]);
            // Find related menus
            $stmtMenu = $db->prepare("SELECT id FROM menus WHERE page_id = ?");
            $stmtMenu->execute([$existingPageId]);
            $menusToDelete = $stmtMenu->fetchAll(PDO::FETCH_COLUMN);
            foreach ($menusToDelete as $mId) {
                $db->prepare("DELETE FROM menu_locations WHERE menu_id = ?")->execute([$mId]);
            }
            $db->prepare("DELETE FROM menus WHERE page_id = ?")->execute([$existingPageId]);
            $db->prepare("DELETE FROM pages WHERE id = ?")->execute([$existingPageId]);
        }

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
        $permissionsJson = isset($config['permissions']) ? json_encode($config['permissions']) : null;
        $db->prepare("INSERT INTO modules (name, title, version, description, page_slug, admin_menu_title, admin_menu_url, admin_menu_icon, permissions) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$slug, $title, $version, $description, $slug, $adminMenuTitle, $adminMenuUrl, $adminMenuIcon, $permissionsJson]);

        $moduleId = (int) $db->lastInsertId();

        // Helper to insert asset
        $insertAsset = function($type, $assets, $location) use ($db, $moduleId, $slug) {
             $order = 1;
             foreach ($assets as $asset) {
                if (empty($asset)) continue;
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
                $db->prepare("INSERT INTO module_assets (module_id, type, path, load_order, location) VALUES (?,?,?,?,?)")
                    ->execute([$moduleId, $type, $assetPath, $order++, $location]);
             }
        };

        // 1. Legacy Admin Assets (from root 'assets') -> location='admin'
        if (!empty($config['assets'])) {
             if (isset($config['assets']['css'])) $insertAsset('css', $config['assets']['css'], 'admin');
             if (isset($config['assets']['js']))  $insertAsset('js', $config['assets']['js'], 'admin');
        }

        // 2. New Admin Assets (admin_menu -> admin_assets) -> location='admin'
        if (!empty($adminMenu['admin_assets'])) {
             if (isset($adminMenu['admin_assets']['css'])) $insertAsset('css', $adminMenu['admin_assets']['css'], 'admin');
             if (isset($adminMenu['admin_assets']['js']))  $insertAsset('js', $adminMenu['admin_assets']['js'], 'admin');
        }

        // 3. Page Assets (pageassets) -> location='frontend'
        if (!empty($config['pageassets'])) {
             if (isset($config['pageassets']['css'])) $insertAsset('css', $config['pageassets']['css'], 'frontend');
             if (isset($config['pageassets']['js']))  $insertAsset('js', $config['pageassets']['js'], 'frontend');
        }

        // 4. Global Hook Assets (hooksassets) -> location='global'
        if (!empty($config['hooksassets'])) {
             if (isset($config['hooksassets']['css'])) $insertAsset('css', $config['hooksassets']['css'], 'global');
             if (isset($config['hooksassets']['js']))  $insertAsset('js', $config['hooksassets']['js'], 'global');
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
    
    // Read module.json to find uninstall script
    $moduleJsonFile = $moduleDir . "/module.json";
    if (file_exists($moduleJsonFile)) {
        $moduleConfig = json_decode(file_get_contents($moduleJsonFile), true);
        $uninstallScript = $moduleConfig['db_setup']['uninstall'] ?? null;
        if ($uninstallScript && file_exists($moduleDir . "/" . $uninstallScript)) {
            try {
                include_once $moduleDir . "/" . $uninstallScript;
            } catch (Throwable $e) {
                if (function_exists('sp_log')) sp_log("Module Uninstall Script Error ($slug): " . $e->getMessage(), "system_error");
            }
        }
    }

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

        if ($type === 'theme') {
            throw new Exception("Please use Theme Install for themes.");
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

        $isModule = ($type === 'module');
        $configContent = '';
        $jsonFile = 'module.json';

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
        $targetDir = ROOT_DIR . "modules/" . $slug;

        if (is_dir($targetDir)) {
            recursiveDelete($targetDir);
        }
        mkdir($targetDir, 0755, true);

        // 4. Move Files
        recursiveCopy($extractPath, $targetDir);

        // 5. Run Install Logic (DB etc)
        installModule($db, $slug, $config, $targetDir);

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
