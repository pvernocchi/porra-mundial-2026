<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Team
{
    public int $id = 0;
    public string $name = '';
    public int $pot = 0;

    public function __construct(private Database $db) {}

    public function find(int $id): ?self
    {
        $row = $this->db->fetch('SELECT * FROM {prefix:teams} WHERE id = :id', ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    /** @return array<int, self> */
    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM {prefix:teams} ORDER BY pot, name');
        return array_map(fn(array $r) => (new self($this->db))->hydrate($r), $rows);
    }

    /** @return array<int, self> */
    public function byPot(int $pot): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM {prefix:teams} WHERE pot = :pot ORDER BY name',
            ['pot' => $pot]
        );
        return array_map(fn(array $r) => (new self($this->db))->hydrate($r), $rows);
    }

    /**
     * @return array<int, array<int, self>> Keyed by pot number
     */
    public function allGroupedByPot(): array
    {
        $teams = $this->all();
        $grouped = [];
        foreach ($teams as $t) {
            $grouped[$t->pot][] = $t;
        }
        ksort($grouped);
        return $grouped;
    }

    /** @param array<string, mixed> $row */
    public function hydrate(array $row): self
    {
        $t = new self($this->db);
        $t->id   = (int)($row['id'] ?? 0);
        $t->name = (string)($row['name'] ?? '');
        $t->pot  = (int)($row['pot'] ?? 0);
        return $t;
    }
}
