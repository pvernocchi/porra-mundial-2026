<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Snapshot of the leaderboard taken at a specific point in time.
 *
 * Snapshots are used by the broadcast report (admin / reportes) to
 * detect significant ranking movements between two reporting periods.
 */
final class LeaderboardSnapshot
{
    public function __construct(private Database $db) {}

    /**
     * Persist the supplied leaderboard as a new snapshot. All rows in a
     * single snapshot share the same `snapshot_at` timestamp.
     *
     * @param array<int, array{user_id:int, total:float|int}> $board  Ordered by total DESC.
     * @return string The snapshot timestamp that was used (UTC, 'Y-m-d H:i:s').
     */
    public function save(array $board): string
    {
        $now = gmdate('Y-m-d H:i:s');
        $position = 0;
        $rank = 0;
        $prevTotal = null;

        $this->db->beginTransaction();
        try {
            foreach ($board as $entry) {
                $position++;
                $total = (float)($entry['total'] ?? 0);
                if ($prevTotal === null || $total !== $prevTotal) {
                    $rank = $position;
                    $prevTotal = $total;
                }
                $this->db->insert('leaderboard_snapshots', [
                    'snapshot_at' => $now,
                    'user_id'     => (int)$entry['user_id'],
                    'position'    => $rank,
                    'total'       => $total,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $now;
    }

    /**
     * Return the most recent snapshot indexed by user_id, or [] if none.
     *
     * @return array<int, array{position:int, total:float, snapshot_at:string}>
     */
    public function latest(): array
    {
        $row = $this->db->fetch(
            'SELECT snapshot_at FROM {prefix:leaderboard_snapshots}
             ORDER BY snapshot_at DESC, id DESC LIMIT 1'
        );
        if ($row === null) {
            return [];
        }
        $at = (string)$row['snapshot_at'];
        $rows = $this->db->fetchAll(
            'SELECT user_id, position, total FROM {prefix:leaderboard_snapshots}
             WHERE snapshot_at = :at',
            ['at' => $at]
        );
        $by = [];
        foreach ($rows as $r) {
            $by[(int)$r['user_id']] = [
                'position'    => (int)$r['position'],
                'total'       => (float)$r['total'],
                'snapshot_at' => $at,
            ];
        }
        return $by;
    }

    /**
     * Timestamp of the most recent snapshot, or null if none exist.
     */
    public function latestTimestamp(): ?string
    {
        $row = $this->db->fetch(
            'SELECT snapshot_at FROM {prefix:leaderboard_snapshots}
             ORDER BY snapshot_at DESC, id DESC LIMIT 1'
        );
        return $row === null ? null : (string)$row['snapshot_at'];
    }
}
