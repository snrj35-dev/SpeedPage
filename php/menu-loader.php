<?php
// php/menu-loader.php
require_once __DIR__ . '/../admin/db.php';

function getMenus(string $location = 'home'): array
{
    try {
        $stmt = $GLOBALS['db']->prepare("
            SELECT 
                m.title,
                m.icon,
                m.external_url,
                p.slug
            FROM menus m
            LEFT JOIN pages p ON p.id = m.page_id
            INNER JOIN menu_locations ml ON ml.menu_id = m.id
            WHERE m.is_active = 1
              AND ml.location = ?
            ORDER BY m.sort_order ASC
        ");
        $stmt->execute([$location]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}



