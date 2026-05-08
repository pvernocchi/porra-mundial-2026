<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Database;
use App\Core\Installer;
use App\Models\LeaderboardSnapshot;
use App\Models\Report;
use App\Models\Score;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the broadcast-report aggregator (admin/reports).
 */
final class ReportTest extends TestCase
{
    private string $tmp;
    private Database $db;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__);
        require_once $root . '/app/Core/Autoloader.php';
        $autoloader = new \App\Core\Autoloader();
        $autoloader->addNamespace('App', $root . '/app');
        $autoloader->register();

        $this->tmp = tempnam(sys_get_temp_dir(), 'pm_report_');
        $cfg = ['driver' => 'sqlite', 'database' => $this->tmp, 'prefix' => 'pm_'];

        (new Installer(new Application($root)))->runMigrations($cfg);
        $this->db = Database::fromConfig($cfg);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        parent::tearDown();
    }

    public function testTopLeadersHandlesTiesAndLimit(): void
    {
        $report = new Report($this->db);
        $board = [
            ['user_id' => 1, 'full_name' => 'Ana',   'display_name' => 'Ana',   'total' => 100.0],
            ['user_id' => 2, 'full_name' => 'Bea',   'display_name' => 'Bea',   'total' => 100.0],
            ['user_id' => 3, 'full_name' => 'Carla', 'display_name' => 'Carla', 'total' => 90.0],
            ['user_id' => 4, 'full_name' => 'Dani',  'display_name' => 'Dani',  'total' => 80.0],
            ['user_id' => 5, 'full_name' => 'Eli',   'display_name' => 'Eli',   'total' => 70.0],
            ['user_id' => 6, 'full_name' => 'Fer',   'display_name' => 'Fer',   'total' => 60.0],
        ];
        $top = $report->topLeaders($board, 5);
        $this->assertCount(5, $top);
        $this->assertSame(1, $top[0]['position']);
        $this->assertSame(1, $top[1]['position']); // tie
        $this->assertSame(3, $top[2]['position']);
        $this->assertSame(4, $top[3]['position']);
        $this->assertSame(5, $top[4]['position']);
    }

    public function testMovementsAndUpwardDownward(): void
    {
        $report = new Report($this->db);
        $board = [
            ['user_id' => 1, 'full_name' => 'A', 'display_name' => 'A', 'total' => 100.0], // pos 1
            ['user_id' => 2, 'full_name' => 'B', 'display_name' => 'B', 'total' => 90.0],  // pos 2
            ['user_id' => 3, 'full_name' => 'C', 'display_name' => 'C', 'total' => 80.0],  // pos 3
            ['user_id' => 4, 'full_name' => 'D', 'display_name' => 'D', 'total' => 70.0],  // pos 4
        ];
        $previous = [
            1 => ['position' => 3, 'total' => 50.0, 'snapshot_at' => '2026-01-01 00:00:00'],
            2 => ['position' => 4, 'total' => 40.0, 'snapshot_at' => '2026-01-01 00:00:00'],
            3 => ['position' => 1, 'total' => 80.0, 'snapshot_at' => '2026-01-01 00:00:00'],
            4 => ['position' => 2, 'total' => 70.0, 'snapshot_at' => '2026-01-01 00:00:00'],
        ];
        $movements = $report->movements($board, $previous);
        $up = $report->topUpward($movements, 3);
        $down = $report->topDownward($movements, 3);

        $this->assertSame(1, $up[0]['user_id']);
        $this->assertSame(2, $up[0]['delta']);
        $this->assertSame(2, $up[1]['user_id']);
        $this->assertSame(2, $up[1]['delta']);

        $this->assertSame(3, $down[0]['user_id']);
        $this->assertSame(-2, $down[0]['delta']);
    }

    public function testIdenticalPickGroupsRequiresFullSetAndAtLeastTwoUsers(): void
    {
        // Insert two users with identical picks across all 6 pots, plus
        // a third user with a different pot 1 pick.
        $now = gmdate('Y-m-d H:i:s');
        $this->db->insert('users', [
            'full_name' => 'Ana', 'team_name' => 'Club A', 'email' => 'a@x',
            'password_hash' => 'x', 'role' => 'user', 'status' => 'active',
            'mfa_enforced' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->insert('users', [
            'full_name' => 'Bea', 'team_name' => 'Club B', 'email' => 'b@x',
            'password_hash' => 'x', 'role' => 'user', 'status' => 'active',
            'mfa_enforced' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->insert('users', [
            'full_name' => 'Cris', 'team_name' => '', 'email' => 'c@x',
            'password_hash' => 'x', 'role' => 'user', 'status' => 'active',
            'mfa_enforced' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Resolve a real team id per pot from seeded data.
        $teamIdsByPot = [];
        for ($pot = 1; $pot <= 6; $pot++) {
            $row = $this->db->fetch('SELECT id FROM {prefix:teams} WHERE pot = :p ORDER BY id LIMIT 1', ['p' => $pot]);
            $teamIdsByPot[$pot] = (int)$row['id'];
        }
        $altPot1 = (int)$this->db->fetch('SELECT id FROM {prefix:teams} WHERE pot = 1 ORDER BY id LIMIT 1 OFFSET 1')['id'];

        foreach ([1 => 1, 2 => 2] as $uid => $_) {
            for ($pot = 1; $pot <= 6; $pot++) {
                $this->db->insert('picks', [
                    'user_id' => $uid, 'team_id' => $teamIdsByPot[$pot],
                    'pot' => $pot, 'created_at' => $now,
                ]);
            }
        }
        // Cris differs in pot 1.
        $this->db->insert('picks', ['user_id' => 3, 'team_id' => $altPot1,           'pot' => 1, 'created_at' => $now]);
        for ($pot = 2; $pot <= 6; $pot++) {
            $this->db->insert('picks', ['user_id' => 3, 'team_id' => $teamIdsByPot[$pot], 'pot' => $pot, 'created_at' => $now]);
        }

        $groups = (new Report($this->db))->identicalPickGroups();
        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]['participants']);
        $names = array_column($groups[0]['participants'], 'full_name');
        $this->assertSame(['Ana', 'Bea'], $names);
        $this->assertSame('Club A', $groups[0]['participants'][0]['team_name']);
    }

    public function testSnapshotRoundTrip(): void
    {
        $snap = new LeaderboardSnapshot($this->db);
        $this->assertNull($snap->latestTimestamp());

        $at = $snap->save([
            ['user_id' => 1, 'total' => 50.0],
            ['user_id' => 2, 'total' => 50.0], // tie -> same rank
            ['user_id' => 3, 'total' => 30.0],
        ]);
        $latest = $snap->latest();
        $this->assertSame($at, $snap->latestTimestamp());
        $this->assertSame(1, $latest[1]['position']);
        $this->assertSame(1, $latest[2]['position']);
        $this->assertSame(3, $latest[3]['position']);
    }

    public function testMostPickedTeamsExcludesDeletedUsers(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->insert('users', [
            'full_name' => 'Ana', 'team_name' => null, 'email' => 'a@x',
            'password_hash' => 'x', 'role' => 'user', 'status' => 'active',
            'mfa_enforced' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->insert('users', [
            'full_name' => 'Bea', 'team_name' => null, 'email' => 'b@x',
            'password_hash' => 'x', 'role' => 'user', 'status' => 'deleted',
            'mfa_enforced' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $teamId = (int)$this->db->fetch('SELECT id FROM {prefix:teams} WHERE pot = 1 ORDER BY id LIMIT 1')['id'];
        $this->db->insert('picks', ['user_id' => 1, 'team_id' => $teamId, 'pot' => 1, 'created_at' => $now]);
        $this->db->insert('picks', ['user_id' => 2, 'team_id' => $teamId, 'pot' => 1, 'created_at' => $now]);

        $top = (new Report($this->db))->mostPickedTeams(5);
        $this->assertNotEmpty($top);
        $this->assertSame(1, $top[0]['picks']);
    }
}
