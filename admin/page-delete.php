<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

$slug = $_GET['slug'] ?? '';
$slug = preg_replace('/[^a-z0-9_-]/', '', $slug);

if (!$slug) {
    die('<span lang="ersayfa"></span>');
}

$file = SAYFALAR_DIR . "$slug.php";

if (!file_exists($file)) {
    die('<span lang="ersayfa2"></span>');
}

// Delete physical file
unlink($file);

try {
    // Find page ID
    $stmt = $db->prepare("SELECT id FROM pages WHERE slug=?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();

    if ($page) {
        $page_id = $page['id'];

        // Delete page assets
        $db->prepare("DELETE FROM page_assets WHERE page_id=?")->execute([$page_id]);

        // Delete menus linked to this page
        $menus = $db->prepare("SELECT id FROM menus WHERE page_id=?");
        $menus->execute([$page_id]);
        $menuRows = $menus->fetchAll();

        foreach ($menuRows as $m) {
            $menu_id = $m['id'];

            // Delete menu locations
            $db->prepare("DELETE FROM menu_locations WHERE menu_id=?")->execute([$menu_id]);

            // Delete menu itself
            $db->prepare("DELETE FROM menus WHERE id=?")->execute([$menu_id]);
        }

        // Delete page record
        $db->prepare("DELETE FROM pages WHERE id=?")->execute([$page_id]);
    }

} catch (Exception $e) {
    // ignore errors
}

// Redirect back to admin panel
header("Location: index.php");
exit;

