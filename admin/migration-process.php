<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db_switch.php';

// Increase limits for migration
ini_set('memory_limit', '512M');
set_time_limit(0);

header('Content-Type: application/json; charset=utf-8');

// Check Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => __('access_denied')]);
    exit;
}

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

function getSourceDB(): DB_Switch
{
    return new DB_Switch('sqlite', ['path' => DB_PATH]);
}

function getTargetDB(): DB_Switch
{
    if (!isset($_SESSION['migration_target'])) {
        throw new Exception(__('target_db_session_not_found') ?: "Target DB session not found.");
    }
    return new DB_Switch($_SESSION['migration_target']['type'], $_SESSION['migration_target']['config']);
}

try {
    /* ---------------- STEP 1: TEST CONNECTION & SAVE SETUP ---------------- */
    if ($action === 'connect') {
        $type = $_POST['type'] ?? 'mysql';
        $host = $_POST['host'] ?? 'localhost';
        $name = $_POST['name'] ?? '';
        $user = $_POST['user'] ?? 'root';
        $pass = $_POST['pass'] ?? '';
        $port = (int) ($_POST['port'] ?? 3306);

        $config = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ];

        $testDB = new DB_Switch($type, $config);
        $testDB->testConnection();

        // Save to session
        $_SESSION['migration_target'] = [
            'type' => $type,
            'config' => $config
        ];

        echo json_encode(["status" => "success", "message" => "Connection successful! Saved to session."]);

        if (function_exists('sp_log'))
            sp_log("Migration connection test: $type @ $host", "migration_test");
    }

    /* ---------------- STEP 2: PREPARE SCHEMA ---------------- */ elseif ($action === 'prepare_schema') {

        $source = getSourceDB();
        $target = getTargetDB();

        $tables = $source->getTables();
        $logs = [];

        foreach ($tables as $table) {
            // Get columns from SQLite
            $cols = $source->pdo->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);

            $mysqlCols = [];
            $primaryKeys = [];
            $indexes = []; // Future improvement: indexes

            foreach ($cols as $col) {
                $cName = $col['name'];
                $cType = strtoupper($col['type']);
                $notNull = $col['notnull'] ? 'NOT NULL' : 'NULL';
                $default = $col['dflt_value'] !== null ? "DEFAULT {$col['dflt_value']}" : '';

                // --- Type Conversion Logic ---
                $newType = 'TEXT';
                $extra = '';

                if (strpos($cType, 'INT') !== false) {
                    $newType = 'INT';
                    if ($col['pk']) {
                        $extra = 'AUTO_INCREMENT';
                        $primaryKeys[] = "`$cName`";
                    }
                } elseif (strpos($cType, 'CHAR') !== false || strpos($cType, 'TEXT') !== false) {
                    // Optimized types for indexing
                    if (in_array($cName, ['key', 'slug', 'name', 'type', 'setting_key', 'theme_name'])) {
                        $newType = 'VARCHAR(255)';
                    } else {
                        $newType = 'LONGTEXT';
                    }
                } elseif (strpos($cType, 'BLOB') !== false) {
                    $newType = 'LONGBLOB';
                }

                // Handle 'id' specifically if not caught
                if ($cName === 'id' && $col['pk']) {
                    $newType = 'INT';
                    $extra = 'AUTO_INCREMENT';
                    if (!in_array("`$cName`", $primaryKeys))
                        $primaryKeys[] = "`$cName`";
                }

                // Fix for settings table key
                if ($table === 'settings' && $cName === 'key') {
                    if (!in_array("`$cName`", $primaryKeys))
                        $primaryKeys[] = "`$cName`";
                    $newType = 'VARCHAR(191)'; // UTF8MB4 limit safety
                }

                $mysqlCols[] = "`$cName` $newType $notNull $extra $default";
            }

            $createSQL = "CREATE TABLE IF NOT EXISTS `$table` (\n" . implode(",\n", $mysqlCols);
            if (!empty($primaryKeys)) {
                $createSQL .= ",\nPRIMARY KEY (" . implode(',', $primaryKeys) . ")";
            }
            $createSQL .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

            // Execute on Target
            $target->pdo->exec($createSQL);
            $logs[] = "Table checked/created: $table";
        }

        if (function_exists('sp_log'))
            sp_log("Migration schema prepared", "migration_schema");
        echo json_encode(["status" => "success", "logs" => $logs, "tables" => $tables]);
    }

    /* ---------------- STEP 3: MIGRATE DATA (CHUNKED) ---------------- */ elseif ($action === 'migrate_data') {
        $table = $_POST['table'] ?? '';
        $offset = (int) ($_POST['offset'] ?? 0);
        $limit = 1000;

        if (!$table)
            throw new Exception("Table name missing.");

        $source = getSourceDB();
        $target = getTargetDB();

        // Count total for progress (only on first call)
        $total = 0;
        if ($offset === 0) {
            $total = (int) $source->pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            // Truncate logic removed for safety/sync capabilities
        }

        // Fetch Chunk
        $stmt = $source->pdo->prepare("SELECT * FROM `$table` LIMIT :lim OFFSET :off");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $cols = array_keys($rows[0]);
            $colNames = implode(',', array_map(fn($c) => "`$c`", $cols));
            $placeholders = implode(',', array_fill(0, count($cols), '?'));

            // Use INSERT IGNORE/ON DUPLICATE KEY UPDATE might be better but risky without unique keys defined correctly
            // INSERT IGNORE is safer for migration to avoid duplicate key errors halting process
            $sql = "INSERT IGNORE INTO `$table` ($colNames) VALUES ($placeholders)";

            $target->pdo->beginTransaction();
            try {
                $stmtInsert = $target->pdo->prepare($sql);
                foreach ($rows as $row) {
                    $stmtInsert->execute(array_values($row));
                }
                $target->pdo->commit();
            } catch (Throwable $e) {
                $target->pdo->rollBack();
                throw $e;
            }
        }

        $nextOffset = $offset + count($rows);
        $finished = count($rows) < $limit;

        if ($finished && function_exists('sp_log')) {
            // Log per table finish? Maybe too verbose. 
        }

        echo json_encode([
            "status" => "success",
            "moved" => count($rows),
            "next_offset" => $nextOffset,
            "finished" => $finished,
            "total" => $total
        ]);
    }

    /* ---------------- STEP 4: FINALIZE ---------------- */ elseif ($action === 'finalize') {
        if (!isset($_SESSION['migration_target']))
            throw new Exception("Configuration missing.");

        $conf = $_SESSION['migration_target'];

        // Backup db.php
        $dbFile = __DIR__ . '/db.php';
        if (file_exists($dbFile)) {
            copy($dbFile, $dbFile . '.bak');
        }

        // Create new db.php content
        $newContent = "<?php\n";
        $newContent .= "require_once __DIR__ . '/../settings.php';\n\n";
        $newContent .= "// Generator: Migration Wizard\n";

        if ($conf['type'] === 'mysql') {
            $host = $conf['config']['host'];
            $name = $conf['config']['name'];
            $user = $conf['config']['user'];
            $pass = $conf['config']['pass'];
            $port = $conf['config']['port'];

            $newContent .= "\$dsn = \"mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4\";\n";
            $newContent .= "\$user = \"$user\";\n";
            $newContent .= "\$pass = \"$pass\";\n";
            $newContent .= "\n";
            $newContent .= "try {\n";
            $newContent .= "    \$db = new PDO(\$dsn, \$user, \$pass);\n";
            $newContent .= "    \$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
            $newContent .= "    \$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n";
            $newContent .= "} catch (PDOException \$e) {\n";
            $newContent .= "    die(\"Database connection failed: \" . \$e->getMessage());\n";
            $newContent .= "}\n";
        }
        // MySQL is the focus for external DB migration.

        file_put_contents($dbFile, $newContent);

        if (function_exists('sp_log'))
            sp_log("Migration finalized: Switched to " . $conf['type'], "migration_complete");
        echo json_encode(["status" => "success", "message" => "Migration complete! Config updated."]);
    }

    /* ---------------- STEP: ROLLBACK (EMERGENCY) ---------------- */ elseif ($action === 'rollback') {
        $dbFile = __DIR__ . '/db.php';
        $bakFile = $dbFile . '.bak';

        if (file_exists($bakFile)) {
            copy($bakFile, $dbFile);
            if (function_exists('sp_log'))
                sp_log("Migration rollback requested", "migration_rollback");
            echo json_encode(["status" => "success", "message" => "System restored to previous backup (SQLite)."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Backup file missing!"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
    }

} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
