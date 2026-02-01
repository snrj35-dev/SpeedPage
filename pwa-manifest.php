<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/admin/db.php';

global $db;
$siteName = "SpeedPage";
$siteShortName = "SpeedPage";
$description = "Modern and Fast CMS";
$themeColor = "#121212";

try {
    $st = $db->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('site_name', 'site_description', 'theme_color')");
    $settings = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $siteName = $settings['site_name'] ?? $siteName;
    $siteShortName = $siteName;
    $description = $settings['site_description'] ?? $description;
    $themeColor = $settings['theme_color'] ?? $themeColor;
} catch (Throwable $e) {}

$manifest = [
    "name" => $siteName,
    "short_name" => $siteShortName,
    "description" => $description,
    "start_url" => BASE_PATH,
    "scope" => BASE_PATH,
    "display" => "standalone",
    "background_color" => $themeColor,
    "theme_color" => $themeColor,
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => BASE_URL . "cdn/images/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => BASE_URL . "cdn/images/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
