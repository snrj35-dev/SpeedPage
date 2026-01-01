<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

// --- YARDIMCI FONKSİYON: Slug Temizle ---
function cleanSlug($s)
{
    return preg_replace('/[^a-z0-9_-]/', '', strtolower($s));
}

// 1. SİLME İŞLEMİ (Sayfa + Asset + Menü)
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    if (!$is_admin) {
        die("Yetkisiz işlem. Silme yetkiniz yok.");
    }
    $slug = cleanSlug($_GET['slug'] ?? '');

    if (!$slug) {
        die('<span lang="ersayfa"></span>');
    }

    $file = SAYFALAR_DIR . "$slug.php";

    try {
        // Sayfa ID'sini bul
        $stmt = $db->prepare("SELECT id FROM pages WHERE slug=?");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();

        if ($page) {
            $page_id = $page['id'];

            // A. Fiziksel Dosyayı Sil
            if (file_exists($file))
                unlink($file);

            // B. Page Assets (CSS/JS) Sil
            $db->prepare("DELETE FROM page_assets WHERE page_id=?")->execute([$page_id]);

            // C. Menü ve Konumları Sil (Eski page-delete.php mantığı)
            $menus = $db->prepare("SELECT id FROM menus WHERE page_id=?");
            $menus->execute([$page_id]);
            $menuRows = $menus->fetchAll();

            foreach ($menuRows as $m) {
                $menu_id = $m['id'];
                // Önce menü lokasyonlarını temizle
                $db->prepare("DELETE FROM menu_locations WHERE menu_id=?")->execute([$menu_id]);
                // Sonra menüyü sil
                $db->prepare("DELETE FROM menus WHERE id=?")->execute([$menu_id]);
            }

            // D. Son olarak Sayfayı DB'den sil
            $db->prepare("DELETE FROM pages WHERE id=?")->execute([$page_id]);

            sp_log("Sayfa silindi: $slug (ID: $page_id)", "page_delete", $page, null);
        }

        header("Location: index.php?page=pages"); // İşlem bitince listeye dön
        exit;

    } catch (Exception $e) {
        die("Hata oluştu: " . $e->getMessage());
    }
}

// 2. VERİ GETİRME (AJAX GET - Modal Doldurmak İçin)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_slug'])) {
    header('Content-Type: application/json');
    $slug = cleanSlug($_GET['get_slug']);
    $file = SAYFALAR_DIR . "$slug.php";

    $stmt = $db->prepare("SELECT id, title, description, icon, is_active FROM pages WHERE slug=?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();

    if ($page) {
        $stmt = $db->prepare("SELECT type, path FROM page_assets WHERE page_id=? ORDER BY load_order ASC");
        $stmt->execute([$page['id']]);
        $assets = $stmt->fetchAll();

        $css = [];
        $js = [];
        foreach ($assets as $a) {
            if ($a['type'] === 'css')
                $css[] = $a['path'];
            if ($a['type'] === 'js')
                $js[] = $a['path'];
        }

        echo json_encode([
            'success' => true,
            'slug' => $slug,
            'title' => $page['title'],
            'description' => $page['description'],
            'icon' => $page['icon'],
            'is_active' => (int) $page['is_active'],
            'css' => implode(', ', $css),
            'js' => implode(', ', $js),
            'content' => file_exists($file) ? file_get_contents($file) : ''
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// 3. OLUŞTURMA VEYA GÜNCELLEME (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        if (isset($_POST['mode']) && $_POST['mode'] === 'create')
            die("CSRF verification failed");
        echo json_encode(['success' => false, 'error' => 'CSRF verification failed']);
        exit;
    }
    $mode = $_POST['mode'] ?? 'create';
    $slug = cleanSlug($_POST['slug']);
    $content = $_POST['content'] ?? $_POST['icerik'];

    $title = trim($_POST['title'] ?? '') ?: $slug;
    $description = trim($_POST['description'] ?? '') ?: null;
    $icon = trim($_POST['icon'] ?? '') ?: null;
    $is_active = isset($_POST['aktif']) ? (int) $_POST['aktif'] : (isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1);

    $css = array_filter(array_map('trim', explode(',', $_POST['css'] ?? '')));
    $js = array_filter(array_map('trim', explode(',', $_POST['js'] ?? '')));

    $file = SAYFALAR_DIR . "$slug.php";

    try {
        if ($mode === 'create') {
            if (file_exists($file)) {
                echo "❌ <span lang=\"page_exists\"></span>";
                exit;
            }

            file_put_contents($file, $content);
            $sort = (int) $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM pages")->fetchColumn();

            $stmt = $db->prepare("INSERT INTO pages (slug, title, description, icon, is_active, sort_order) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$slug, $title, $description, $icon, $is_active, $sort]);
            $page_id = $db->lastInsertId();

            // Menü Ekleme
            if (!empty($_POST['add_to_menu'])) {
                $m_title = trim($_POST['menu_title'] ?? '') ?: $title;
                $m_icon = trim($_POST['menu_icon'] ?? '') ?: $icon;
                $m_order = (int) ($_POST['menu_order'] ?? $sort);

                $db->prepare("INSERT INTO menus (page_id, title, icon, sort_order, is_active) VALUES (?,?,?,?,1)")
                    ->execute([$page_id, $m_title, $m_icon, $m_order]);

                $menu_id = $db->lastInsertId();
                foreach (($_POST['menu_locations'] ?? ['navbar']) as $loc) {
                    $db->prepare("INSERT INTO menu_locations (menu_id, location) VALUES (?,?)")->execute([$menu_id, cleanSlug($loc)]);
                }
            }
            echo "✅ <span lang=\"page_created\"></span>";
            sp_log("Yeni sayfa oluşturuldu: $slug", "page_create", null, ['slug' => $slug, 'title' => $title]);
        } else {
            // Düzenleme (Edit)
            $old_slug = cleanSlug($_POST['old_slug']);

            // Eski veriyi çek
            $stmtOld = $db->prepare("SELECT * FROM pages WHERE slug=?");
            $stmtOld->execute([$old_slug]);
            $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

            // Dosya adını değiştirme (Eğer slug değiştiyse)
            if ($old_slug !== $slug) {
                if (file_exists(SAYFALAR_DIR . "$old_slug.php"))
                    unlink(SAYFALAR_DIR . "$old_slug.php");
            }
            file_put_contents($file, $content);

            $db->prepare("UPDATE pages SET slug=?, title=?, description=?, icon=?, is_active=? WHERE slug=?")
                ->execute([$slug, $title, $description, $icon, $is_active, $old_slug]);

            // Yeni veriyi hazırla (basitçe array olarak)
            $newData = [
                'slug' => $slug,
                'title' => $title,
                'description' => $description,
                'icon' => $icon,
                'is_active' => $is_active
            ];
            sp_log("Sayfa düzenlendi: $slug", "page_edit", $oldData, $newData);

            $stmt = $db->prepare("SELECT id FROM pages WHERE slug=?");
            $stmt->execute([$slug]);
            $page_id = $stmt->fetchColumn();
            echo json_encode(['success' => true]);
        }

        // Asset Güncelleme (Silip tekrar ekleme)
        $db->prepare("DELETE FROM page_assets WHERE page_id=?")->execute([$page_id]);
        foreach (['css' => $css, 'js' => $js] as $type => $paths) {
            $idx = 1;
            foreach ($paths as $p) {
                $db->prepare("INSERT INTO page_assets (page_id, type, path, load_order) VALUES (?,?,?,?)")
                    ->execute([$page_id, $type, $p, $idx++]);
            }
        }
    } catch (Exception $e) {
        if ($mode === 'create')
            echo "❌ Hata";
        else
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}