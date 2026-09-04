<?php

declare(strict_types=1);

namespace Chessboard;

use Chessboard\Repository\BoardRepository;
use Chessboard\Repository\ModerationRepository;
use PDO;
use RuntimeException;
use Throwable;

final readonly class Installer
{
    public const STATUS_SETUP = 'setup';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_REPAIR = 'repair';

    private const REQUIRED_EXTENSIONS = [
        'pdo_sqlite',
        'mbstring',
        'fileinfo',
        'gd',
        'session',
        'json',
    ];

    public function __construct(private Config $config)
    {
    }

    public function requirements(): array
    {
        $requirements = [[
            'label' => 'PHP 8.5 or newer',
            'ok' => PHP_VERSION_ID >= 80500,
            'detail' => PHP_VERSION,
        ]];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $requirements[] = [
                'label' => $extension,
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'available' : 'missing',
            ];
        }

        $varDirectory = dirname($this->config->requireString('database_path'));
        $writable = is_dir($varDirectory)
            ? is_writable($varDirectory)
            : is_writable(dirname($varDirectory));
        $requirements[] = [
            'label' => 'Writable data directory',
            'ok' => $writable,
            'detail' => $writable ? 'available' : 'not writable',
        ];

        return $requirements;
    }

    public function missingRequirements(): array
    {
        return array_values(array_filter(
            $this->requirements(),
            static fn (array $requirement): bool => !$requirement['ok'],
        ));
    }

    public function status(): string
    {
        $databasePath = $this->config->requireString('database_path');
        if (!is_file($databasePath)) {
            return self::STATUS_SETUP;
        }

        $moderatorCount = $this->moderatorCount();
        if ($moderatorCount === null) {
            return self::STATUS_REPAIR;
        }
        if ($moderatorCount === 0) {
            return self::STATUS_SETUP;
        }

        return self::STATUS_INSTALLED;
    }

    public function install(string $username, string $password): array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username)) {
            throw new RuntimeException(
                'The administrator username must be 3–32 letters, numbers, underscores, or hyphens.',
            );
        }
        $passwordLength = strlen($password);
        if ($passwordLength < 12 || $passwordLength > 128) {
            throw new RuntimeException('The administrator password must contain 12–128 characters.');
        }

        $missing = $this->missingRequirements();
        if ($missing !== []) {
            throw new RuntimeException(
                'The server is missing: ' . implode(', ', array_column($missing, 'label')),
            );
        }

        $databasePath = $this->config->requireString('database_path');
        $this->ensureDirectory(dirname($databasePath));
        $lockPath = dirname($databasePath) . '/.setup.lock';
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Unable to lock the setup process.');
        }

        try {
            $status = $this->status();
            if ($status === self::STATUS_INSTALLED) {
                throw new RuntimeException('Chessboard Lite is already installed.');
            }
            if ($status === self::STATUS_REPAIR) {
                throw new RuntimeException(
                    'The installation is incomplete or damaged. Run php bin/doctor.php for details.',
                );
            }

            $this->ensureDirectory($this->config->requireString('storage_path') . '/original');
            $this->ensureDirectory($this->config->requireString('storage_path') . '/thumb');
            $this->ensureDirectory(dirname($this->config->requireString('log_path')));

            $database = new Database($this->config, true);
            $migrations = $database->migrate();
            $boards = new BoardRepository($database);
            $moderation = new ModerationRepository($database);

            $boardCreated = false;
            if (!$boards->exists('chess')) {
                $boards->create(
                    'chess',
                    'Chess',
                    'Chess discussion, game analysis, puzzles, openings, and tournament talk.',
                );
                $boardCreated = true;
            }

            if ($this->moderatorCount($database->pdo()) !== 0) {
                throw new RuntimeException('An administrator already exists; browser setup is disabled.');
            }

            $hash = password_hash(
                $password,
                defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT,
            );
            if ($hash === false) {
                throw new RuntimeException('PHP was unable to hash the administrator password.');
            }
            $moderation->createModerator($username, $hash);

            return [
                'username' => $username,
                'migrations' => $migrations,
                'board_created' => $boardCreated,
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function moderatorCount(?PDO $pdo = null): ?int
    {
        try {
            if ($pdo === null) {
                $pdo = new PDO(
                    'sqlite:' . $this->config->requireString('database_path'),
                    null,
                    null,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                );
            }
            $exists = $pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'moderators' LIMIT 1"
            )->fetchColumn();
            if ($exists === false) {
                return 0;
            }

            return (int) $pdo->query('SELECT COUNT(*) FROM moderators')->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }
}
