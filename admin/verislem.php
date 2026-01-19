<?php
declare(strict_types=1);

/**
 * SpeedPage Admin Data Operations (verislem.php)
 * Stabilized for PHP 8.3 | CSRF Protected | Standardized JSON API
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

// Standard JSON Header
header("Content-Type: application/json; charset=utf-8");

// Response Template
$response = [
    "ok" => false,
    "error" => "",
    "data" => []
];

try {
    // 1. Input Processing
    $rawInput = file_get_contents("php://input");
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $_POST = array_merge($_POST, $jsonData);
    }

    $action = (string) filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
    $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

    // 2. Strict CSRF Validation for all POST operations
    if ($isPost) {
        $token = $_POST['csrf'] ?? '';
        if (!$token || $token !== ($_SESSION['csrf'] ?? '')) {
            throw new Exception("CSRF_VERIFICATION_FAILED");
        }
    }

    // 3. Admin Permissions Check (for specific actions if needed)
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

    // 4. Action Routing using PHP 8.0 match expression
    if ($action === 'export_sql_file') {
        $dump = generate_agnostic_dump($db);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup.sql"');
        echo $dump;
        exit;
    }

    $result = match ($action) {
        'tables' => handleListTables($db),
        'columns' => handleListColumns($db),
        'rows' => handleListRows($db),
        'insert' => handleInsert($db),
        'update' => handleUpdate($db),
        'delete' => handleDelete($db),
        'sql' => handleSqlExec($db, $is_admin),
        'export_sql' => handleExportSql($db),
        'import_sql' => handleImportSql($db, $is_admin),
        'import_file' => handleImportFile($db, $is_admin),
        default => throw new Exception("INVALID_ACTION")
    };

    $response["ok"] = true;
    $response["data"] = $result;

} catch (Throwable $e) {
    $response["ok"] = false;
    $response["error"] = $e->getMessage();
}

echo json_encode($response);
exit;

/* ======================================================
   HANDLER FUNCTIONS
   ====================================================== */

function handleListTables(PDO $db): array
{
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    return match ($driver) {
        'sqlite' => $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN),
        'mysql' => $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN),
        default => []
    };
}

function handleListColumns(PDO $db): array
{
    $table = preg_replace("/[^a-zA-Z0-9_]/", "", $_GET["table"] ?? "");
    if (!$table)
        return [];

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        return $db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $cols = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($c) => [
            'name' => $c['Field'],
            'type' => $c['Type'],
            'notnull' => ($c['Null'] === 'NO' ? 1 : 0),
            'dflt_value' => $c['Default'],
            'pk' => ($c['Key'] === 'PRI' ? 1 : 0),
            'extra' => $c['Extra']
        ], $cols);
    }
}

function handleListRows(PDO $db): array
{
    $table = preg_replace("/[^a-zA-Z0-9_]/", "", $_GET["table"] ?? "");
    if (!$table)
        return [];

    $stmt = $db->prepare("SELECT * FROM `$table` LIMIT 200");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function handleInsert(PDO $db): bool
{
    $table = preg_replace("/[^a-zA-Z0-9_]/", "", $_POST["table"] ?? "");
    $data = $_POST["data"] ?? [];
    if (!$table || !is_array($data))
        throw new Exception("REQUIRED_PARAMS_MISSING");

    // Auto-increment stripping logic
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if ($c['pk'] && stripos($c['type'], 'INT') !== false && empty($data[$c['name']])) {
                unset($data[$c['name']]);
            }
        }
    } else {
        $cols = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (stripos($c['Extra'] ?? '', 'auto_increment') !== false) {
                unset($data[$c['Field']]);
            }
        }
    }

    if ($table === "users" && isset($data["password_hash"])) {
        $data["password_hash"] = password_hash((string) $data["password_hash"], PASSWORD_BCRYPT);
    }

    $colNames = implode(",", array_map(fn($k) => "`$k`", array_keys($data)));
    $placeholders = implode(",", array_fill(0, count($data), "?"));
    $stmt = $db->prepare("INSERT INTO `$table` ($colNames) VALUES ($placeholders)");
    $stmt->execute(array_values($data));

    if (function_exists('sp_log'))
        sp_log("DB Insert: $table", "db_insert");
    return true;
}

function handleUpdate(PDO $db): bool
{
    $table = preg_replace("/[^a-zA-Z0-9_]/", "", $_POST["table"] ?? "");
    $id = $_POST["id"] ?? "";
    $col = preg_replace("/[^a-zA-Z0-9_]/", "", $_POST["col"] ?? "");
    $val = $_POST["val"] ?? "";

    if (!$table || !$id || !$col)
        throw new Exception("REQUIRED_PARAMS_MISSING");

    if ($table === "users" && $col === "password_hash") {
        $val = password_hash((string) $val, PASSWORD_BCRYPT);
    }

    $pk = get_primary_key_agnostic($db, $table);
    $stmt = $db->prepare("UPDATE `$table` SET `$col`=? WHERE `$pk`=?");
    $stmt->execute([$val, $id]);

    if (function_exists('sp_log'))
        sp_log("DB Update: $table ($id)", "db_update");
    return true;
}

function handleDelete(PDO $db): bool
{
    $table = preg_replace("/[^a-zA-Z0-9_]/", "", $_POST["table"] ?? "");
    $id = $_POST["id"] ?? "";
    if (!$table || !$id)
        throw new Exception("REQUIRED_PARAMS_MISSING");

    $pk = get_primary_key_agnostic($db, $table);
    if ($table === "users" && (int) $id === 1)
        throw new Exception("CANNOT_DELETE_MAIN_ADMIN");

    $stmt = $db->prepare("DELETE FROM `$table` WHERE `$pk`=?");
    $stmt->execute([$id]);

    if (function_exists('sp_log'))
        sp_log("DB Delete: $table ($id)", "db_delete");
    return true;
}

function handleSqlExec(PDO $db, bool $is_admin): array
{
    $sql = trim($_POST["sql"] ?? "");
    if (!$sql)
        throw new Exception("SQL_EMPTY");

    $isSelect = preg_match('/^(select|show|describe|pragma|with)\b/i', $sql);
    if (!$is_admin && !$isSelect)
        throw new Exception("UNAUTHORIZED_SQL_EXECUTION");

    if ($isSelect) {
        return ["type" => "select", "data" => $db->query($sql)->fetchAll(PDO::FETCH_ASSOC)];
    } else {
        $affected = $db->exec($sql);
        if (function_exists('sp_log'))
            sp_log("Manual SQL Executed", "db_sql_exec", null, $sql);
        return ["type" => "exec", "affected" => $affected];
    }
}

function handleExportSql(PDO $db): array
{
    return ["sql" => generate_agnostic_dump($db)];
}

function handleImportSql(PDO $db, bool $is_admin): bool
{
    if (!$is_admin)
        throw new Exception("UNAUTHORIZED");
    $sql = trim($_POST["sql"] ?? "");
    if (!$sql)
        throw new Exception("SQL_EMPTY");

    $sql = preg_replace('/\bBEGIN\b.*?;|COMMIT;|ROLLBACK;/i', '', $sql);
    $db->exec($sql);
    if (function_exists('sp_log'))
        sp_log("SQL Imported (Admin Console)", "db_import");
    return true;
}

function handleImportFile(PDO $db, bool $is_admin): bool
{
    if (!$is_admin)
        throw new Exception("UNAUTHORIZED");
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("FILE_UPLOAD_ERROR");
    }

    $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
    $sql = preg_replace('/\bBEGIN\b.*?;|COMMIT;|ROLLBACK;/i', '', $sql);

    $db->beginTransaction();
    try {
        $db->exec($sql);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    if (function_exists('sp_log'))
        sp_log("SQL File Imported", "db_import_file");
    return true;
}

/* ======================================================
   SUPPORT HELPERS
   ====================================================== */

function get_primary_key_agnostic(PDO $db, string $table): string
{
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    try {
        if ($driver === 'sqlite') {
            $cols = $db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c)
                if ($c['pk'])
                    return $c['name'];
        } else {
            $cols = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c)
                if ($c['Key'] === 'PRI')
                    return $c['Field'];
        }
    } catch (Throwable) {
    }
    return 'id';
}

function generate_agnostic_dump(PDO $db): string
{
    $dump = "-- SpeedPage Agnostic Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $create = $db->query("SELECT sql FROM sqlite_master WHERE name='$t'")->fetchColumn();
            $dump .= "$create;\n\n";
            $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $vals = implode(",", array_map(fn($v) => $v === null ? "NULL" : "'" . str_replace("'", "''", (string) $v) . "'", array_values($r)));
                $dump .= "INSERT INTO `$t` VALUES ($vals);\n";
            }
            $dump .= "\n";
        }
    } else {
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $create = $db->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
            $dump .= $create['Create Table'] . ";\n\n";
            $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $vals = implode(",", array_map(fn($v) => $v === null ? "NULL" : "'" . str_replace("'", "\\'", (string) $v) . "'", array_values($r)));
                $dump .= "INSERT INTO `$t` VALUES ($vals);\n";
            }
            $dump .= "\n";
        }
    }
    return $dump;
}
