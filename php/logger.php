<?php
// GLOBAL LOGGER - php/logger.php

if (!function_exists('sp_log')) {

    // Veritabanı tablosunun varlığından emin ol (Lazy Init)
    function ensure_log_table()
    {
        global $db;
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
        try {
            // SQLite: datetime('now', '-30 days')
            $stmt = $db->prepare("DELETE FROM logs WHERE created_at < datetime('now', '-' || ? || ' days')");
            $stmt->execute([(int) $days]);
        } catch (Exception $e) {
            error_log("Log Cleanup Error: " . $e->getMessage());
        }
    }
}
?>