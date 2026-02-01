<?php
declare(strict_types=1);
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
                p.slug,
                mod.permissions
            FROM menus m
            LEFT JOIN pages p ON p.id = m.page_id
            LEFT JOIN modules mod ON mod.page_slug = p.slug
            INNER JOIN menu_locations ml ON ml.menu_id = m.id
            WHERE m.is_active = 1
              AND ml.location = ?
            ORDER BY m.sort_order ASC
        ");
        $stmt->execute([$location]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Role based filtering ---
        $userRole = $_SESSION['role'] ?? 'guest';
        $filtered = [];
        foreach ($rows as $row) {
            if (!empty($row['permissions'])) {
                $perms = json_decode($row['permissions'], true);
                if (is_array($perms) && !in_array($userRole, $perms)) {
                    continue; // Skip restricted menu item
                }
            }
            $filtered[] = $row;
        }
        return $filtered;
    } catch (Throwable $e) {
        return [];
    }
}



