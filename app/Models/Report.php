<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Read-only aggregator that builds the data needed by the broadcast
 * report (admin / reportes). All numbers are computed live from the
 * current state of picks, matches, achievements and awards. Movement
 * detection requires a previous {@see LeaderboardSnapshot}.
 */
final class Report
{
    /** Default minimum number of positions a participant must move to be
     *  considered a "movimiento significativo". */
    public const SIGNIFICANT_MOVE = 1;

    public function __construct(private Database $db) {}

    /**
     * Top N participants by total score. Each entry mirrors the shape
     * returned by {@see Score::leaderboard()} plus an explicit `position`.
     *
     * @param array<int, array<string, mixed>> $board  Output of Score::leaderboard()
     * @param int $n
     * @return array<int, array<string, mixed>>
     */
    public function topLeaders(array $board, int $n = 5): array
    {
        $out = [];
        $position = 0;
        $rank = 0;
        $prevTotal = null;
        foreach ($board as $entry) {
            $position++;
            $total = (float)$entry['total'];
            if ($prevTotal === null || $total !== $prevTotal) {
                $rank = $position;
                $prevTotal = $total;
            }
            $entry['position'] = $rank;
            $out[] = $entry;
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    /**
     * Compute ranking movement for every participant present in both the
     * current leaderboard and the latest snapshot. Movement is
     *   previous_position - current_position
     * so that positive numbers represent climbing the table.
     *
     * @param array<int, array<string, mixed>> $board     Current leaderboard.
     * @param array<int, array{position:int, total:float, snapshot_at:string}> $previous
     * @return array<int, array{user_id:int, display_name:string, full_name:string, total:float, current_position:int, previous_position:int, delta:int}>
     */
    public function movements(array $board, array $previous): array
    {
        $movements = [];
        $position = 0;
        $rank = 0;
        $prevTotal = null;
        foreach ($board as $entry) {
            $position++;
            $total = (float)$entry['total'];
            if ($prevTotal === null || $total !== $prevTotal) {
                $rank = $position;
                $prevTotal = $total;
            }
            $uid = (int)$entry['user_id'];
            if (!isset($previous[$uid])) {
                continue;
            }
            $prevPos = $previous[$uid]['position'];
            $movements[] = [
                'user_id'           => $uid,
                'display_name'      => (string)($entry['display_name'] ?? $entry['full_name']),
                'full_name'         => (string)$entry['full_name'],
                'total'             => $total,
                'current_position'  => $rank,
                'previous_position' => $prevPos,
                'delta'             => $prevPos - $rank,
            ];
        }
        return $movements;
    }

    /**
     * Top N movements upwards (largest positive delta). Only includes
     * participants who actually moved by at least SIGNIFICANT_MOVE.
     *
     * @param array<int, array<string, mixed>> $movements Output of movements().
     * @return array<int, array<string, mixed>>
     */
    public function topUpward(array $movements, int $n = 3): array
    {
        $filtered = array_values(array_filter(
            $movements,
            fn(array $m) => $m['delta'] >= self::SIGNIFICANT_MOVE
        ));
        usort($filtered, fn($a, $b) => $b['delta'] <=> $a['delta']);
        return array_slice($filtered, 0, $n);
    }

    /**
     * Top N movements downwards (largest negative delta).
     *
     * @param array<int, array<string, mixed>> $movements
     * @return array<int, array<string, mixed>>
     */
    public function topDownward(array $movements, int $n = 3): array
    {
        $filtered = array_values(array_filter(
            $movements,
            fn(array $m) => $m['delta'] <= -self::SIGNIFICANT_MOVE
        ));
        usort($filtered, fn($a, $b) => $a['delta'] <=> $b['delta']);
        return array_slice($filtered, 0, $n);
    }

    /**
     * Compute total points for every team (matches + progress + awards).
     *
     * @param Score $scoreModel
     * @return array<int, array{team_id:int, team_name:string, pot:int, points:float}>
     *         Sorted by points DESC, then name ASC.
     */
    public function teamScores(Score $scoreModel): array
    {
        $teams = (new Team($this->db))->all();
        $allMatches = (new GameMatch($this->db))->played();

        $matchesByTeam = [];
        foreach ($allMatches as $m) {
            $matchesByTeam[$m->homeTeamId][] = $m;
            $matchesByTeam[$m->awayTeamId][] = $m;
        }

        $rows = [];
        foreach ($teams as $t) {
            $points = $scoreModel->teamMatchPoints($t->id, $matchesByTeam[$t->id] ?? [])
                + $scoreModel->teamProgressPoints($t->id)
                + $scoreModel->teamAwardPoints($t->id);
            $rows[] = [
                'team_id'   => $t->id,
                'team_name' => $t->name,
                'pot'       => $t->pot,
                'points'    => $points,
            ];
        }
        usort($rows, function (array $a, array $b): int {
            if ($a['points'] === $b['points']) {
                return strcmp($a['team_name'], $b['team_name']);
            }
            return $b['points'] <=> $a['points'];
        });
        return $rows;
    }

    /**
     * Slice the best/worst N teams from the team-score list.
     *
     * @param array<int, array{team_id:int, team_name:string, pot:int, points:float}> $teamScores
     * @return array<int, array<string, mixed>>
     */
    public function bestTeams(array $teamScores, int $n = 5): array
    {
        return array_slice($teamScores, 0, $n);
    }

    /**
     * @param array<int, array{team_id:int, team_name:string, pot:int, points:float}> $teamScores
     * @return array<int, array<string, mixed>>
     */
    public function worstTeams(array $teamScores, int $n = 5): array
    {
        $reversed = array_reverse($teamScores);
        return array_slice($reversed, 0, $n);
    }

    /**
     * Most picked teams across all participants.
     *
     * @return array<int, array{team_id:int, team_name:string, pot:int, picks:int}>
     */
    public function mostPickedTeams(int $n = 5): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.team_id, t.name AS team_name, t.pot, COUNT(*) AS picks
             FROM {prefix:picks} p
             JOIN {prefix:teams} t ON t.id = p.team_id
             JOIN {prefix:users} u ON u.id = p.user_id AND u.status != :s
             GROUP BY p.team_id, t.name, t.pot
             ORDER BY picks DESC, t.name ASC'
        , ['s' => 'deleted']);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'team_id'   => (int)$r['team_id'],
                'team_name' => (string)$r['team_name'],
                'pot'       => (int)$r['pot'],
                'picks'     => (int)$r['picks'],
            ];
        }
        return array_slice($out, 0, $n);
    }

    /**
     * Groups of participants whose picks are *exactly* identical across
     * all six pots. Only groups with two or more participants are
     * returned. Within each group the participants are sorted by full
     * name; groups themselves are sorted by group size DESC.
     *
     * @return array<int, array{teams: array<int, array{pot:int, team_name:string}>, participants: array<int, array{user_id:int, full_name:string, team_name:string}>}>
     */
    public function identicalPickGroups(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.user_id, p.pot, p.team_id, t.name AS team_name,
                    u.full_name, u.team_name AS user_team_name
             FROM {prefix:picks} p
             JOIN {prefix:teams} t ON t.id = p.team_id
             JOIN {prefix:users} u ON u.id = p.user_id AND u.status != :s
             ORDER BY u.full_name, p.pot',
            ['s' => 'deleted']
        );

        // Group rows by user_id.
        $byUser = [];
        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user_id'        => $uid,
                    'full_name'      => (string)$r['full_name'],
                    'user_team_name' => trim((string)($r['user_team_name'] ?? '')),
                    'picks'          => [],
                ];
            }
            $byUser[$uid]['picks'][(int)$r['pot']] = [
                'team_id'   => (int)$r['team_id'],
                'team_name' => (string)$r['team_name'],
            ];
        }

        // Build a signature for each user with a complete pick set (1..6).
        $groups = [];
        foreach ($byUser as $u) {
            $picks = $u['picks'];
            ksort($picks);
            if (count($picks) < 6) {
                // Skip incomplete pick sets to avoid spurious matches.
                continue;
            }
            $sig = '';
            $teams = [];
            foreach ($picks as $pot => $info) {
                $sig .= $pot . ':' . $info['team_id'] . '|';
                $teams[] = ['pot' => $pot, 'team_name' => $info['team_name']];
            }
            if (!isset($groups[$sig])) {
                $groups[$sig] = ['teams' => $teams, 'participants' => []];
            }
            $groups[$sig]['participants'][] = [
                'user_id'   => $u['user_id'],
                'full_name' => $u['full_name'],
                'team_name' => $u['user_team_name'],
            ];
        }

        // Keep only groups with 2+ participants.
        $out = [];
        foreach ($groups as $g) {
            if (count($g['participants']) < 2) {
                continue;
            }
            usort($g['participants'], fn($a, $b) => strcasecmp($a['full_name'], $b['full_name']));
            $out[] = $g;
        }
        usort($out, fn($a, $b) => count($b['participants']) <=> count($a['participants']));
        return $out;
    }
}
