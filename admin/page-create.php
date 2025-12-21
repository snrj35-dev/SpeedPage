<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

$mesaj = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Clean slug
    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($_POST['slug']));
    $content = $_POST['icerik'];

    // Meta fields
    $title       = trim($_POST['title'] ?? '') ?: $slug;
    $description = trim($_POST['description'] ?? '') ?: null;
    $icon        = trim($_POST['icon'] ?? '') ?: null;
    $is_active   = isset($_POST['aktif']) ? ((int)$_POST['aktif'] ? 1 : 0) : 1;

    // CSS / JS arrays
    $css = array_filter(array_map('trim', explode(',', $_POST['css'] ?? '')));
    $js  = array_filter(array_map('trim', explode(',', $_POST['js'] ?? '')));

    // File path
    $file = SAYFALAR_DIR . "$slug.php";

    if (file_exists($file)) {
        echo "❌ Bu sayfa zaten var";
        exit;
    }

    // Save HTML content only
    file_put_contents($file, $content);

    try {
        // Insert into pages table
        $sort_order = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM pages")->fetchColumn();

        $stmt = $db->prepare("
            INSERT INTO pages (slug, title, description, icon, is_active, sort_order)
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->execute([$slug, $title, $description, $icon, $is_active, $sort_order]);

        $page_id = (int)$db->lastInsertId();

        // Insert CSS assets
        $order = 1;
        foreach ($css as $c) {
            $db->prepare("
                INSERT INTO page_assets (page_id, type, path, load_order)
                VALUES (?,?,?,?)
            ")->execute([$page_id, 'css', $c, $order++]);
        }

        // Insert JS assets
        foreach ($js as $j) {
            $db->prepare("
                INSERT INTO page_assets (page_id, type, path, load_order)
                VALUES (?,?,?,?)
            ")->execute([$page_id, 'js', $j, $order++]);
        }

        // Add to menu if selected
        if (!empty($_POST['add_to_menu'])) {

            $menu_title = trim($_POST['menu_title'] ?? '') ?: $title;
            $menu_icon  = trim($_POST['menu_icon'] ?? '') ?: $icon;
            $menu_order = (int)($_POST['menu_order'] ?? $sort_order);

            // Create menu entry
            $db->prepare("
                INSERT INTO menus (page_id, title, icon, sort_order, is_active)
                VALUES (?,?,?,?,1)
            ")->execute([$page_id, $menu_title, $menu_icon, $menu_order]);

            $menu_id = (int)$db->lastInsertId();

            // Menu locations (multi-select)
            $locations = $_POST['menu_locations'] ?? ['navbar'];

            foreach ($locations as $loc) {
                $loc = preg_replace('/[^a-z0-9_-]/', '', $loc);
                $db->prepare("
                    INSERT INTO menu_locations (menu_id, location)
                    VALUES (?,?)
                ")->execute([$menu_id, $loc]);
            }
        }

        echo "✅ Sayfa başarıyla oluşturuldu";

    } catch (Exception $e) {
        echo "❌ Sayfa oluşturuldu fakat DB kaydı başarısız: " . $e->getMessage();
    }

    exit;
}

echo "❌ Geçersiz istek";
exit;

