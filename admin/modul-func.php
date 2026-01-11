<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        echo json_encode(["status" => "error", "message" => "CSRF verification failed", "message_key" => "errdata"]);
        exit;
    }
}

/* ---------------- Ensure tables exist ---------------- */
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'sqlite') {
    $db->exec("
    CREATE TABLE IF NOT EXISTS modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        version TEXT DEFAULT '1.0',
        description TEXT,
        page_slug TEXT,
        is_active INTEGER DEFAULT 1,
        installed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
    ");

    $db->exec("
    CREATE TABLE IF NOT EXISTS module_assets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        type TEXT NOT NULL,   -- js | css | json
        path TEXT NOT NULL,
        load_order INTEGER DEFAULT 0,
        FOREIGN KEY (module_id) REFERENCES modules(id)
    )
    ");

    $db->exec("
    CREATE TABLE IF NOT EXISTS theme_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        theme_name TEXT NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT,
        UNIQUE(theme_name, setting_key)
    )
    ");
} else {
    // MySQL
    $db->exec("
    CREATE TABLE IF NOT EXISTS modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        title LONGTEXT NOT NULL,
        version VARCHAR(50) DEFAULT '1.0',
        description LONGTEXT,
        page_slug LONGTEXT,
        is_active INT DEFAULT 1,
        installed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
    CREATE TABLE IF NOT EXISTS module_assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module_id INT NOT NULL,
        type VARCHAR(10) NOT NULL,
        path LONGTEXT NOT NULL,
        load_order INT DEFAULT 0,
        FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
    CREATE TABLE IF NOT EXISTS theme_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        theme_name VARCHAR(255) NOT NULL,
        setting_key VARCHAR(255) NOT NULL,
        setting_value LONGTEXT,
        UNIQUE(theme_name, setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

if ($action === 'upload') {
    $extractPath = null;
    try {
        if (!isset($_FILES['module_zip'])) {
            throw new Exception("Zip dosyası bulunamadı", 400);
        }

        $zipFile = $_FILES['module_zip']['tmp_name'];
        $zip = new ZipArchive();

        // Dizin Güvenliği: ZipArchive::open ile başarıyla doğrulandıktan sonra klasörü oluştur
        if ($zip->open($zipFile) !== true) {
            throw new Exception("Zip dosyası açılamadı veya geçersiz.", 400);
        }

        /* ---------------- PRE-EXTRACTION VALIDATION ---------------- */
        $configContent = '';
        $isTheme = false;
        $isModule = false;

        // 1. Config Dosyasını Ara (ZIP açılmadan)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === 'theme.json') {
                $isTheme = true;
                $configContent = $zip->getFromIndex($i);
            } elseif ($name === 'module.json') {
                $isModule = true;
                $configContent = $zip->getFromIndex($i);
            }

            // 2. Güvenlik Kontrolü: Dosya yolları (Traversal & Core Overwrite)
            $fileName = basename($name);
            $forbidden = ['index.php', 'settings.php', 'db.php', 'auth.php', '.htaccess', 'manifest.json', 'service-worker.js', 'page.php'];

            // Directory Traversal check
            if (strpos($name, '..') !== false || strpos($name, '/') === 0 || strpos($name, '\\') === 0) {
                throw new Exception("Güvenlik Hatası: Geçersiz dosya yolu tespit edildi (Traversal).");
            }

            // Core file overwrite prevention (at zip root)
            if (in_array(strtolower($fileName), $forbidden) && !strpos($name, '/') && !strpos($name, '\\')) {
                // Not: Temalar için index.php istisna olabilir ama genel güvenlik için kök dizinde bu isimleri yasaklıyoruz.
                // Paket yapısında bunlar bir alt klasörde veya isimleri farklı olmalıdır.
                throw new Exception("Güvenlik Hatası: Sistem çekirdek dosyası ismiyle ('$fileName') çakışan dosya tespit edildi.");
            }
        }

        if (!$isTheme && !$isModule) {
            throw new Exception("Geçersiz Paket: module.json veya theme.json bulunamadı.", 400);
        }

        $config = json_decode($configContent, true);
        if (!$config) {
            throw new Exception("Yapılandırma Hatası: JSON dosyası okunamadı veya hatalı.", 400);
        }

        // 3. PHP Sürüm Kontrolü
        if (!empty($config['php_version'])) {
            if (version_compare(PHP_VERSION, $config['php_version'], '<')) {
                throw new Exception("PHP Sürüm Hatası: Bu paket en az v" . $config['php_version'] . " gerektiriyor. Sistem Sürümü: " . PHP_VERSION);
            }
        }

        // 4. Bağımlılık (Dependencies) Kontrolü
        if ($isModule && !empty($config['dependencies']) && is_array($config['dependencies'])) {
            foreach ($config['dependencies'] as $dep) {
                $stmt = $db->prepare("SELECT id FROM modules WHERE name = ? AND is_active = 1");
                $stmt->execute([$dep]);
                if (!$stmt->fetch()) {
                    throw new Exception("Bağımlılık Hatası: Bu modülün çalışması için '$dep' modülünün yüklü ve aktif olması gerekir.");
                }
            }
        }

        // 5. Geçici dizin oluştur ve çıkart
        $extractPath = ROOT_DIR . "modules/tmp_" . bin2hex(random_bytes(8));
        if (!is_dir($extractPath)) {
            if (!mkdir($extractPath, 0755, true)) {
                $zip->close();
                throw new Exception("Geçici dizin oluşturulamadı.", 500);
            }
        }

        $zip->extractTo($extractPath);
        $zip->close();
        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $config['name'] ?? '');

        if (!$slug) {
            throw new Exception("Paket ismi (name) belirtilmemiş veya geçersiz.", 400);
        }

        /* ---------------- SMART MEDIA DISTRIBUTION ---------------- */
        if (!empty($config['assets']['images']) && is_array($config['assets']['images'])) {
            $imageTargetBase = $isTheme
                ? ROOT_DIR . "themes/$slug/images/"
                : ROOT_DIR . "cdn/images/$slug/";

            foreach ($config['assets']['images'] as $imgRelPath) {
                // Determine source and handle cross-platform separators
                $src = $extractPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imgRelPath);

                if (file_exists($src)) {
                    // Hiyerarşik Kopyalama & mkdir -p
                    // Remove leading 'images/' if exists to prevent .../images/images/...
                    $cleanPath = preg_replace('/^images[\/\\\\]/i', '', $imgRelPath);
                    $dst = $imageTargetBase . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cleanPath);
                    $dstDir = dirname($dst);

                    if (!is_dir($dstDir)) {
                        mkdir($dstDir, 0755, true);
                    }
                    copy($src, $dst);
                }
            }
        }

        if ($isTheme) {
            /* ============================
               THEME UPLOAD LOGIC
               ============================ */
            $themeDir = ROOT_DIR . "themes/" . $slug;
            if (!is_dir($themeDir)) {
                mkdir($themeDir, 0755, true);
            }

            // Move all files (Hierarchical Copying)
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $dest = $themeDir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                if ($item->isDir()) {
                    if (!is_dir($dest))
                        mkdir($dest, 0755, true);
                } else {
                    copy($item->getRealPath(), $dest);
                }
            }

            sp_log("Tema başarıyla yüklendi: $slug", "theme_upload", $slug);
            echo json_encode(["status" => "success", "message" => "Tema başarıyla yüklendi: $slug", "message_key" => "theme_upload_success"]);
        } else {
            /* ============================
               MODULE UPLOAD LOGIC
               ============================ */
            $title = $config['title'] ?? $slug;
            $description = $config['description'] ?? '';
            $icon = $config['icon'] ?? '';
            $version = $config['version'] ?? '1.0';
            $menu_title = $config['menu_title'] ?? $title;
            $menu_icon = $config['menu_icon'] ?? $icon;
            $locations = $config['locations'] ?? ['navbar'];

            /* ---------------- Copy page file ---------------- */
            if (isset($config['page'])) {
                $pageSrc = $extractPath . DIRECTORY_SEPARATOR . $config['page'];
                if (file_exists($pageSrc)) {
                    copy($pageSrc, SAYFALAR_DIR . $config['page']);
                }
            }

            /* ---------------- Copy uninstall script ---------------- */
            $uninstallFileNames = ["uninstall.php", "uninstall_" . $slug . ".php"];
            foreach ($uninstallFileNames as $uName) {
                $uSrc = $extractPath . DIRECTORY_SEPARATOR . $uName;
                if (file_exists($uSrc)) {
                    copy($uSrc, SAYFALAR_DIR . "uninstall_" . $slug . ".php");
                    break;
                }
            }

            // --- DATABASE MIGRATIONS (Pre-DB Commit) ---
            // 1. Execute schema.sql if exists
            $schemaFile = $extractPath . DIRECTORY_SEPARATOR . 'schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                if (!empty(trim($sql))) {
                    try {
                        $db->exec($sql);
                    } catch (Exception $e) {
                        throw new Exception("SQL Migration Hatası: " . $e->getMessage());
                    }
                }
            }

            // 2. Execute migration.php if exists
            $migrationFile = $extractPath . DIRECTORY_SEPARATOR . 'migration.php';
            if (file_exists($migrationFile)) {
                try {
                    // Let the migration file use $db global variable
                    include $migrationFile;
                } catch (Exception $e) {
                    throw new Exception("PHP Migration Hatası: " . $e->getMessage());
                }
            }

            // Veritabanı işlemleri (Prepared Statements)
            $db->beginTransaction();
            try {
                $sort_order = (int) $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM pages")->fetchColumn();

                $stmt = $db->prepare("INSERT INTO pages (slug, title, description, icon, is_active, sort_order) VALUES (?,?,?,?,1,?)");
                $stmt->execute([$slug, $title, $description, $icon, $sort_order]);
                $page_id = (int) $db->lastInsertId();

                if (!empty($config['assets']['css'])) {
                    $order = 1;
                    foreach ($config['assets']['css'] as $css) {
                        $db->prepare("INSERT INTO page_assets (page_id, type, path, load_order) VALUES (?,?,?,?)")->execute([$page_id, 'css', $css, $order++]);
                    }
                }
                if (!empty($config['assets']['js'])) {
                    $order = 1;
                    foreach ($config['assets']['js'] as $js) {
                        $db->prepare("INSERT INTO page_assets (page_id, type, path, load_order) VALUES (?,?,?,?)")->execute([$page_id, 'js', $js, $order++]);
                    }
                }

                $stmt = $db->prepare("INSERT INTO menus (page_id, title, icon, sort_order, is_active) VALUES (?,?,?,?,1) ");
                $stmt->execute([$page_id, $menu_title, $menu_icon, $sort_order]);
                $menu_id = (int) $db->lastInsertId();

                foreach ($locations as $loc) {
                    $loc = preg_replace('/[^a-z0-9_-]/', '', $loc);
                    $db->prepare("INSERT INTO menu_locations (menu_id, location) VALUES (?,?)")->execute([$menu_id, $loc]);
                }

                $stmt = $db->prepare("INSERT INTO modules (name, title, version, description, page_slug) VALUES (?,?,?,?,?)");
                $stmt->execute([$slug, $title, $version, $description, $slug]);
                $module_id = (int) $db->lastInsertId();

                /* ---------------- Copy CDN assets + module_assets ---------------- */
                foreach (['css', 'js', 'json'] as $type) {
                    if (!empty($config['cdn'][$type])) {
                        $order = 1;
                        foreach ($config['cdn'][$type] as $file) {
                            $src = $extractPath . DIRECTORY_SEPARATOR . "cdn" . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file;
                            $dst = ROOT_DIR . "cdn/$type/$file";
                            if (file_exists($src)) {
                                $dstDir = dirname($dst);
                                if (!is_dir($dstDir))
                                    mkdir($dstDir, 0755, true);
                                copy($src, $dst);
                                $db->prepare("INSERT INTO module_assets (module_id, type, path, load_order) VALUES (?,?,?,?)")->execute([$module_id, $type, "cdn/$type/$file", $order++]);
                            }
                        }
                    }
                }
                $db->commit();
                sp_log("Modül başarıyla yüklendi: $slug (v$version)", "module_upload", $slug);
                echo json_encode(["status" => "success", "message" => "Modül başarıyla yüklendi", "message_key" => "module_upload_success"]);
            } catch (Exception $dbEx) {
                $db->rollBack();
                throw $dbEx;
            }
        }
    } catch (Exception $e) {
        if (ob_get_length())
            ob_clean();
        sp_log("Paket yükleme hatası: " . $e->getMessage(), "system_error", null, ["action" => "upload"]);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        // Hata olsa dahi tüm geçici dosyaları temizle (RecursiveDirectoryIterator)
        if ($extractPath && is_dir($extractPath)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
            rmdir($extractPath);
        }
    }
    exit;
}

/* ---------------- ACTIVATE THEME ---------------- */ elseif ($action === 'activate_theme') {
    $themeName = $_POST['theme_name'] ?? 'default';

    // Validate if theme exists
    if (!is_dir(ROOT_DIR . "themes/" . $themeName) || !file_exists(ROOT_DIR . "themes/" . $themeName . "/theme.json")) {
        echo json_encode(["status" => "error", "message" => "Tema bulunamadı: $themeName"]);
        exit;
    }

    try {
        // Check if active_theme key exists in settings
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key` = 'active_theme'");
        $stmt->execute();
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $db->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'active_theme'")->execute([$themeName]);
        } else {
            $db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('active_theme', ?)")->execute([$themeName]);
        }

        sp_log("Tema değiştirildi: $themeName", "theme_change");
        echo json_encode(["status" => "success", "message" => "Tema etkinleştirildi: $themeName"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
    }
    exit;
}

/* ---------------- DELETE MODULE ---------------- */ elseif ($action === 'delete') {
    if (!$is_admin) {
        echo json_encode(["status" => "error", "message" => "Yetkisiz işlem. Silme yetkiniz yok.", "message_key" => "errdata"]);
        exit;
    }

    $id = (int) $_POST['id'];

    // Modülü bul
    $stmt = $db->prepare("SELECT * FROM modules WHERE id=?");
    $stmt->execute([$id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        echo json_encode(["status" => "error", "message" => "Modül bulunamadı", "message_key" => "module_not_found"]);
        exit;
    }

    $slug = $module['page_slug'];

    /* ---------------- Find page ID ---------------- */
    $stmt = $db->prepare("SELECT id FROM pages WHERE slug=?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        $page_id = $page['id'];

        // Delete page assets
        $db->prepare("DELETE FROM page_assets WHERE page_id=?")->execute([$page_id]);

        // Delete menu locations
        $menus = $db->prepare("SELECT id FROM menus WHERE page_id=?");
        $menus->execute([$page_id]);
        foreach ($menus->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $db->prepare("DELETE FROM menu_locations WHERE menu_id=?")->execute([$m['id']]);
        }

        // Delete menus
        $db->prepare("DELETE FROM menus WHERE page_id=?")->execute([$page_id]);

        // Delete page
        $db->prepare("DELETE FROM pages WHERE id=?")->execute([$page_id]);
    }

    // Release any locks by closing previous statements
    if (isset($stmt))
        $stmt->closeCursor();
    if (isset($menus))
        $menus->closeCursor();
    unset($stmt, $menus, $module, $page);

    /* ---------------- Physical file cleanup ---------------- */
    // 1. Fetch CDN assets first
    $stmtAssets = $db->prepare("SELECT path FROM module_assets WHERE module_id=?");
    $stmtAssets->execute([$id]);
    $assetsToDelete = $stmtAssets->fetchAll(PDO::FETCH_ASSOC);
    $stmtAssets->closeCursor();

    // 2. Delete data from DB tables (Except the module itself, to keep slug ref for uninstall)
    // Already deleted pages, menus etc above.

    // 3. Delete CDN assets files
    foreach ($assetsToDelete as $asset) {
        $target = ROOT_DIR . $asset['path'];
        if (is_file($target)) {
            @unlink($target);
        }
    }

    // 4. Delete module record and assets record
    $db->prepare("DELETE FROM module_assets WHERE module_id=?")->execute([$id]);
    $db->prepare("DELETE FROM modules WHERE id=?")->execute([$id]);

    // 5. Delete page file
    $pageFile = SAYFALAR_DIR . $slug . ".php";
    if (file_exists($pageFile)) {
        @unlink($pageFile);
    }

    // --- CRITICAL LOCK BREAKER: REFRESH CONNECTION ---
    // Unset all objects and close connection to force SQLite to release ALL locks
    unset($stmt, $stmtAssets, $stmtAssets, $menus, $module, $page, $assetsToDelete);
    $db = null;

    // Re-connect
    require ROOT_DIR . 'admin/db.php';

    // --- UNINSTALL LOGIC (LAST STEP) ---
    // We run this at the very end when all other DB locks are released.
    $uninstallFile = SAYFALAR_DIR . "uninstall_" . $slug . ".php";
    if (file_exists($uninstallFile)) {
        try {
            global $db;
            include $uninstallFile;
            @unlink($uninstallFile);
            sp_log("Temizlik betiği başarıyla çalıştırıldı: $slug", "module_uninstall_ok", $slug);
        } catch (Exception $e) {
            sp_log("Uninstallation Script Hatası ($slug): " . $e->getMessage(), "system_error");
        }
    }

    // Delete tmp_xxxxx folders
    $modulesTmp = ROOT_DIR . "modules/";
    foreach (glob($modulesTmp . "tmp_*") as $tmpDir) {
        if (is_dir($tmpDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
            }
            @rmdir($tmpDir);
        }
    }

    sp_log("Modül silindi: $slug (ID: $id)", "module_delete", $slug);
    echo json_encode(["status" => "success", "message" => "Modül ve dosyaları tamamen silindi", "message_key" => "module_uninstalled"]);
    exit;
}

/* ---------------- TOGGLE MODULE (ENABLE / DISABLE) ---------------- */ elseif ($action === 'toggle') {

    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $db->prepare("SELECT * FROM modules WHERE id=?");
    $stmt->execute([$id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        echo json_encode(["status" => "error", "message" => "Modül bulunamadı"]);
        exit;
    }

    $newState = $module['is_active'] ? 0 : 1;

    try {
        // Update modules table
        $db->prepare("UPDATE modules SET is_active=? WHERE id=?")->execute([$newState, $id]);

        // Update related page active flag (if exists)
        $slug = $module['page_slug'];
        $stmt = $db->prepare("SELECT id FROM pages WHERE slug=?");
        $stmt->execute([$slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($page) {
            $page_id = $page['id'];
            $db->prepare("UPDATE pages SET is_active=? WHERE id=?")->execute([$newState, $page_id]);

            // Update menus for that page
            $db->prepare("UPDATE menus SET is_active=? WHERE page_id=?")->execute([$newState, $page_id]);
        }

        sp_log("Modül durumu değiştirildi: $slug (" . ($newState ? "Aktif" : "Pasif") . ")", "module_toggle", $slug);
        echo json_encode(["status" => "success", "message" => ($newState ? "Modül etkinleştirildi" : "Modül devre dışı bırakıldı"), "message_key" => ($newState ? "module_activated" : "module_deactivated"), "is_active" => $newState]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "DB hata: " . $e->getMessage(), "message_key" => "errdata"]);
    }

    exit;
}

/* ---------------- DUPLICATE THEME ---------------- */ elseif ($action === 'duplicate_theme') {
    $source = $_POST['source'] ?? 'default';
    $newName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['new_name'] ?? '');
    $newTitle = strip_tags($_POST['new_title'] ?? $newName);

    if (!$newName) {
        echo json_encode(["status" => "error", "message" => "Geçersiz tema ismi."]);
        exit;
    }

    $sourceDir = ROOT_DIR . "themes/" . $source;
    $targetDir = ROOT_DIR . "themes/" . $newName;

    if (!is_dir($sourceDir)) {
        echo json_encode(["status" => "error", "message" => "Kaynak tema bulunamadı."]);
        exit;
    }

    if (is_dir($targetDir)) {
        echo json_encode(["status" => "error", "message" => "Bu isimde bir tema zaten mevcut."]);
        exit;
    }

    try {
        mkdir($targetDir, 0755, true);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest = $targetDir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($dest))
                    mkdir($dest, 0755, true);
            } else {
                copy($item->getRealPath(), $dest);
            }
        }

        // Update theme.json in the new theme
        $jsonPath = $targetDir . "/theme.json";
        if (file_exists($jsonPath)) {
            $config = json_decode(file_get_contents($jsonPath), true);
            $config['name'] = $newName;
            $config['title'] = $newTitle;
            file_put_contents($jsonPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        sp_log("Tema kopyalandı: $source -> $newName", "theme_duplicate", $newName);
        echo json_encode(["status" => "success", "message" => "Tema başarıyla kopyalandı: $newName"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Kopyalama hatası: " . $e->getMessage()]);
    }
    exit;
}

/* ---------------- DELETE THEME ---------------- */ elseif ($action === 'delete_theme') {
    $themeName = $_POST['theme_name'] ?? '';

    if ($themeName === 'default') {
        echo json_encode(["status" => "error", "message" => "Varsayılan tema silinemez."]);
        exit;
    }

    $themeDir = ROOT_DIR . "themes/" . $themeName;

    if (!is_dir($themeDir)) {
        echo json_encode(["status" => "error", "message" => "Tema bulunamadı."]);
        exit;
    }

    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($themeDir);

        // Also clean settings from DB
        $db->prepare("DELETE FROM theme_settings WHERE theme_name = ?")->execute([$themeName]);

        sp_log("Tema silindi: $themeName", "theme_delete", $themeName);
        echo json_encode(["status" => "success", "message" => "Tema başarıyla silindi."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Silme hatası: " . $e->getMessage()]);
    }
    exit;
}

/* ---------------- CLEAR ALL LOGS ---------------- */ elseif ($action === 'clear_logs') {
    if (!$is_admin) {
        echo json_encode(["status" => "error", "message" => "Yetkisiz işlem."]);
        exit;
    }

    try {
        $db->exec("DELETE FROM logs");
        sp_log("Sistem denetim logları manuel olarak temizlendi.", "logs_clear");
        echo json_encode(["status" => "success", "message" => "Tüm log kayıtları başarıyla silindi."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Log silme hatası: " . $e->getMessage()]);
    }
    exit;
}
