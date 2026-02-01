<?php
declare(strict_types=1);

require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../admin/db.php';

/** @var PDO $db */
global $db;

/**
 * Genel bildirim ekleme helper'ı.
 *
 * @param int         $userId     Bildirimi alacak kullanıcı
 * @param int         $actorId    Eylemi yapan kullanıcı (0 = sistem)
 * @param string      $targetType 'forum', 'system', 'social' vb.
 * @param int|null    $targetId   İlgili kayıt ID'si (post_id, page_id, user_id ...)
 * @param string      $actionType 'new_user', 'new_page', 'like', 'reply', 'mention'...
 * @param string|null $content    Kısa açıklama veya link için ek veri
 */
function addNotification(
    int $userId,
    int $actorId,
    string $targetType,
    ?int $targetId,
    string $actionType,
    ?string $content = null
): bool {
    global $db;

    if ($userId === $actorId && $actorId !== 0) {
        return false;
    }

    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, actor_id, target_type, target_id, action_type, content)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([$userId, $actorId, $targetType, $targetId, $actionType, $content]);
}

