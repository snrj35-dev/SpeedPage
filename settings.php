<?php
declare(strict_types=1);

// ---------------------------------------------
// 0. Core Definitions
// ---------------------------------------------
define('ROOT_DIR', __DIR__ . '/');
define('STORAGE_DIR', ROOT_DIR . 'admin/_internal_storage/');
define('CACHE_DIR', STORAGE_DIR . '_cache/');
define('TEMP_DIR', STORAGE_DIR . '_temp/');
define('DATA_DIR', STORAGE_DIR . 'data_secure/');

// ---------------------------------------------
// 1. Caching & Optimization Helpers (Must be early)
// ---------------------------------------------
function sp_cache_set(string $key, mixed $data, int $ttl = 3600): void
{
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
        file_put_contents(CACHE_DIR . '.htaccess', "Deny from all");
    }
    $file = CACHE_DIR . md5($key) . '.cache';
    $cacheData = [
        'expires' => time() + $ttl,
        'data' => $data
    ];
    file_put_contents($file, json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function sp_cache_get(string $key): mixed
{
    $file = CACHE_DIR . md5($key) . '.cache';
    if (!file_exists($file))
        return null;

    $raw = file_get_contents($file);
    $cacheData = is_string($raw) ? json_decode($raw, true) : null;
    if (
        !is_array($cacheData) ||
        !isset($cacheData['expires']) ||
        time() > (int) $cacheData['expires']
    ) {
        @unlink($file);
        return null;
    }
    return $cacheData['data'] ?? null;
}

function sp_cache_delete(string $key): void
{
    $file = CACHE_DIR . md5($key) . '.cache';
    if (file_exists($file))
        unlink($file);
}

function sp_cache_flush(): void
{
    if (!is_dir(CACHE_DIR))
        return;
    $files = glob(CACHE_DIR . '*.cache');
    foreach ($files as $file) {
        if (is_file($file))
            unlink($file);
    }
}

// ---------------------------------------------
// 2. Global Logger & Session Start
// ---------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
    );

    // Session duration optimization via cache
    $s_duration = sp_cache_get('session_duration');
    if ($s_duration === null && file_exists(ROOT_DIR . 'admin/veritabanı/data.db')) {
        try {
            $db_tmp = new PDO("sqlite:" . ROOT_DIR . 'admin/veritabanı/data.db');
            $s_stmt = $db_tmp->query("SELECT value FROM settings WHERE `key`='session_duration' LIMIT 1");
            $s_duration = $s_stmt->fetchColumn();
            if ($s_duration) {
                sp_cache_set('session_duration', $s_duration, 86400); // Cache for 24h
            }
        } catch (Throwable) {
        }
    }

    $sessionCookieParams = [
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps
    ];

    if ($s_duration) {
        $duration = (int) $s_duration;
        ini_set('session.gc_maxlifetime', (string) $duration);
        $sessionCookieParams['lifetime'] = $duration;
    }
    session_set_cookie_params($sessionCookieParams);
    session_start();
}

// Global CSRF Token Generation
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/php/logger.php';
require_once __DIR__ . '/php/hooks.php';

define('APP_VERSION', '1.0.1'); // Global assets version
$debugEnv = getenv('APP_DEBUG');
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalIp = in_array($remoteIp, ['127.0.0.1', '::1'], true);
$debugMode = ($debugEnv !== false)
    ? (bool) filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN)
    : $isLocalIp;
define('DEBUG', $debugMode);

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

define('BASE_PATH', '/cms/');
$baseScheme = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
) ? 'https' : 'http';
$baseHost = $_SERVER['HTTP_HOST'] ?? 'osman.center';
if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]+)?$/', $baseHost)) {
    $baseHost = 'osman.center';
}
define('BASE_URL', $baseScheme . '://' . $baseHost . BASE_PATH);
define('CDN_URL', BASE_URL . 'cdn/');

// ---------------------------------------------
// 2. Dosya Sistemi Yolları (PHP için)
// ---------------------------------------------
define('PHP_DIR', ROOT_DIR . 'php/');
define('LANG_DIR', ROOT_DIR . 'cdn/lang/');

if (!defined('DB_PATH')) {
    define('DB_PATH', DATA_DIR . 'data.db');
}

// Helper XSS function - Optimized for PHP 8.1+ (mixed & null-safe)
if (!function_exists('e')) {
    function e(mixed $str): string
    {
        return htmlspecialchars((string) ($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * CSRF validation helper - Supports both HTML and JSON/AJAX responses
 */
if (!function_exists('check_csrf')) {
    function check_csrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            $sessionToken = $_SESSION['csrf'] ?? null;
            if (
                !is_string($token)
                || !is_string($sessionToken)
                || $token === ''
                || !hash_equals($sessionToken, $token)
            ) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    die(json_encode(['status' => 'error', 'message' => __('csrf_error')]));
                }
                die(__('csrf_error'));
            }
        }
    }
}

/**
 * Initializes the global $translations array from settings and filesystem.
 */
function sp_init_translations(): void
{
    global $translations, $db;
    if ($translations !== null)
        return;

    $lang = $_SESSION['lang'] ?? null;

    if (!$lang) {
        $lang = sp_cache_get('default_lang') ?? 'tr';

        if ($lang === 'tr') { // Re-verify if it was just default fallback
            // Lazy load DB logic (DB_Switch integrated via db.php)
            if (empty($db) && file_exists(ROOT_DIR . 'admin/db.php')) {
                try {
                    require_once ROOT_DIR . 'admin/db.php';
                } catch (Throwable) {
                }
            }

            // Attempt to get language from DB (agnostic)
            if (!empty($db) && $db instanceof PDO) {
                try {
                    $stmt = $db->prepare("SELECT value FROM settings WHERE " .
                        (($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') ? "`key`" : "key") .
                        " = ? LIMIT 1");
                    $stmt->execute(['default_lang']);
                    $dbLang = $stmt->fetchColumn();
                    if ($dbLang) {
                        $lang = $dbLang;
                        sp_cache_set('default_lang', $lang, 86400);
                    }
                } catch (Throwable) {
                    $lang = 'tr';
                }
            }
        }
    }

    $langFile = LANG_DIR . $lang . '.json';

    // Primary language file attempt
    if (file_exists($langFile)) {
        $translations = json_decode(file_get_contents($langFile), true);
    }

    $translations ??= [];
}

/**
 * Loads a module language file and merges it into the global translations.
 * Usage: sp_load_language('forum'); // loads forum/lang_tr.json
 */
function sp_load_language(string $module, ?string $file = null): void
{
    global $translations;
    if ($translations === null) {
        sp_init_translations();
    }

    $lang = $_SESSION['lang'] ?? 'tr';
    $moduleDir = ROOT_DIR . "modules/" . $module . "/";

    if ($file === null) {
        $langFile = $moduleDir . "lang_" . $lang . ".json";
        // Fallback to lang.json
        if (!file_exists($langFile)) {
            $langFile = $moduleDir . "lang.json";
        }
    } else {
        $langFile = $moduleDir . $file;
    }

    if (file_exists($langFile)) {
        $mTrans = json_decode(file_get_contents($langFile), true);
        if (is_array($mTrans)) {
            $translations = array_merge($translations, $mTrans);
        }
    }
}

// Helper Translation function - DB Independent & Fallback Optimized
if (!function_exists('__')) {
    function __(string $key, ...$args): string
    {
        global $translations;
        if ($translations === null) {
            sp_init_translations();
        }

        $text = $translations[$key] ?? $key;
        if (!empty($args)) {
            return vsprintf((string) $text, $args);
        }
        return (string) $text;
    }
}
