<?php

declare(strict_types=1);

namespace Chessboard;

use Closure;
use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    private PDO $pdo;

    public function __construct(
        private readonly Config $config,
        bool $allowCreate = false,
    ) {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('The PDO_SQLite PHP extension is required.');
        }

        $path = $this->config->requireString('database_path');
        if (!$allowCreate && !is_file($path)) {
            throw new RuntimeException(
                'The database has not been set up. Open setup.php or run: php bin/install.php',
            );
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the database directory.');
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function immediate(Closure $callback): mixed
    {
        $this->pdo->exec('BEGIN IMMEDIATE');

        try {
            $result = $callback($this->pdo);
            $this->pdo->exec('COMMIT');

            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->exec('ROLLBACK');
            }

            throw $error;
        }
    }

    public function migrate(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                applied_at INTEGER NOT NULL
            )'
        );

        $directory = $this->config->rootPath() . '/migrations';
        $files = array_merge(
            glob($directory . '/*.sql') ?: [],
            glob($directory . '/*.php') ?: [],
        );
        sort($files, SORT_STRING);
        $applied = [];

        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $query = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
            $query->execute(['version' => $version]);
            if ($query->fetchColumn()) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($extension === 'sql') {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException('Unable to read migration: ' . basename($file));
                }

                $statements = array_filter(
                    array_map('trim', explode('-- migrate:split', $sql)),
                    static fn (string $statement): bool => $statement !== '',
                );
                $migration = static function (PDO $pdo) use ($statements): void {
                    foreach ($statements as $statement) {
                        $pdo->exec($statement);
                    }
                };
            } elseif ($extension === 'php') {
                $migration = require $file;
                if (!is_callable($migration)) {
                    throw new RuntimeException('PHP migration must return a callable: ' . basename($file));
                }
            } else {
                continue;
            }

            $this->immediate(function (PDO $pdo) use ($migration, $version): void {
                $migration($pdo);

                $query = $pdo->prepare(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)'
                );
                $query->execute(['version' => $version, 'applied_at' => time()]);
            });

            $applied[] = $version;
        }

        return $applied;
    }
}
