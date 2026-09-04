<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = static function (string $table) use ($pdo): array {
        $query = $pdo->query("PRAGMA table_info('" . str_replace("'", "''", $table) . "')");

        return array_column($query->fetchAll(), 'name');
    };

    $pdo->exec('DROP INDEX IF EXISTS posts_ip_hash');
    $postColumns = $columns('posts');
    if (in_array('password_hash', $postColumns, true)) {
        $pdo->exec('ALTER TABLE posts DROP COLUMN password_hash');
    }
    if (in_array('ip_hash', $postColumns, true)) {
        $pdo->exec('ALTER TABLE posts DROP COLUMN ip_hash');
    }

    $reportColumns = $columns('reports');
    if (in_array('reporter_ip_hash', $reportColumns, true)) {
        $pdo->exec('ALTER TABLE reports DROP COLUMN reporter_ip_hash');
    }

    $pdo->exec('DROP INDEX IF EXISTS bans_ip_active');
    $pdo->exec('DROP TABLE IF EXISTS bans');
    $pdo->exec('DROP TABLE IF EXISTS rate_limits');
};
