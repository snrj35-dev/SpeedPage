<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

// Ensure standard JSON response
header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($is_admin) || !$is_admin) {
        echo json_encode(['ok' => false, 'error' => 'ACCESS_DENIED', 'message_key' => 'access_denied'], JSON_THROW_ON_ERROR);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    // $userId'yi almaya gerek yok, sp_log session'dan okur.

    // --- GET Request: Fetch User List or Single User ---
    if ($method === 'GET') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            $stmt = $db->prepare("SELECT id, username, role, is_active, created_at FROM users WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                echo json_encode(['ok' => true, 'user' => $row], JSON_THROW_ON_ERROR);
            } else {
                echo json_encode(['ok' => false, 'error' => 'NOT_FOUND', 'message_key' => 'user_not_found'], JSON_THROW_ON_ERROR);
            }
            exit;
        }

        // Pagination & Search settings
        $search = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $where = " WHERE 1=1 ";
        $params = [];
        if ($search) {
            $where .= " AND username LIKE ? ";
            $params[] = "%$search%";
        }

        // Total count for pagination
        $totalStmt = $db->prepare("SELECT COUNT(*) FROM users $where");
        $totalStmt->execute($params);
        $totalUsers = (int)$totalStmt->fetchColumn();
        $totalPages = ceil($totalUsers / $limit);

        // List users with limit
        $stmt = $db->prepare("SELECT id, username, role, is_active, created_at FROM users $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true, 
            'users' => $rows, 
            'total_pages' => $totalPages, 
            'current_page' => $page,
            'total_users' => $totalUsers
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    // --- POST Request: Create / Update / Delete ---
    if ($method === 'POST') {
        $csrf = filter_input(INPUT_POST, 'csrf', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$csrf || $csrf !== $_SESSION['csrf']) {
            echo json_encode(['ok' => false, 'error' => 'CSRF verification failed', 'message_key' => 'csrf_error'], JSON_THROW_ON_ERROR);
            exit;
        }

        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'create';

        // 1. CREATE USER
        if ($action === 'create') {
            $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $password = $_POST['password'] ?? '';

            $role = 'user';
            if (!empty($is_admin) && $is_admin) {
                $roleInput = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
                if ($roleInput)
                    $role = $roleInput;
            }

            $is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT) ?? 1;

            if (!$username || !$password) {
                echo json_encode(['ok' => false, 'error' => 'MISSING', 'message_key' => 'fill_all_fields'], JSON_THROW_ON_ERROR);
                exit;
            }

            // Check if username already exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'error' => 'EXISTS', 'message_key' => 'username_taken'], JSON_THROW_ON_ERROR);
                exit;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, is_active) VALUES (?,?,?,?)");
            $stmt->execute([$username, $hash, $role, $is_active]);

            if (function_exists('sp_log')) {
                sp_log("Yeni kullanıcı eklendi: $username ($role)", 'user_add');
            }

            echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
            exit;
        }

        // 2. UPDATE USER
        if ($action === 'update') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $password = $_POST['password'] ?? null;
            $roleInput = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
            $is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT) ?? 1;

            if (!$id || !$username) {
                echo json_encode(['ok' => false, 'error' => 'MISSING', 'message_key' => 'fill_all_fields'], JSON_THROW_ON_ERROR);
                exit;
            }

            // Determine role to set
            $roleToSet = 'user';
            if (!empty($is_admin) && $is_admin) {
                $roleToSet = $roleInput ?? 'user';
            } else {
                $stmt = $db->prepare("SELECT role FROM users WHERE id=?");
                $stmt->execute([$id]);
                $roleRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $roleToSet = $roleRow['role'] ?? 'user';
            }

            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET username=?, password_hash=?, role=?, is_active=? WHERE id=?")
                    ->execute([$username, $hash, $roleToSet, $is_active, $id]);
            } else {
                $db->prepare("UPDATE users SET username=?, role=?, is_active=? WHERE id=?")
                    ->execute([$username, $roleToSet, $is_active, $id]);
            }

            if (function_exists('sp_log')) {
                sp_log("Kullanıcı güncellendi: $username (ID: $id)", 'user_update');
            }

            echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
            exit;
        }

        // 3. DELETE USER
        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id) {
                echo json_encode(['ok' => false, 'error' => 'MISSING', 'message_key' => 'data_missing'], JSON_THROW_ON_ERROR);
                exit;
            }
            if ($id === 1) {
                echo json_encode(['ok' => false, 'error' => 'MAIN_ADMIN', 'message_key' => 'main_admin_cannot_be_deleted'], JSON_THROW_ON_ERROR);
                exit;
            }

            $stmt = $db->prepare("SELECT username FROM users WHERE id=?");
            $stmt->execute([$id]);
            $uName = $stmt->fetchColumn() ?: 'Unknown';

            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);

            if (function_exists('sp_log')) {
                sp_log("Kullanıcı silindi: $uName (ID: $id)", 'user_delete');
            }

            echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'INVALID_METHOD', 'message_key' => 'invalid_request'], JSON_THROW_ON_ERROR);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'message_key' => 'errdata'], JSON_THROW_ON_ERROR);
}
