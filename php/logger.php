<?php
// GLOBAL LOGGER - php/logger.php

if (!function_exists('sp_log')) {

    // Veritabanı tablosunun varlığından emin ol (Lazy Init)
    function ensure_log_table()
    {
        global $db;
        static $checked = false;

        if ($checked)
            return;

        if (!$db) {
            $db_file = __DIR__ . '/../admin/veritabanı/data.db';
            if (file_exists($db_file)) {
                try {
                    $db = new PDO("sqlite:" . $db_file);
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch (Exception $e) {
                    return;
                }
            } else {
                return;
            }
        }

        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

            // TRANSACTION SAFETY (MySQL): 
            // DDL (CREATE TABLE) inside a transaction causes an implicit commit in MySQL.
            // If we are in a transaction and haven't checked the table yet, we MUST skip DDL.
            if ($driver === 'mysql' && $db->inTransaction()) {
                // We don't mark $checked as true here because we might want to check again 
                // outside of the transaction scope later in the same request.
                return;
            }

            if ($driver === 'sqlite') {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NULL,
                        action_type TEXT NOT NULL, 
                        message TEXT,
                        ip_address TEXT,
                        user_agent TEXT,
                        old_data TEXT NULL,
                        new_data TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            } elseif ($driver === 'mysql') {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NULL,
                        action_type LONGTEXT NOT NULL, 
                        message LONGTEXT,
                        ip_address LONGTEXT,
                        user_agent LONGTEXT,
                        old_data LONGTEXT NULL,
                        new_data LONGTEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ");
            }
            $checked = true;
        } catch (Exception $e) {
            error_log("Log Table Creation Error: " . $e->getMessage());
        }
    }

    /*
     * @param string $message  Kısa açıklama (örn: "Login Failed")
     * @param string $type     Tür (login_fail, login_success, page_edit, etc.)
     * @param mixed  $old      Eski veri (Array veya Object ise JSON'a çevrilir)
     * @param mixed  $new      Yeni veri (Array veya Object ise JSON'a çevrilir)
     */
    function sp_log($message, $type, $old = null, $new = null)
    {
        global $db;

        // Tabloyu check et (performans için session cache kullanılabilir ama şimdilik her çağrıda 'IF NOT EXISTS' ucuzdur)
        ensure_log_table();

        // Eğer veritabanı hala bağlı değilse loglama yapma
        if (!$db)
            return;

        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Verileri JSON yap
        $old_json = ($old !== null) ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
        $new_json = ($new !== null) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;

        try {
            $stmt = $db->prepare("INSERT INTO logs (user_id, action_type, message, ip_address, user_agent, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $type, $message, $ip, $ua, $old_json, $new_json]);
        } catch (Exception $e) {
            // Loglama hatası sistemin çalışmasını durdurmamalı
            error_log("Logger Error: " . $e->getMessage());
        }
    }

    // Otomatik Temizlik (Varsayılan 30 gün)
    function sp_log_cleanup($days = 30)
    {
        global $db;
        ensure_log_table();
        if (!$db)
            return;

        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $db->prepare("DELETE FROM logs WHERE created_at < datetime('now', '-' || ? || ' days')");
            } else {
                $stmt = $db->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            }
            $stmt->execute([(int) $days]);
        } catch (Exception $e) {
            error_log("Log Cleanup Error: " . $e->getMessage());
        }
    }

    /**
     * PHP Hatalarını Yakalar ve Veritabanına Loglar
     */
    function sp_error_handler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno))
            return false;

        $type_map = [
            E_ERROR => 'PHP_ERROR_FATAL',
            E_WARNING => 'PHP_WARNING',
            E_NOTICE => 'PHP_NOTICE',
            E_USER_ERROR => 'PHP_USER_ERROR',
            E_USER_WARNING => 'PHP_USER_WARNING',
            E_USER_NOTICE => 'PHP_USER_NOTICE',
            E_DEPRECATED => 'PHP_DEPRECATED',
        ];

        $type = $type_map[$errno] ?? 'PHP_ERROR_UNKNOWN';
        $data = ['file' => $errfile, 'line' => $errline, 'type' => $type];

        sp_log("[$type] $errstr", 'system_error', null, $data);

        return defined('DEBUG') && DEBUG === true ? false : true;
    }

    /**
     * Yakalanmamış İstisnaları (Exceptions) Yakalar ve Veritabanına Loglar
     */
    function sp_exception_handler($exception)
    {
        $data = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        sp_log("[Exception] " . $exception->getMessage(), 'php_exception', null, $data);

        if (defined('DEBUG') && DEBUG === true) {
            echo "<h2>Unhandled Exception</h2><p><b>" . $exception->getMessage() . "</b></p><pre>" . $exception->getTraceAsString() . "</pre>";
        } else {
            if (!headers_sent())
                http_response_code(500);
            echo "Sistemde bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }
}
?>