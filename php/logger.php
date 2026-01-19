<?php
declare(strict_types=1);

// GLOBAL LOGGER - php/logger.php

if (!function_exists('sp_log')) {

    // Veritabanı tablosunun varlığından emin ol (Lazy Init)
    function ensure_log_table(): void
    {
        global $db;
        static $checked = false;

        if ($checked) {
            return;
        }

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
     * @param string $message  Short description
     * @param string $type     Type (login_fail, login_success, page_edit, etc.)
     * @param mixed  $old      Old data (serialized to JSON)
     * @param mixed  $new      New data (serialized to JSON)
     */
    function sp_log(string $message, string $type, mixed $old = null, mixed $new = null): void
    {
        global $db;

        // Ensure table exists
        ensure_log_table();

        // If DB is still not connected, abort
        if (!$db) {
            return;
        }

        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id !== null) {
            $user_id = (int) $user_id;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Serialize to JSON
        $old_json = ($old !== null) ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
        $new_json = ($new !== null) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;

        try {
            $stmt = $db->prepare("INSERT INTO logs (user_id, action_type, message, ip_address, user_agent, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $type, $message, $ip, $ua, $old_json, $new_json]);
        } catch (Exception $e) {
            // Logging failure should not stop execution
            error_log("Logger Error: " . $e->getMessage());
        }
    }

    // Auto Cleanup (Default 30 days)
    function sp_log_cleanup(int $days = 30): void
    {
        global $db;
        ensure_log_table();
        if (!$db) {
            return;
        }

        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $db->prepare("DELETE FROM logs WHERE created_at < datetime('now', '-' || ? || ' days')");
            } else {
                $stmt = $db->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            }
            $stmt->execute([$days]);
        } catch (Exception $e) {
            error_log("Log Cleanup Error: " . $e->getMessage());
        }
    }

    /**
     * PHP Error Handler
     */
    function sp_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

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

        // If DEBUG is false, suppress standard error output by returning true
        return !(defined('DEBUG') && DEBUG === true);
    }

    /**
     * Uncaught Exception Handler
     */
    function sp_exception_handler(Throwable $exception): void
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
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo "Sistemde bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }

    /**
     * Renders fatal error reporter button even on white screen death
     */
    function sp_fatal_reporter_render(): void
    {
        $error = error_get_last();
        // Only run on critical errors
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            echo "";
            if (function_exists('run_hook')) {
                run_hook('footer_end');
            }
        }
    }

    register_shutdown_function('sp_fatal_reporter_render');
}
?>