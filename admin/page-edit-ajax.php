<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// ---------------- GET: PAGE LOAD ----------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $slug = preg_replace('/[^a-z0-9_-]/', '', $_GET['slug']);
    $file = SAYFALAR_DIR . "$slug.php";

    if (!file_exists($file)) {
        echo json_encode(['error' => 'PAGE_NOT_FOUND', 'message_key' => 'ersayfa2']);
        exit;
    }

    // Read file content (HTML only)
    $content = file_get_contents($file);

    // Load page meta from DB
    $stmt = $db->prepare("SELECT id, title, description, icon, is_active FROM pages WHERE slug=?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();

    if (!$page) {
        echo json_encode(['error' => 'PAGE_META_NOT_FOUND', 'message_key' => 'ersayfa']);
        exit;
    }

    // Load assets from DB
    $stmt = $db->prepare("SELECT type, path FROM page_assets WHERE page_id=? ORDER BY load_order ASC");
    $stmt->execute([$page['id']]);
    $assets = $stmt->fetchAll();

    $css = [];
    $js  = [];

    foreach ($assets as $a) {
        if ($a['type'] === 'css') $css[] = $a['path'];
        if ($a['type'] === 'js')  $js[]  = $a['path'];
    }

    echo json_encode([
        'slug'        => $slug,
        'title'       => $page['title'],
        'description' => $page['description'],
        'icon'        => $page['icon'],
        'is_active'   => (int)$page['is_active'],
        'css'         => $css,
        'js'          => $js,
        'content'     => $content
    ]);
    exit;
}

// ---------------- POST: SAVE PAGE ----------------

$old = preg_replace('/[^a-z0-9_-]/', '', $_POST['old_slug']);
$new = preg_replace('/[^a-z0-9_-]/', '', $_POST['slug']);

$title       = trim($_POST['title']);
$description = trim($_POST['description'] ?? '');
$icon        = trim($_POST['icon'] ?? '');
$is_active   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

$css = array_filter(array_map('trim', explode(',', $_POST['css'])));
$js  = array_filter(array_map('trim', explode(',', $_POST['js'])));

$content = $_POST['content'];

$oldFile = SAYFALAR_DIR . "$old.php";
$newFile = SAYFALAR_DIR . "$new.php";

// Save HTML content only
file_put_contents($newFile, $content);

// Delete old file if slug changed
if ($old !== $new && file_exists($oldFile)) {
    unlink($oldFile);
}

// Update DB
try {
    // Check if page exists
    $stmt = $db->prepare("SELECT id FROM pages WHERE slug=?");
    $stmt->execute([$old]);
    $row = $stmt->fetch();

    if ($row) {
        // Update existing page
        $page_id = $row['id'];

        $db->prepare("
            UPDATE pages 
            SET slug=?, title=?, description=?, icon=?, is_active=? 
            WHERE id=?
        ")->execute([$new, $title, $description, $icon, $is_active, $page_id]);

    } else {
        // Insert new page
        $sort_order = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM pages")->fetchColumn();

        $db->prepare("
            INSERT INTO pages (slug, title, description, icon, is_active, sort_order)
            VALUES (?,?,?,?,?,?)
        ")->execute([$new, $title, $description, $icon, $is_active, $sort_order]);

        $page_id = $db->lastInsertId();
    }

    // Clear old assets
    $db->prepare("DELETE FROM page_assets WHERE page_id=?")->execute([$page_id]);

    // Insert new CSS
    $order = 1;
    foreach ($css as $c) {
        $db->prepare("
            INSERT INTO page_assets (page_id, type, path, load_order)
            VALUES (?,?,?,?)
        ")->execute([$page_id, 'css', $c, $order++]);
    }

    // Insert new JS
    foreach ($js as $j) {
        $db->prepare("
            INSERT INTO page_assets (page_id, type, path, load_order)
            VALUES (?,?,?,?)
        ")->execute([$page_id, 'js', $j, $order++]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'message_key' => 'errdata']);
}

