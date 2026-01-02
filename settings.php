<?php

// ---------------------------------------------
// 0. Global Logger
// ---------------------------------------------
require_once __DIR__ . '/php/logger.php';
require_once __DIR__ . '/php/hooks.php';

// Active Theme Definition MOVED to index.php (Dynamic)
define('DEBUG', false); // Geliştirme aşamasında true, yayında false yapın

error_reporting(E_ALL); // Tüm hataları yakala (Hata yakalayıcıya gönder)

if (DEBUG) {
	ini_set('display_errors', 1);
} else {
	ini_set('display_errors', 0);
}

// ✅ Global Hata Yakalayıcıları Kaydet
set_error_handler('sp_error_handler');
set_exception_handler('sp_exception_handler');

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

// Helper XSS function
if (!function_exists('e')) {
	function e($str)
	{
		if ($str === null)
			return '';
		return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
	}
}

// Helper Translation function
if (!function_exists('__')) {
	function __($key, ...$args)
	{
		static $translations = null;
		if ($translations === null) {
			global $db;
			if (!$db) {
				require_once ROOT_DIR . 'admin/db.php';
			}
			$q = $db->query("SELECT value FROM settings WHERE `key` = 'language'");
			$lang = $q->fetchColumn() ?: 'tr';
			$langFile = LANG_DIR . $lang . '.json';
			if (file_exists($langFile)) {
				$translations = json_decode(file_get_contents($langFile), true);
			} else {
				$translations = [];
			}
		}

		$text = $translations[$key] ?? $key;
		if (!empty($args)) {
			return vsprintf($text, $args);
		}
		return $text;
	}
}

?>
