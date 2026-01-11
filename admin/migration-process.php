<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db_switch.php';

// Increase limits for migration
ini_set('memory_limit', '512M');
set_time_limit(0);

header('Content-Type: application/json');

// Check Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$action = $_POST['action'] ?? '';

// Helper to get Source DB (SQLite currently)
function getSourceDB()
{
    return new DB_Switch('sqlite', ['path' => DB_PATH]);
}

// Helper to get Target DB from Session
function getTargetDB()
{
    if (!isset($_SESSION['migration_target'])) {
        throw new Exception("Hedef veritabanı oturumu bulunamadı.");
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
        $port = $_POST['port'] ?? ($type === 'pgsql' ? 5432 : 3306);

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

        echo json_encode(["status" => "success", "message" => "Bağlantı başarılı! Session'a kaydedildi."]);
    }

    /* ---------------- STEP 2: PREPARE SCHEMA ---------------- */ elseif ($action === 'prepare_schema') {

        $source = getSourceDB();
        $target = getTargetDB(); // This checks session

        $tables = $source->getTables();
        $logs = [];

        foreach ($tables as $table) {
            // Get columns from SQLite
            $cols = $source->pdo->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);

            $mysqlCols = [];
            $primaryKeys = [];

            foreach ($cols as $col) {
                $cName = $col['name'];
                $cType = strtoupper($col['type']);
                $notNull = $col['notnull'] ? 'NOT NULL' : 'NULL';
                $default = $col['dflt_value'] !== null ? "DEFAULT {$col['dflt_value']}" : '';

                // --- Type Conversion Logic ---
                $newType = 'TEXT'; // Fallback
                $extra = '';

                if (strpos($cType, 'INT') !== false) {
                    $newType = 'INT';
                    if ($col['pk']) {
                        $extra = 'AUTO_INCREMENT';
                        $primaryKeys[] = "`$cName`";
                    }
                } elseif (strpos($cType, 'CHAR') !== false || strpos($cType, 'TEXT') !== false) {
                    // For keys/slugs, use VARCHAR to allow indexing
                    if (in_array($cName, ['key', 'slug', 'name'])) {
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

                // phpMyAdmin / MySQL Primary Key fix for settings table
                if ($table === 'settings' && $cName === 'key') {
                    if (!in_array("`$cName`", $primaryKeys)) {
                        $primaryKeys[] = "`$cName`";
                    }
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
            $logs[] = "Tablo oluşturuldu/kontrol edildi: $table";
        }

        echo json_encode(["status" => "success", "logs" => $logs, "tables" => $tables]);
    }

    /* ---------------- STEP 3: MIGRATE DATA (CHUNKED) ---------------- */ elseif ($action === 'migrate_data') {
        $table = $_POST['table'] ?? '';
        $offset = (int) ($_POST['offset'] ?? 0);
        $limit = 1000;

        if (!$table) {
            throw new Exception("Tablo adı belirtilmedi.");
        }

        $source = getSourceDB();
        $target = getTargetDB();

        // Count total for progress (only on first call)
        $total = 0;
        if ($offset === 0) {
            $total = $source->pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            // Truncate target table to avoid duplicates on retry
            // WARNING: Only truncate if offset is 0
            // Truncate target table removed for Sync capability (to avoid data loss if just syncing)
            // $target->pdo->exec("TRUNCATE TABLE `$table`");
        }

        // Fetch Chunk
        $rows = $source->pdo->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $cols = array_keys($rows[0]);
            $colNames = implode(',', array_map(fn($c) => "`$c`", $cols));
            $placeholders = implode(',', array_fill(0, count($cols), '?'));

            // Use INSERT IGNORE for safer sync
            $sql = "INSERT IGNORE INTO `$table` ($colNames) VALUES ($placeholders)";

            $target->pdo->beginTransaction();
            try {
                $stmt = $target->pdo->prepare($sql);
                foreach ($rows as $row) {
                    $stmt->execute(array_values($row));
                }
                $target->pdo->commit();
            } catch (Exception $e) {
                $target->pdo->rollBack();
                throw $e;
            }
        }

        $nextOffset = $offset + count($rows);
        $finished = count($rows) < $limit;

        echo json_encode([
            "status" => "success",
            "moved" => count($rows),
            "next_offset" => $nextOffset,
            "finished" => $finished,
            "total" => $total
        ]);
    }

    /* ---------------- STEP 4: FINALIZE ---------------- */ elseif ($action === 'finalize') {
        // Here we update db.php to point to the new MySQL DB
        if (!isset($_SESSION['migration_target'])) {
            throw new Exception("Hedef yapılandırma bulunamadı.");
        }

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

        file_put_contents($dbFile, $newContent);

        echo json_encode(["status" => "success", "message" => "Migration tamamlandı! Config güncellendi."]);
    }

    /* ---------------- STEP: ROLLBACK (EMERGENCY) ---------------- */ elseif ($action === 'rollback') {
        // Restore db.php.bak
        $dbFile = __DIR__ . '/db.php';
        $bakFile = $dbFile . '.bak';

        if (file_exists($bakFile)) {
            copy($bakFile, $dbFile);
            echo json_encode(["status" => "success", "message" => "Sistem eski haline (SQLite) geri döndürüldü."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Yedek dosyası (db.php.bak) bulunamadı!"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Geçersiz işlem."]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
