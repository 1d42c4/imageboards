<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Db
{
    public \PDO $pdo;
    public function __construct(string $path)
    {
        $this->pdo = new \PDO('sqlite:' . $path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=10000; PRAGMA journal_mode=WAL;');
    }
    /**
     * @param array<string|int, mixed> $params */
    #[\NoDiscard]
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, match (true) {
                is_int($value) => \PDO::PARAM_INT, $value === null => \PDO::PARAM_NULL, default => \PDO::PARAM_STR
            });
        }
        $statement->execute();
        return $statement;
    }
    /**
     * @param array<string|int, mixed> $params */
    public function execute(string $sql, array $params = []): void
    {
        $statement = $this->query($sql, $params);
        $statement->closeCursor();
    }
    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }
    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return array_values($this->query($sql, $params)->fetchAll());
    }
    /**
     * @template T
     * @param callable(): T $work
     * @return T */
    public function transaction(callable $work): mixed
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $work();
            $this->pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
    }
}
