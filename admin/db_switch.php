<?php
declare(strict_types=1);

/**
 * SpeedPage Database Switcher
 * Modernized for PHP 8.3 | Compatible with MySQL, SQLite
 */
class DB_Switch
{
    public readonly PDO $pdo;
    public readonly string $type; // 'sqlite', 'mysql'

    public function __construct(string $type, array $config)
    {
        $this->type = strtolower($type);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $dsn = match ($this->type) {
                'sqlite' => "sqlite:" . ($config['path'] ?? ''),
                'mysql' => sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
                    $config['host'] ?? 'localhost',
                    $config['port'] ?? 3306,
                    $config['name'] ?? ''
                ),
                default => throw new InvalidArgumentException("Unsupported database type: $this->type"),
            };

            // SQLite specific connection optimizations
            if ($this->type === 'sqlite') {
                $options[PDO::ATTR_TIMEOUT] = 5;
            }

            $user = $config['user'] ?? null;
            $pass = $config['pass'] ?? null;

            $this->pdo = new PDO($dsn, $user, $pass, $options);

            // Post-connection optimizations
            if ($this->type === 'sqlite') {
                $this->pdo->exec("PRAGMA journal_mode = WAL;");
                $this->pdo->exec("PRAGMA busy_timeout = 5000;");
                $this->pdo->exec("PRAGMA foreign_keys = ON;");
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Database connection error ($this->type): " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Test the connection
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            return (bool) $this->pdo->query("SELECT 1");
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get list of tables (Agnostic)
     * @return array
     */
    public function getTables(): array
    {
        try {
            return match ($this->type) {
                'sqlite' => $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN),
                'mysql' => $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN),
                default => [],
            };
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Drop table safely (Agnostic)
     * @param string $table
     * @return void
     */
    public function dropTable(string $table): void
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (!$table)
            return;

        try {
            if ($this->type === 'mysql') {
                $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0; DROP TABLE IF EXISTS `$table`; SET FOREIGN_KEY_CHECKS = 1;");
            } else {
                $this->pdo->exec("DROP TABLE IF EXISTS `$table` ");
            }
        } catch (Throwable) {
            // Silently fail or handle as needed
        }
    }
}
