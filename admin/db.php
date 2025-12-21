<?php
// db.php — Centralized database connection

// Ensure settings.php is loaded so DB_PATH and ROOT_DIR come from a single place
require_once __DIR__ . '/../settings.php';

if (!defined('DB_PATH')) {
    define('DB_PATH', ROOT_DIR . 'admin/veritabanı/data.db');
}

try {
    $db = new PDO("sqlite:" . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die("Database connection failed.");
}

