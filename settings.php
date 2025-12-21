<?php

// ---------------------------------------------
// 1. URL Tabanlı Ayarlar (Tarayıcı için)
// ---------------------------------------------

define('BASE_PATH', '/'); 

// Tam Site URL'si (Dil dosyalarını çekerken işimize yarar)
// Bu kısım genellikle otomatik belirlenir, ancak basitlik için elle ayarlayabiliriz:
// Not: http veya https'i buradan yönetmek daha temizdir.
define('BASE_URL', 'http://localhost' . BASE_PATH); 

// CDN yolu
define('CDN_URL', BASE_URL . 'cdn/');


// ---------------------------------------------
// 2. Dosya Sistemi Yolları (PHP için)
// ---------------------------------------------

// settings.php'nin bulunduğu dizin
define('ROOT_DIR', __DIR__ . '/');

// PHP dosyaları
define('PHP_DIR', ROOT_DIR . 'php/');

// Sayfa dosyaları
define('SAYFALAR_DIR', ROOT_DIR . 'sayfalar/');

// Dil dosyaları (JSON)
define('LANG_DIR', ROOT_DIR . 'cdn/lang/');

// Database dosyası yolu
if (!defined('DB_PATH')) {
	define('DB_PATH', ROOT_DIR . 'admin/veritabanı/data.db');
}

?>

