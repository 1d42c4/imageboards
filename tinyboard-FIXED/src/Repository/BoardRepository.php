<?php

declare(strict_types=1);

namespace Newboard\Repository;

use Newboard\Database;

final class BoardRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM boards ORDER BY uri COLLATE NOCASE');
    }

    public function find(string $uri): ?array
    {
        return $this->db->fetchOne('SELECT * FROM boards WHERE uri = ? COLLATE NOCASE', [$uri]);
    }

    public function create(string $uri, string $title, string $subtitle = ''): void
    {
        $this->db->query(
            'INSERT INTO boards (uri, title, subtitle, created_at) VALUES (?, ?, ?, ?)',
            [$uri, $title, $subtitle, time()]
        );
    }

    /**
     * @param array{
     *   title: string,
     *   subtitle: string,
     *   require_approval: int,
     *   require_password: int,
     *   password_hash: ?string,
     *   force_image_op: int
     * } $data
     */
    public function update(string $uri, array $data): void
    {
        $this->db->query(
            'UPDATE boards SET
                title = ?, subtitle = ?,
                require_approval = ?, require_password = ?,
                password_hash = ?, force_image_op = ?
             WHERE uri = ? COLLATE NOCASE',
            [
                $data['title'],
                $data['subtitle'],
                $data['require_approval'],
                $data['require_password'],
                $data['password_hash'],
                $data['force_image_op'],
                $uri,
            ]
        );
    }

    public function delete(string $uri): void
    {
        $this->db->query('DELETE FROM reports WHERE board_uri = ? COLLATE NOCASE', [$uri]);
        $this->db->query('DELETE FROM cites WHERE board_uri = ? COLLATE NOCASE OR target_board_uri = ? COLLATE NOCASE', [$uri, $uri]);
        $this->db->query('DELETE FROM posts WHERE board_uri = ? COLLATE NOCASE', [$uri]);
        $this->db->query('DELETE FROM boards WHERE uri = ? COLLATE NOCASE', [$uri]);
    }
}
