<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // GET /admin/user-edit.php?id=...
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $stmt = $db->prepare("SELECT id, username, role, is_active, created_at FROM users WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) echo json_encode(['ok' => true, 'user' => $row]);
        else echo json_encode(['ok' => false, 'error' => 'NOT_FOUND', 'message_key' => 'user_not_found']);
        exit;
    }

    // List users
    $rows = $db->query("SELECT id, username, role, is_active, created_at FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'users' => $rows]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        // Only admin may assign role; otherwise default to 'user'
        $role = (!empty($is_admin) && $is_admin) ? ($_POST['role'] ?? 'user') : 'user';
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (!$username || !$password) {
            echo json_encode(['ok' => false, 'error' => 'MISSING', 'message_key' => 'fill_all_fields']);
            exit;
        }

        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, is_active) VALUES (?,?,?,?)");
            $stmt->execute([$username, $hash, $role, $is_active]);
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'message_key' => 'errdata']);
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? null;
        // Only admin may change role; otherwise preserve existing role
        $posted_role = $_POST['role'] ?? null;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (!$id || !$username) {
            echo json_encode(['ok' => false, 'error' => 'MISSING', 'message_key' => 'fill_all_fields']);
            exit;
        }

        try {
            if (!empty($is_admin) && $is_admin) {
                // admin can set role
                $roleToSet = $posted_role ?? 'user';
            } else {
                // keep existing role
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
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'message_key' => 'errdata']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'error' => 'MISSING', 'message_key' => 'data_missing']); exit; }
        if ($id === 1) { echo json_encode(['ok' => false, 'error' => 'MAIN_ADMIN', 'message_key' => 'main_admin_cannot_be_deleted']); exit; }
        try {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'INVALID']);
