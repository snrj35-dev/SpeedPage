<?php
declare(strict_types=1);

// ---------------------------------------------
// 0. Global Logger & Core Loaders
// ---------------------------------------------
require_once __DIR__ . '/php/logger.php';
require_once __DIR__ . '/php/hooks.php';

define('DEBUG', false); // Geliştirme aşamasında true, yayında false yapın

error_reporting(E_ALL);

if (DEBUG) {
	ini_set('display_errors', '1');
} else {
	ini_set('display_errors', '0');
}

// ✅ Global Hata Yakalayıcıları Kaydet
set_error_handler('sp_error_handler');
set_exception_handler('sp_exception_handler');

// ---------------------------------------------
// 1. URL Tabanlı Ayarlar (Tarayıcı için)
// ---------------------------------------------

define('BASE_PATH', '/');
define('BASE_URL', 'http://localhost' . BASE_PATH);
define('CDN_URL', BASE_URL . 'cdn/');

// ---------------------------------------------
// 2. Dosya Sistemi Yolları (PHP için)
// ---------------------------------------------
define('ROOT_DIR', __DIR__ . '/');
define('PHP_DIR', ROOT_DIR . 'php/');
define('LANG_DIR', ROOT_DIR . 'cdn/lang/');

if (!defined('DB_PATH')) {
	define('DB_PATH', ROOT_DIR . 'admin/veritabanı/data.db');
}

// Helper XSS function - Optimized for PHP 8.1+ (mixed & null-safe)
if (!function_exists('e')) {
	function e(mixed $str): string
	{
		return htmlspecialchars((string) ($str ?? ''), ENT_QUOTES, 'UTF-8');
	}
}

// Helper Translation function - DB Independent & Fallback Optimized
if (!function_exists('__')) {
	function __(string $key, ...$args): string
	{
		static $translations = null;
		if ($translations === null) {
			global $db;
			$lang = 'tr'; // Default

			// Lazy load DB logic (DB_Switch integrated via db.php)
			if (empty($db) && file_exists(ROOT_DIR . 'admin/db.php')) {
				try {
					require_once ROOT_DIR . 'admin/db.php';
				} catch (Throwable) {
					// Fallback handled below
				}
			}

			// Attempt to get language from DB (agnostic)
			if (!empty($db) && $db instanceof PDO) {
				try {
					$stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'language' LIMIT 1");
					$stmt->execute();
					$lang = $stmt->fetchColumn() ?: 'tr';
				} catch (Throwable) {
					$lang = 'tr';
				}
			}

			$langFile = LANG_DIR . $lang . '.json';

			// Primary language file attempt
			if (file_exists($langFile)) {
				$translations = json_decode(file_get_contents($langFile), true);
			}

			// Critical Fallback: if primary fails or is empty, load tr.json as hard fallback
			if (empty($translations)) {
				$fallbackFile = LANG_DIR . 'tr.json';
				if (file_exists($fallbackFile)) {
					$translations = json_decode(file_get_contents($fallbackFile), true);
				}
			}

			$translations ??= [];
		}

		$text = $translations[$key] ?? $key;
		if (!empty($args)) {
			return vsprintf((string) $text, $args);
		}
		return (string) $text;
	}
}



