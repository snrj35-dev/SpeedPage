<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/notifications.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var PDO $db */
global $db;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Giriş yapmalısınız.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_notifications':
            $limit = 15;
            $stmt = $db->prepare("
                SELECT 
                    n.id,
                    n.user_id,
                    n.actor_id,
                    n.target_type,
                    n.target_id,
                    n.action_type,
                    n.content,
                    n.is_read,
                    n.created_at,
                    u.username AS actor_username,
                    u.avatar_url AS actor_avatar
                FROM notifications n
                LEFT JOIN users u ON n.actor_id = u.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            $rows = $stmt->fetchAll();

            // Unread count
            $cStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $cStmt->execute([$userId]);
            $unread = (int) $cStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'message' => 'Bildirimler yüklendi.',
                'data' => [
                    'list' => $rows,
                    'unread' => $unread
                ]
            ]);
            break;

        case 'mark_notifications_read':
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'message' => 'Tüm bildirimler okundu.']);
            break;

        case 'mark_notification_read':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            echo json_encode(['success' => true, 'message' => 'Bildirim okundu.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}

