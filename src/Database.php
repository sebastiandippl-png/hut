<?php

declare(strict_types=1);

namespace Hut;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    /**
     * Release the current connection. The next call to getInstance() will open a fresh one.
     * Useful to free a connection early (e.g. before long-running non-DB work).
     */
    public static function close(): void
    {
        self::$instance = null;
    }

    private static function connect(): PDO
    {
        $dsn      = $_ENV['DB_DSN']      ?? throw new RuntimeException('DB_DSN not set');
        $user     = $_ENV['DB_USERNAME'] ?? null;
        $password = $_ENV['DB_PASSWORD'] ?? null;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $isSqlite = str_starts_with($dsn, 'sqlite:');
        if ($isSqlite) {
            $dsn = self::prepareSqliteDsn($dsn);
        }

        try {
            $pdo = new PDO($dsn, $user ?: null, $password ?: null, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        if ($isSqlite) {
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $pdo->exec("SET NAMES 'utf8mb4'");
        }

        return $pdo;
    }

    private static function prepareSqliteDsn(string $dsn): string
    {
        $path = substr($dsn, strlen('sqlite:'));
        if ($path === false || $path === '') {
            throw new RuntimeException('Invalid SQLite DSN. Expected sqlite:/absolute/path or sqlite:relative/path');
        }

        if ($path === ':memory:' || str_starts_with($path, 'file:')) {
            return $dsn;
        }

        $isAbsolutePath = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\/]#', $path) === 1;
        if (!$isAbsolutePath) {
            $path = dirname(__DIR__) . '/' . $path;
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Failed to create SQLite directory: ' . $directory);
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('SQLite directory is not writable: ' . $directory);
        }

        return 'sqlite:' . $path;
    }

    /**
     * Run all SQL migration files not yet recorded in the migrations table.
     */
    public static function migrate(string $migrationsDir): void
    {
        $pdo = self::getInstance();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Ensure migrations tracking table exists
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id     INTEGER PRIMARY KEY AUTOINCREMENT,
                    name   VARCHAR(255) NOT NULL UNIQUE,
                    run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name   VARCHAR(255) NOT NULL UNIQUE,
                    run_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $ran = $pdo->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

        foreach (glob($migrationsDir . '/*.sql') as $file) {
            $name = basename($file);
            if (in_array($name, $ran, true)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Failed to read migration file: ' . $file);
            }
            if ($driver !== 'sqlite') {
                $sql = self::normalizeSqlForMysql($sql);
            }
            $pdo->exec($sql);
            $stmt = $pdo->prepare("INSERT INTO migrations (name) VALUES (?)");
            $stmt->execute([$name]);
            echo "Migrated: $name\n";
        }
    }

    private static function normalizeSqlForMysql(string $sql): string
    {
        // Drop SQLite-only PRAGMA statements.
        $sql = preg_replace('/^\s*PRAGMA\s+[^;]+;\s*$/mi', '', $sql) ?? $sql;

        // Convert SQLite AUTOINCREMENT PKs to MySQL-compatible syntax.
        $sql = str_ireplace(
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            $sql
        );

        // Keep externally assigned IDs as integer PK in MySQL.
        $sql = str_ireplace(
            'INTEGER PRIMARY KEY',
            'INT UNSIGNED NOT NULL PRIMARY KEY',
            $sql
        );

        // Keep FK/reference column types compatible with unsigned PKs.
        $sql = preg_replace('/\b([a-zA-Z0-9_]*_id)\s+INTEGER\b/i', '$1 INT UNSIGNED', $sql) ?? $sql;

        // SQLite rename table syntax to MySQL syntax.
        $sql = preg_replace('/ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+RENAME\s+TO\s+([a-zA-Z0-9_]+)\s*;/i', 'RENAME TABLE $1 TO $2;', $sql) ?? $sql;

        // SQLite UPSERT syntax to MySQL UPSERT syntax used in migration 004.
        $sql = preg_replace(
            '/ON\s+CONFLICT\s*\(\s*bgg_id\s*\)\s*DO\s+UPDATE\s+SET\s+thumbnail\s*=\s*excluded\.thumbnail\s*;/i',
            'ON DUPLICATE KEY UPDATE thumbnail = VALUES(thumbnail);',
            $sql
        ) ?? $sql;

        // SQLite cast aliases to MySQL cast aliases.
        $sql = str_ireplace(' AS INTEGER)', ' AS SIGNED)', $sql);
        $sql = str_ireplace(' AS REAL)', ' AS DECIMAL(10,5))', $sql);

        return $sql;
    }
}
