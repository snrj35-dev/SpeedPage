<?php
require_once __DIR__ . '/../settings.php';

// db.php — Centralized database connection

try {
    $db = new PDO("sqlite:" . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("PRAGMA journal_mode = WAL;");
    $db->exec("PRAGMA busy_timeout = 5000;");
} catch (Throwable $e) {
    die("Database connection failed.");
}

