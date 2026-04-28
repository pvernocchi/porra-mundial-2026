<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Scoring engine. Computes points for each team and aggregates
 * per-user totals based on their picks.
 *
 * Point system:
 *   Results: Win +3, Draw +1
 *   Progress: Group +3, R16 +3, QF +4, SF +6, Final +8, Champion +12
 *   Bonus: 3+ goals +2, Clean sheet +1, Comeback +1
 *   Penalties: Yellow −0.2, Double yellow −1, Red −2, Concede 3+ −2, Last in group −1
 *   Awards: MVP +3, Golden Boot +2, Golden Glove +2, Best Young +2
 */
final class Score
{
    public function __construct(private Database $db) {}

    /**
     * Calculate points for a single team from matches.
     * @param array<int, GameMatch> $matches  played matches involving this team
     * @return float
     */
    public function teamMatchPoints(int $teamId, array $matches): float
    {
        $points = 0.0;
        foreach ($matches as $m) {
            if (!$m->played || $m->homeGoals === null || $m->awayGoals === null) {
                continue;
            }
            $isHome = ($m->homeTeamId === $teamId);
            $goalsFor     = $isHome ? $m->homeGoals : $m->awayGoals;
            $goalsAgainst = $isHome ? $m->awayGoals : $m->homeGoals;
            $yellows       = $isHome ? $m->homeYellows : $m->awayYellows;
            $doubleYellows = $isHome ? $m->homeDoubleYellows : $m->awayDoubleYellows;
            $reds          = $isHome ? $m->homeReds : $m->awayReds;
            $comeback      = $isHome ? $m->homeComeback : $m->awayComeback;

            // Result
            if ($goalsFor > $goalsAgainst) {
                $points += 3;
            } elseif ($goalsFor === $goalsAgainst) {
                $points += 1;
            }

            // Bonus: 3+ goals scored
            if ($goalsFor >= 3) {
                $points += 2;
            }

            // Bonus: clean sheet
            if ($goalsAgainst === 0) {
                $points += 1;
            }

            // Bonus: comeback
            if ($comeback) {
                $points += 1;
            }

            // Penalties: cards
            $points -= $yellows * 0.2;
            $points -= $doubleYellows * 1;
            $points -= $reds * 2;

            // Penalty: concede 3+
            if ($goalsAgainst >= 3) {
                $points -= 2;
            }
        }
        return $points;
    }

    /**
     * Points from tournament achievements.
     */
    public function teamProgressPoints(int $teamId): float
    {
        $map = [
            'passed_group' => 3,
            'round_of_16'  => 3,
            'quarter'      => 4,
            'semi'         => 6,
            'final'        => 8,
            'champion'     => 12,
            'last_in_group' => -1,
        ];
        $rows = $this->db->fetchAll(
            'SELECT achievement FROM {prefix:tournament_progress} WHERE team_id = :tid',
            ['tid' => $teamId]
        );
        $points = 0.0;
        foreach ($rows as $r) {
            $points += $map[$r['achievement']] ?? 0;
        }
        return $points;
    }

    /**
     * Points from individual awards for a team.
     */
    public function teamAwardPoints(int $teamId): float
    {
        $map = [
            'mvp'          => 3,
            'golden_boot'  => 2,
            'golden_glove' => 2,
            'best_young'   => 2,
        ];
        $rows = $this->db->fetchAll(
            'SELECT award_type FROM {prefix:tournament_awards} WHERE team_id = :tid',
            ['tid' => $teamId]
        );
        $points = 0.0;
        foreach ($rows as $r) {
            $points += $map[$r['award_type']] ?? 0;
        }
        return $points;
    }

    /**
     * Full leaderboard: each user's total score across their 6 picked teams.
     * @return array<int, array{user_id: int, full_name: string, total: float, teams: array<int, array{team_id: int, team_name: string, pot: int, points: float}>}>
     */
    public function leaderboard(): array
    {
        // Get all picks with team info
        $picks = $this->db->fetchAll(
            'SELECT p.user_id, p.team_id, p.pot, t.name AS team_name, u.full_name
             FROM {prefix:picks} p
             JOIN {prefix:teams} t ON t.id = p.team_id
             JOIN {prefix:users} u ON u.id = p.user_id AND u.status != :s
             ORDER BY u.full_name, p.pot',
            ['s' => 'deleted']
        );

        // Get all played matches
        $allMatches = (new GameMatch($this->db))->played();

        // Index matches by team
        $matchesByTeam = [];
        foreach ($allMatches as $m) {
            $matchesByTeam[$m->homeTeamId][] = $m;
            $matchesByTeam[$m->awayTeamId][] = $m;
        }

        // Cache team scores
        $teamScoreCache = [];
        $getTeamScore = function (int $teamId) use (&$teamScoreCache, $matchesByTeam): float {
            if (!isset($teamScoreCache[$teamId])) {
                $matches = $matchesByTeam[$teamId] ?? [];
                $teamScoreCache[$teamId] = $this->teamMatchPoints($teamId, $matches)
                    + $this->teamProgressPoints($teamId)
                    + $this->teamAwardPoints($teamId);
            }
            return $teamScoreCache[$teamId];
        };

        // Build per-user board
        $board = [];
        foreach ($picks as $r) {
            $uid = (int)$r['user_id'];
            if (!isset($board[$uid])) {
                $board[$uid] = [
                    'user_id'   => $uid,
                    'full_name' => (string)$r['full_name'],
                    'total'     => 0.0,
                    'teams'     => [],
                ];
            }
            $teamId = (int)$r['team_id'];
            $pts = $getTeamScore($teamId);
            $board[$uid]['teams'][] = [
                'team_id'   => $teamId,
                'team_name' => (string)$r['team_name'],
                'pot'       => (int)$r['pot'],
                'points'    => $pts,
            ];
            $board[$uid]['total'] += $pts;
        }

        // Sort by total desc
        usort($board, fn($a, $b) => $b['total'] <=> $a['total']);

        return $board;
    }

    /**
     * Detailed score breakdown for a single user.
     * @return array{total: float, rank: int, total_players: int, teams: array<int, array{team_id: int, team_name: string, pot: int, match_pts: float, progress_pts: float, award_pts: float, total: float}>}
     */
    public function userScoreBreakdown(int $userId): array
    {
        $picks = $this->db->fetchAll(
            'SELECT p.team_id, p.pot, t.name AS team_name
             FROM {prefix:picks} p
             JOIN {prefix:teams} t ON t.id = p.team_id
             WHERE p.user_id = :uid
             ORDER BY p.pot',
            ['uid' => $userId]
        );

        $allMatches = (new GameMatch($this->db))->played();
        $matchesByTeam = [];
        foreach ($allMatches as $m) {
            $matchesByTeam[$m->homeTeamId][] = $m;
            $matchesByTeam[$m->awayTeamId][] = $m;
        }

        $teams = [];
        $total = 0.0;
        foreach ($picks as $r) {
            $teamId = (int)$r['team_id'];
            $matchPts    = $this->teamMatchPoints($teamId, $matchesByTeam[$teamId] ?? []);
            $progressPts = $this->teamProgressPoints($teamId);
            $awardPts    = $this->teamAwardPoints($teamId);
            $teamTotal   = $matchPts + $progressPts + $awardPts;
            $total += $teamTotal;
            $teams[] = [
                'team_id'      => $teamId,
                'team_name'    => (string)$r['team_name'],
                'pot'          => (int)$r['pot'],
                'match_pts'    => $matchPts,
                'progress_pts' => $progressPts,
                'award_pts'    => $awardPts,
                'total'        => $teamTotal,
            ];
        }

        // Determine rank from leaderboard
        $board = $this->leaderboard();
        $rank = 0;
        $pos = 0;
        $prevTotal = null;
        foreach ($board as $entry) {
            $pos++;
            if ($entry['total'] !== $prevTotal) {
                $rank = $pos;
                $prevTotal = $entry['total'];
            }
            if ($entry['user_id'] === $userId) {
                break;
            }
        }
        if ($pos > 0 && ($board[$pos - 1]['user_id'] ?? 0) !== $userId) {
            $rank = 0; // user not found in board
        }

        return [
            'total'         => $total,
            'rank'          => $rank,
            'total_players' => count($board),
            'teams'         => $teams,
        ];
    }

    /**
     * Save/update a tournament progress achievement.
     */
    public function addProgress(int $teamId, string $achievement): void
    {
        $exists = $this->db->fetch(
            'SELECT id FROM {prefix:tournament_progress} WHERE team_id = :tid AND achievement = :a',
            ['tid' => $teamId, 'a' => $achievement]
        );
        if ($exists === null) {
            $this->db->insert('tournament_progress', [
                'team_id'     => $teamId,
                'achievement' => $achievement,
                'created_at'  => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function removeProgress(int $teamId, string $achievement): void
    {
        $this->db->run(
            'DELETE FROM {prefix:tournament_progress} WHERE team_id = :tid AND achievement = :a',
            ['tid' => $teamId, 'a' => $achievement]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allProgress(): array
    {
        return $this->db->fetchAll(
            'SELECT tp.*, t.name AS team_name
             FROM {prefix:tournament_progress} tp
             JOIN {prefix:teams} t ON t.id = tp.team_id
             ORDER BY t.name, tp.achievement'
        );
    }

    public function setAward(string $awardType, int $teamId, ?string $playerName): void
    {
        // Delete existing
        $this->db->run('DELETE FROM {prefix:tournament_awards} WHERE award_type = :a', ['a' => $awardType]);
        $this->db->insert('tournament_awards', [
            'award_type'  => $awardType,
            'team_id'     => $teamId,
            'player_name' => $playerName,
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function removeAward(string $awardType): void
    {
        $this->db->run('DELETE FROM {prefix:tournament_awards} WHERE award_type = :a', ['a' => $awardType]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allAwards(): array
    {
        return $this->db->fetchAll(
            'SELECT ta.*, t.name AS team_name
             FROM {prefix:tournament_awards} ta
             JOIN {prefix:teams} t ON t.id = ta.team_id
             ORDER BY ta.award_type'
        );
    }
}
