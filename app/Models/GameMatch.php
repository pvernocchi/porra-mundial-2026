<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class GameMatch
{
    public int $id = 0;
    public string $phase = '';
    public ?string $matchDate = null;
    public int $homeTeamId = 0;
    public int $awayTeamId = 0;
    public ?int $homeGoals = null;
    public ?int $awayGoals = null;
    public int $homeYellows = 0;
    public int $awayYellows = 0;
    public int $homeDoubleYellows = 0;
    public int $awayDoubleYellows = 0;
    public int $homeReds = 0;
    public int $awayReds = 0;
    public bool $homeComeback = false;
    public bool $awayComeback = false;
    public bool $played = false;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    // Joined fields (not persisted)
    public string $homeTeamName = '';
    public string $awayTeamName = '';

    public function __construct(private Database $db) {}

    public function find(int $id): ?self
    {
        $row = $this->db->fetch(
            'SELECT m.*, ht.name AS home_team_name, at2.name AS away_team_name
             FROM {prefix:matches} m
             JOIN {prefix:teams} ht ON ht.id = m.home_team_id
             JOIN {prefix:teams} at2 ON at2.id = m.away_team_id
             WHERE m.id = :id',
            ['id' => $id]
        );
        return $row ? $this->hydrate($row) : null;
    }

    /** @return array<int, self> */
    public function all(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT m.*, ht.name AS home_team_name, at2.name AS away_team_name
             FROM {prefix:matches} m
             JOIN {prefix:teams} ht ON ht.id = m.home_team_id
             JOIN {prefix:teams} at2 ON at2.id = m.away_team_id
             ORDER BY m.match_date, m.id'
        );
        return array_map(fn(array $r) => (new self($this->db))->hydrate($r), $rows);
    }

    /** @return array<int, self> */
    public function played(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT m.*, ht.name AS home_team_name, at2.name AS away_team_name
             FROM {prefix:matches} m
             JOIN {prefix:teams} ht ON ht.id = m.home_team_id
             JOIN {prefix:teams} at2 ON at2.id = m.away_team_id
             WHERE m.played = 1
             ORDER BY m.match_date DESC, m.id DESC'
        );
        return array_map(fn(array $r) => (new self($this->db))->hydrate($r), $rows);
    }

    public function create(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return (int)$this->db->insert('matches', [
            'phase'               => (string)$data['phase'],
            'match_date'          => $data['match_date'] ?? null,
            'home_team_id'        => (int)$data['home_team_id'],
            'away_team_id'        => (int)$data['away_team_id'],
            'home_goals'          => isset($data['home_goals']) ? (int)$data['home_goals'] : null,
            'away_goals'          => isset($data['away_goals']) ? (int)$data['away_goals'] : null,
            'home_yellows'        => (int)($data['home_yellows'] ?? 0),
            'away_yellows'        => (int)($data['away_yellows'] ?? 0),
            'home_double_yellows' => (int)($data['home_double_yellows'] ?? 0),
            'away_double_yellows' => (int)($data['away_double_yellows'] ?? 0),
            'home_reds'           => (int)($data['home_reds'] ?? 0),
            'away_reds'           => (int)($data['away_reds'] ?? 0),
            'home_comeback'       => (int)($data['home_comeback'] ?? 0),
            'away_comeback'       => (int)($data['away_comeback'] ?? 0),
            'played'              => (int)($data['played'] ?? 0),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    public function updateResult(int $id, array $data): void
    {
        $this->db->update('matches', [
            'home_goals'          => isset($data['home_goals']) ? (int)$data['home_goals'] : null,
            'away_goals'          => isset($data['away_goals']) ? (int)$data['away_goals'] : null,
            'home_yellows'        => (int)($data['home_yellows'] ?? 0),
            'away_yellows'        => (int)($data['away_yellows'] ?? 0),
            'home_double_yellows' => (int)($data['home_double_yellows'] ?? 0),
            'away_double_yellows' => (int)($data['away_double_yellows'] ?? 0),
            'home_reds'           => (int)($data['home_reds'] ?? 0),
            'away_reds'           => (int)($data['away_reds'] ?? 0),
            'home_comeback'       => (int)($data['home_comeback'] ?? 0),
            'away_comeback'       => (int)($data['away_comeback'] ?? 0),
            'played'              => (int)($data['played'] ?? 0),
            'updated_at'          => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function deleteMatch(int $id): void
    {
        $this->db->run('DELETE FROM {prefix:matches} WHERE id = :id', ['id' => $id]);
    }

    /**
     * Get all played matches for a specific team.
     * @return array<int, self>
     */
    public function forTeam(int $teamId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT m.*, ht.name AS home_team_name, at2.name AS away_team_name
             FROM {prefix:matches} m
             JOIN {prefix:teams} ht ON ht.id = m.home_team_id
             JOIN {prefix:teams} at2 ON at2.id = m.away_team_id
             WHERE m.played = 1 AND (m.home_team_id = :t1 OR m.away_team_id = :t2)
             ORDER BY m.match_date, m.id',
            ['t1' => $teamId, 't2' => $teamId]
        );
        return array_map(fn(array $r) => (new self($this->db))->hydrate($r), $rows);
    }

    /** @param array<string, mixed> $row */
    public function hydrate(array $row): self
    {
        $m = new self($this->db);
        $m->id                = (int)($row['id'] ?? 0);
        $m->phase             = (string)($row['phase'] ?? '');
        $m->matchDate         = $row['match_date'] ?? null;
        $m->homeTeamId        = (int)($row['home_team_id'] ?? 0);
        $m->awayTeamId        = (int)($row['away_team_id'] ?? 0);
        $m->homeGoals         = isset($row['home_goals']) ? (int)$row['home_goals'] : null;
        $m->awayGoals         = isset($row['away_goals']) ? (int)$row['away_goals'] : null;
        $m->homeYellows       = (int)($row['home_yellows'] ?? 0);
        $m->awayYellows       = (int)($row['away_yellows'] ?? 0);
        $m->homeDoubleYellows = (int)($row['home_double_yellows'] ?? 0);
        $m->awayDoubleYellows = (int)($row['away_double_yellows'] ?? 0);
        $m->homeReds          = (int)($row['home_reds'] ?? 0);
        $m->awayReds          = (int)($row['away_reds'] ?? 0);
        $m->homeComeback      = (bool)($row['home_comeback'] ?? false);
        $m->awayComeback      = (bool)($row['away_comeback'] ?? false);
        $m->played            = (bool)($row['played'] ?? false);
        $m->createdAt         = $row['created_at'] ?? null;
        $m->updatedAt         = $row['updated_at'] ?? null;
        $m->homeTeamName      = (string)($row['home_team_name'] ?? '');
        $m->awayTeamName      = (string)($row['away_team_name'] ?? '');
        return $m;
    }
}
