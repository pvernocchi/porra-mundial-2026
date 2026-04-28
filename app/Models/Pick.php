<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Pick
{
    public int $id = 0;
    public int $userId = 0;
    public int $teamId = 0;
    public int $pot = 0;
    public ?string $createdAt = null;

    public function __construct(private Database $db) {}

    /**
     * @return array<int, self> Picks for a user
     */
    public function forUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM {prefix:picks} WHERE user_id = :uid ORDER BY pot',
            ['uid' => $userId]
        );
        return array_map(fn(array $r) => (new self($this->db))->hydrate($r), $rows);
    }

    /**
     * Save all 6 picks for a user in a transaction. Replaces existing picks.
     * @param array<int, int> $teamIdsByPot e.g. [1 => 3, 2 => 12, ...]
     */
    public function saveForUser(int $userId, array $teamIdsByPot): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->run('DELETE FROM {prefix:picks} WHERE user_id = :uid', ['uid' => $userId]);
            $now = gmdate('Y-m-d H:i:s');
            foreach ($teamIdsByPot as $pot => $teamId) {
                $this->db->insert('picks', [
                    'user_id'    => $userId,
                    'team_id'    => (int)$teamId,
                    'pot'        => (int)$pot,
                    'created_at' => $now,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function hasCompletePicks(int $userId): bool
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM {prefix:picks} WHERE user_id = :uid',
            ['uid' => $userId]
        );
        return ((int)($row['c'] ?? 0)) >= 6;
    }

    /**
     * @return array<int, array{user_id: int, full_name: string, picks: array<int, array{team_name: string, pot: int}>}>
     */
    public function allWithUsers(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.user_id, p.pot, p.team_id, t.name AS team_name, u.full_name
             FROM {prefix:picks} p
             JOIN {prefix:teams} t ON t.id = p.team_id
             JOIN {prefix:users} u ON u.id = p.user_id AND u.status != :s
             ORDER BY u.full_name, p.pot',
            ['s' => 'deleted']
        );
        $result = [];
        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            if (!isset($result[$uid])) {
                $result[$uid] = [
                    'user_id'   => $uid,
                    'full_name' => (string)$r['full_name'],
                    'picks'     => [],
                ];
            }
            $result[$uid]['picks'][] = [
                'team_name' => (string)$r['team_name'],
                'pot'       => (int)$r['pot'],
            ];
        }
        return array_values($result);
    }

    /** @param array<string, mixed> $row */
    public function hydrate(array $row): self
    {
        $p = new self($this->db);
        $p->id        = (int)($row['id'] ?? 0);
        $p->userId    = (int)($row['user_id'] ?? 0);
        $p->teamId    = (int)($row['team_id'] ?? 0);
        $p->pot       = (int)($row['pot'] ?? 0);
        $p->createdAt = $row['created_at'] ?? null;
        return $p;
    }
}
