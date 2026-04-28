<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. Handles MySQL/MariaDB (production) and SQLite
 * (testing, and the optional preflight "test connection" path in the
 * installer).
 *
 * Adds a configurable table prefix (`pm_` by default) so multiple
 * applications can share a single database on a budget host.
 */
final class Database
{
    private PDO $pdo;
    private string $prefix;
    private string $driver;

    public function __construct(PDO $pdo, string $prefix = '', string $driver = 'mysql')
    {
        $this->pdo    = $pdo;
        $this->prefix = $prefix;
        $this->driver = $driver;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function fromConfig(array $cfg): self
    {
        $driver = (string)($cfg['driver'] ?? 'mysql');
        $prefix = (string)($cfg['prefix'] ?? '');

        if ($driver === 'sqlite') {
            $path = (string)($cfg['database'] ?? ':memory:');
            $pdo  = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return new self($pdo, $prefix, 'sqlite');
        }

        $host    = (string)($cfg['host'] ?? 'localhost');
        $port    = (int)($cfg['port'] ?? 3306);
        $name    = (string)($cfg['database'] ?? '');
        $user    = (string)($cfg['username'] ?? '');
        $pass    = (string)($cfg['password'] ?? '');
        $charset = (string)($cfg['charset'] ?? 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return new self($pdo, $prefix, 'mysql');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /**
     * Substitute {prefix:tablename} tokens in raw SQL.
     */
    public function rewrite(string $sql): string
    {
        return (string)preg_replace_callback(
            '/\{prefix:([a-zA-Z0-9_]+)\}/',
            fn(array $m) => $this->prefix . $m[1],
            $sql
        );
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($this->rewrite($sql));
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): string
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table($table),
            implode(',', $cols),
            implode(',', $placeholders)
        );
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v, $this->paramType($v));
        }
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = [];
        foreach (array_keys($data) as $c) {
            $set[] = $c . ' = :set_' . $c;
        }
        $cond = [];
        foreach (array_keys($where) as $c) {
            $cond[] = $c . ' = :where_' . $c;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->table($table),
            implode(', ', $set),
            implode(' AND ', $cond)
        );
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':set_' . $k, $v, $this->paramType($v));
        }
        foreach ($where as $k => $v) {
            $stmt->bindValue(':where_' . $k, $v, $this->paramType($v));
        }
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function paramType(mixed $v): int
    {
        if (is_int($v)) {
            return PDO::PARAM_INT;
        }
        if (is_bool($v)) {
            return PDO::PARAM_BOOL;
        }
        if ($v === null) {
            return PDO::PARAM_NULL;
        }
        return PDO::PARAM_STR;
    }
}
