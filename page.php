<?php
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/settings.php";
require_once __DIR__ . "/admin/db.php";
global $db;

$page = $_GET["page"] ?? "home";

/* Güvenlik */
if (!preg_match('/^[a-z0-9_-]+$/', $page)) {
    echo json_encode([
        "html" => "<div class='alert alert-danger' lang='ersayfa'></div>",
        "assets" => []
    ]);
    exit;
}

/* Sayfa dosyası */
$file = __DIR__ . "/sayfalar/$page.php";

if (!file_exists($file)) {
    echo json_encode([
        "html" => "<div class='alert alert-warning' lang='ersayfa2'></div>",
        "assets" => []
    ]);
    exit;
}

/* Sayfa ID'sini bul */
// Get page id and active state
$stmt = $db->prepare("SELECT id, is_active FROM pages WHERE slug = ?");
$stmt->execute([$page]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$page_id = $row['id'] ?? null;

// If page exists but is not active, return warning
if ($row && isset($row['is_active']) && !$row['is_active']) {
    echo json_encode([
        "html" => "<div class='alert alert-warning' lang='ersayfa2'></div>",
        "assets" => []
    ]);
    exit;
}

/* Assetleri DB'den çek */
$assets = [
    "css" => [],
    "js"  => []
];

if ($page_id) {
    $stmt = $db->prepare("
        SELECT type, path 
        FROM page_assets 
        WHERE page_id = ?
        ORDER BY load_order ASC
    ");
    $stmt->execute([$page_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        if ($a['type'] === 'css') {
            $assets['css'][] = "cdn/css/" . $a['path'];
        } elseif ($a['type'] === 'js') {
            $assets['js'][] = "cdn/js/" . $a['path'];
        }
    }
}

/* HTML içeriğini al */
ob_start();
include $file;
$html = ob_get_clean();

/* JSON Response */
echo json_encode([
    "html"   => $html,
    "assets" => $assets
]);

