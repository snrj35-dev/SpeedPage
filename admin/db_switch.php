<?php

class DB_Switch
{
    public $pdo;
    public $type; // 'sqlite', 'mysql', 'pgsql'

    public function __construct($type, $config)
    {
        $this->type = strtolower($type);

        $dsn = "";
        $user = $config['user'] ?? null;
        $pass = $config['pass'] ?? null;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        try {
            if ($this->type === 'sqlite') {
                $dsn = "sqlite:" . $config['path'];
                // SQLite specific optimizations
                $options[PDO::ATTR_TIMEOUT] = 5;
            } elseif ($this->type === 'mysql') {
                $host = $config['host'] ?? 'localhost';
                $dbName = $config['name'] ?? '';
                $port = $config['port'] ?? 3306;
                $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
            } elseif ($this->type === 'pgsql') {
                $host = $config['host'] ?? 'localhost';
                $dbName = $config['name'] ?? '';
                $port = $config['port'] ?? 5432;
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
            } else {
                throw new Exception("Unsupported database type: $this->type");
            }

            $this->pdo = new PDO($dsn, $user, $pass, $options);

            if ($this->type === 'sqlite') {
                $this->pdo->exec("PRAGMA journal_mode = WAL;");
                $this->pdo->exec("PRAGMA busy_timeout = 5000;");
            }

        } catch (PDOException $e) {
            throw new Exception("Connection error ($this->type): " . $e->getMessage());
        }
    }

    public function testConnection()
    {
        return $this->pdo->query("SELECT 1")->fetch();
    }

    public function getTables()
    {
        if ($this->type === 'sqlite') {
            return $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($this->type === 'mysql') {
            return $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($this->type === 'pgsql') {
            return $this->pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'")->fetchAll(PDO::FETCH_COLUMN);
        }
        return [];
    }

    public function dropTable($table)
    {
        // Safe drop for overwrite scenarios
        // Filter table name to alphanumeric only to prevent injection
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($this->type === 'mysql') {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0; DROP TABLE IF EXISTS `$table`; SET FOREIGN_KEY_CHECKS = 1;");
        } else {
            $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
    }
}
