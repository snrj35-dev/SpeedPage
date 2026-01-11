<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

// Disable error output (JSON format bozulmasın)
ini_set('display_errors', 0);
error_reporting(0);

header("Content-Type: application/json; charset=utf-8");

// Read JSON body
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (is_array($input)) {
    $_POST = array_merge($_POST, $input);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_POST)) {
    // Some logic might use GET action with POST data. 
    // Generally check if we have any POST data or if input indicates POST intent.
    // verislem.php seems to purely rely on action param from GET often, but Data in POST.
    // Let's enforce CSRF if there is POST data.

    // Note: 'rows' action is GET, 'tables' is GET. 'insert', 'update', 'delete' use POST data.
    if (in_array($_GET["action"] ?? "", ["insert", "update", "delete", "sql", "import_sql", "import_file"])) {
        if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
            echo json_encode(["ok" => false, "error" => "CSRF verification failed"]);
            exit;
        }
    }
}

$action = $_GET["action"] ?? "";

// Determine if current user is admin (auth.php sets session role)
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Sanitize table name
function clean_table($t)
{
    return preg_replace("/[^a-zA-Z0-9_]/", "", $t);
}

function get_primary_key($db, $table)
{
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if ($c['pk'])
                return $c['name'];
        }
    } else {
        $cols = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if ($c['Key'] === 'PRI')
                return $c['Field'];
        }
    }
    return 'id'; // Fallback
}

/* ---------------- TABLE LIST ---------------- */
if ($action === "tables") {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        echo json_encode(
            $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(PDO::FETCH_COLUMN)
        );
    } else {
        echo json_encode(
            $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN)
        );
    }
    exit;
}

/* ---------------- COLUMN LIST ---------------- */
if ($action === "columns") {
    $t = clean_table($_GET["table"] ?? "");
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        echo json_encode(
            $db->query("PRAGMA table_info(`$t`)")->fetchAll(PDO::FETCH_ASSOC)
        );
    } else {
        // Map MySQL DESCRIBE to SQLite PRAGMA table_info format for frontend compatibility
        $cols = $db->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
        $mapped = array_map(function ($c) {
            return [
                'name' => $c['Field'],
                'type' => $c['Type'],
                'notnull' => ($c['Null'] === 'NO' ? 1 : 0),
                'dflt_value' => $c['Default'],
                'pk' => ($c['Key'] === 'PRI' ? 1 : 0),
                'extra' => $c['Extra'] // e.g. auto_increment
            ];
        }, $cols);
        echo json_encode($mapped);
    }
    exit;
}

/* ---------------- ROW LIST ---------------- */
if ($action === "rows") {
    $t = clean_table($_GET["table"] ?? "");
    echo json_encode(
        $db->query("SELECT * FROM `$t` LIMIT 200")->fetchAll(PDO::FETCH_ASSOC)
    );
    exit;
}

/* ---------------- INSERT ---------------- */
if ($action === "insert") {
    $t = clean_table($_POST["table"] ?? "");

    if (!isset($_POST["data"]) || !is_array($_POST["data"])) {
        echo json_encode(["ok" => false, "error" => "DATA_MISSING", "message_key" => "data_missing"]);
        exit;
    }

    $data = $_POST["data"];

    // Detect PK and Auto-Increment
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $db->query("PRAGMA table_info(`$t`)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            // Integer PK in SQLite is typically auto-increment if not provided
            if ($c['pk'] && stripos($c['type'], 'INT') !== false) {
                if (empty($data[$c['name']]))
                    unset($data[$c['name']]);
            }
        }
    } else {
        $cols = $db->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (stripos($c['Extra'], 'auto_increment') !== false) {
                unset($data[$c['Field']]);
            }
        }
    }

    // Falls back to legacy 'id' unset just in case
    unset($data["id"]);

    // Auto hash password
    if ($t === "users" && isset($data["password_hash"])) {
        $data["password_hash"] = password_hash($data["password_hash"], PASSWORD_BCRYPT);
    }

    $cols = array_keys($data);
    $vals = array_values($data);

    $sql = "INSERT INTO `$t` (" . implode(",", $cols) . ")
            VALUES (" . implode(",", array_fill(0, count($cols), "?")) . ")";

    $db->prepare($sql)->execute($vals);

    echo json_encode(["ok" => true]);
    exit;
}

/* ---------------- UPDATE ---------------- */
if ($action === "update") {
    $t = clean_table($_POST["table"] ?? "");
    $id = $_POST["id"] ?? ""; // Could be string key
    $col = clean_table($_POST["col"] ?? "");
    $val = $_POST["val"] ?? "";

    if ($t === "users" && $col === "password_hash") {
        $val = password_hash($val, PASSWORD_BCRYPT);
    }

    $pk = get_primary_key($db, $t);

    $db->prepare("UPDATE `$t` SET `$col`=? WHERE `$pk`=?")
        ->execute([$val, $id]);

    echo json_encode(["ok" => true]);
    exit;
}

/* ---------------- DELETE ---------------- */
if ($action === "delete") {
    $t = clean_table($_POST["table"] ?? "");
    $id = $_POST["id"] ?? "";

    $pk = get_primary_key($db, $t);

    // Prevent deleting main admin
    if ($t === "users" && ($pk === "id" && $id == 1)) {
        echo json_encode(["ok" => false, "error" => "MAIN_ADMIN_CANNOT_BE_DELETED"]);
        exit;
    }

    $db->prepare("DELETE FROM `$t` WHERE `$pk`=?")->execute([$id]);

    echo json_encode(["ok" => true]);
    exit;
}

/* ---------------- RUN SQL ---------------- */
if ($action === "sql") {
    try {
        $sql = trim($_POST["sql"] ?? "");
        if (!$sql) {
            echo json_encode(["error" => "SQL_EMPTY"]);
            exit;
        }

        $lower = ltrim(strtolower($sql));

        // SELECT veya WITH sorguları için veri döndür
        if (preg_match('/^(select|with)\b/', $lower)) {
            $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["type" => "select", "data" => $res]);
        } else {
            // Admin isen DROP, ALTER, TRUNCATE dahil her şeyi çalıştırabilmelisin
            // Filtreyi tamamen kaldırıyoruz veya genişletiyoruz
            $db->exec($sql);
            echo json_encode(["type" => "exec", "ok" => true]);
        }
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}

/* ---------------- EXPORT SQL ---------------- */
if ($action === "export_sql") {
    $dump = "";
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $t) {
            $row = $db->query("SELECT sql FROM sqlite_master WHERE name='$t'")
                ->fetch(PDO::FETCH_ASSOC);

            if (!empty($row["sql"])) {
                $dump .= $row["sql"] . ";\n\n";
            }

            $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $dump .= "INSERT INTO `$t` VALUES (";
                $dump .= implode(",", array_map(fn($v) => $v === null ? "NULL" : "'" . str_replace("'", "''", $v) . "'", array_values($r)));
                $dump .= ");\n";
            }
            $dump .= "\n";
        }
    } else {
        // Basic MySQL Export
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $create = $db->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
            $dump .= $create['Create Table'] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $dump .= "INSERT INTO `$t` VALUES (";
                $dump .= implode(",", array_map(fn($v) => $v === null ? "NULL" : "'" . str_replace("'", "\'", $v) . "'", array_values($r)));
                $dump .= ");\n";
            }
            $dump .= "\n";
        }
    }

    echo json_encode(["sql" => $dump]);
    exit;
}

/* ---------------- EXPORT SQL AS FILE ---------------- */
if ($action === "export_sql_file") {
    $dump = "";
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $t) {
            $row = $db->query("SELECT sql FROM sqlite_master WHERE name='$t'")
                ->fetch(PDO::FETCH_ASSOC);

            if (!empty($row["sql"])) {
                $dump .= $row["sql"] . ";\n\n";
            }

            $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $dump .= "INSERT INTO `$t` VALUES (";
                $dump .= implode(",", array_map(fn($v) => $v === null ? "NULL" : "'" . str_replace("'", "''", $v) . "'", array_values($r)));
                $dump .= ");\n";
            }
            $dump .= "\n";
        }
    } else {
        // MySQL Export as File
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $create = $db->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
            $dump .= $create['Create Table'] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $dump .= "INSERT INTO `$t` VALUES (";
                $dump .= implode(",", array_map(fn($v) => $v === null ? "NULL" : "'" . str_replace("'", "\'", $v) . "'", array_values($r)));
                $dump .= ");\n";
            }
            $dump .= "\n";
        }
    }

    $filename = 'backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $dump;
    exit;
}

/* ---------------- IMPORT SQL ---------------- */
if ($action === "import_sql") {
    try {
        $sql = trim($_POST["sql"] ?? "");
        if (!$sql) {
            echo json_encode(["ok" => false, "error" => "SQL_EMPTY"]);
            exit;
        }

        // Remove BEGIN/COMMIT
        $sql = preg_replace('/\bBEGIN\b.*?;|COMMIT;|ROLLBACK;/i', '', $sql);

        $db->exec($sql);

        echo json_encode(["ok" => true]);
    } catch (Exception $e) {
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

/* ---------------- IMPORT SQL FILE (multipart upload) ---------------- */
if ($action === 'import_file') {
    try {
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["ok" => false, "error" => "NO_FILE", "message_key" => "no_file"]);
            exit;
        }

        $f = $_FILES['sql_file'];

        $content = file_get_contents($f['tmp_name']);
        if (!$content) {
            echo json_encode(["ok" => false, "error" => "EMPTY_FILE", "message_key" => "empty_file"]);
            exit;
        }

        // Basic safety checks - block dangerous keywords for non-admins
        if (!$is_admin && preg_match('/\b(drop|attach|pragma|sqlite_master|\.read)\b/i', $content)) {
            echo json_encode(["ok" => false, "error" => "FORBIDDEN_KEYWORDS_IN_FILE", "message_key" => "forbidden_keywords_in_file"]);
            exit;
        }

        // Remove BEGIN/COMMIT to avoid nested transactions
        $content = preg_replace('/\bBEGIN\b.*?;|COMMIT;|ROLLBACK;/i', '', $content);

        $db->beginTransaction();
        $db->exec($content);
        $db->commit();

        echo json_encode(["ok" => true]);
    } catch (Exception $e) {
        if ($db->inTransaction())
            $db->rollBack();
        echo json_encode(["ok" => false, "error" => $e->getMessage()]);
    }
    exit;
}

echo json_encode(["error" => "INVALID_ACTION"]);

