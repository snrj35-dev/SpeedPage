<?php
declare(strict_types=1);

/**
 * SpeedPage Centralized Database Connection
 * Modernized for PHP 8.3+ and Multi-DB Support
 */

require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db_switch.php';

try {
    // 1. Settings.php'den yapılandırmayı al
    // Not: settings.php'de bu sabitlerin tanımlı olduğunu varsayıyoruz.
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'sqlite';

    $dbConfig = [
        'path' => defined('DB_PATH') ? DB_PATH : __DIR__ . '/veritabanı/data.db',
        'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
        'name' => defined('DB_NAME') ? DB_NAME : '',
        'user' => defined('DB_USER') ? DB_USER : '',
        'pass' => defined('DB_PASS') ? DB_PASS : '',
        'port' => defined('DB_PORT') ? DB_PORT : 3306,
    ];

    // 2. DB_Switch üzerinden bağlantıyı kur
    $switcher = new DB_Switch($dbType, $dbConfig);

    /** @var PDO $db */
    $db = $switcher->pdo;

    // 3. Global erişim ve Logger entegrasyonu için sakla
    $GLOBALS['db'] = $db;
    $GLOBALS['db_type'] = $dbType;

} catch (Throwable $e) {
    // Hata durumunda logger'a yaz ve kullanıcıya temiz bir hata dön
    if (function_exists('sp_log')) {
        sp_log("Database Connection Error: " . $e->getMessage(), 'db_error');
    }

    header('Content-Type: application/json');
    die(json_encode([
        "ok" => false,
        "error" => "Database connection failed. Please check settings.php"
    ]));
}
