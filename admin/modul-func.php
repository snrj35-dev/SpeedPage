<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

$action = $_POST['action'] ?? '';

/* ---------------- Ensure tables exist ---------------- */
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

if ($action === 'upload') {

    if (!isset($_FILES['module_zip'])) {
        echo json_encode(["status"=>"error","message"=>"Zip dosyası bulunamadı"]);
        exit;
    }

    $zipFile = $_FILES['module_zip']['tmp_name'];
    $extractPath = ROOT_DIR . "modules/tmp_" . time();

    if (!is_dir($extractPath)) mkdir($extractPath, 0755, true);

    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo($extractPath);
        $zip->close();
    } else {
        echo json_encode(["status"=>"error","message"=>"Zip açılamadı"]);
        exit;
    }

    /* ---------------- Read module.json ---------------- */
    $configFile = $extractPath . "/module.json";
    if (!file_exists($configFile)) {
        echo json_encode(["status"=>"error","message"=>"module.json bulunamadı"]);
        exit;
    }

    $config = json_decode(file_get_contents($configFile), true);

    $slug        = $config['name'];
    $title       = $config['title'];
    $description = $config['description'] ?? '';
    $icon        = $config['icon'] ?? '';
    $version     = $config['version'] ?? '1.0';

    $menu_title  = $config['menu_title'] ?? $title;
    $menu_icon   = $config['menu_icon'] ?? $icon;
    $locations   = $config['locations'] ?? ['navbar'];

    /* ---------------- Copy page file ---------------- */
    if (isset($config['page'])) {
        copy($extractPath . "/" . $config['page'], SAYFALAR_DIR . $config['page']);
    }

    try {
        /* ---------------- Insert into pages ---------------- */
        $sort_order = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM pages")->fetchColumn();

        $stmt = $db->prepare("
            INSERT INTO pages (slug, title, description, icon, is_active, sort_order)
            VALUES (?,?,?,?,1,?)
        ");
        $stmt->execute([$slug, $title, $description, $icon, $sort_order]);

        $page_id = (int)$db->lastInsertId();

        /* ---------------- Insert page assets ---------------- */
        $order = 1;
        if (!empty($config['assets']['css'])) {
            foreach ($config['assets']['css'] as $css) {
                $db->prepare("
                    INSERT INTO page_assets (page_id, type, path, load_order)
                    VALUES (?,?,?,?)
                ")->execute([$page_id, 'css', $css, $order++]);
            }
        }
        if (!empty($config['assets']['js'])) {
            foreach ($config['assets']['js'] as $js) {
                $db->prepare("
                    INSERT INTO page_assets (page_id, type, path, load_order)
                    VALUES (?,?,?,?)
                ")->execute([$page_id, 'js', $js, $order++]);
            }
        }

        /* ---------------- Insert menu ---------------- */
        $stmt = $db->prepare("
            INSERT INTO menus (page_id, title, icon, sort_order, is_active)
            VALUES (?,?,?,?,1)
        ");
        $stmt->execute([$page_id, $menu_title, $menu_icon, $sort_order]);

        $menu_id = (int)$db->lastInsertId();

        /* ---------------- Insert menu locations ---------------- */
        foreach ($locations as $loc) {
            $loc = preg_replace('/[^a-z0-9_-]/', '', $loc);
            $db->prepare("
                INSERT INTO menu_locations (menu_id, location)
                VALUES (?,?)
            ")->execute([$menu_id, $loc]);
        }

        /* ---------------- Insert module ---------------- */
        $stmt = $db->prepare("
            INSERT INTO modules (name, title, version, description, page_slug)
            VALUES (?,?,?,?,?)
        ");
        $stmt->execute([$slug, $title, $version, $description, $slug]);

        $module_id = (int)$db->lastInsertId();

        /* ---------------- Copy CDN assets + module_assets ---------------- */
        foreach (['css','js','json'] as $type) {
            if (!empty($config['cdn'][$type])) {
                $order = 1;
                foreach ($config['cdn'][$type] as $file) {
                    $src = "$extractPath/cdn/$type/$file";
                    $dst = ROOT_DIR . "cdn/$type/$file";
                    if (file_exists($src)) {
                        copy($src, $dst);
                        $db->prepare("
                            INSERT INTO module_assets (module_id, type, path, load_order)
                            VALUES (?,?,?,?)
                        ")->execute([$module_id, $type, "cdn/$type/$file", $order++]);
                    }
                }
            }
        }

        echo json_encode(["status"=>"success","message"=>"Modül başarıyla yüklendi"]);

    } catch (Exception $e) {
        echo json_encode(["status"=>"error","message"=>"DB hata: " . $e->getMessage()]);
    }

    exit;
}

/* ---------------- DELETE MODULE ---------------- */
elseif ($action === 'delete') {

    $id = (int)$_POST['id'];

    // Modülü bul
    $stmt = $db->prepare("SELECT * FROM modules WHERE id=?");
    $stmt->execute([$id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        echo json_encode(["status"=>"error","message"=>"Modül bulunamadı"]);
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

    /* ---------------- Physical file cleanup ---------------- */
    // Delete page file
    $pageFile = SAYFALAR_DIR . $slug . ".php";
    if (file_exists($pageFile)) {
        @unlink($pageFile);
    }

    // Delete CDN assets via module_assets
    $stmt = $db->prepare("SELECT type, path FROM module_assets WHERE module_id=?");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $asset) {
        $target = ROOT_DIR . $asset['path'];
        if (is_file($target)) {
            @unlink($target);
        }
    }

    // Delete module_assets entries
    $db->prepare("DELETE FROM module_assets WHERE module_id=?")->execute([$id]);

    // Delete module record
    $db->prepare("DELETE FROM modules WHERE id=?")->execute([$id]);

    // Delete tmp_xxxxx folders in modules dir
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

    echo json_encode(["status"=>"success","message"=>"Modül ve dosyaları tamamen silindi"]);
    exit;
}

/* ---------------- TOGGLE MODULE (ENABLE / DISABLE) ---------------- */
elseif ($action === 'toggle') {

    $id = (int)($_POST['id'] ?? 0);

    $stmt = $db->prepare("SELECT * FROM modules WHERE id=?");
    $stmt->execute([$id]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        echo json_encode(["status"=>"error","message"=>"Modül bulunamadı"]);
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

        echo json_encode(["status"=>"success","message"=>($newState ? "Modül etkinleştirildi" : "Modül devre dışı bırakıldı"), "is_active"=>$newState]);
    } catch (Exception $e) {
        echo json_encode(["status"=>"error","message"=>"DB hata: " . $e->getMessage()]);
    }

    exit;
}
