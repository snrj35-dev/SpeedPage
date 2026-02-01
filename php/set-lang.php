<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$lang = $data['lang'] ?? null;

// Hardcode allowed languages for security
$allowed = ['tr', 'en', 'de', 'fr', 'es', 'it'];
if ($lang && in_array($lang, $allowed, true)) {
    $_SESSION['lang'] = $lang;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid or unsupported language']);
}
