<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Database;
use App\Core\Installer;
use App\Models\Pick;
use PHPUnit\Framework\TestCase;

final class PickTest extends TestCase
{
    public function testSaveForUserCannotOverwriteAfterCompleteSubmit(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sqlite');
        if ($tmp === false) {
            $this->fail('Could not create temporary SQLite database');
        }

        try {
            $app = new Application(dirname(__DIR__));
            $inst = new Installer($app);
            $cfg = ['driver' => 'sqlite', 'database' => $tmp, 'prefix' => 'pm_'];
            $inst->runMigrations($cfg);

            $db = Database::fromConfig($cfg);
            $userId = (int)$db->insert('users', [
                'full_name' => 'Test User',
                'email' => 'test@example.com',
                'password_hash' => 'hash',
                'role' => 'user',
                'status' => 'active',
            ]);

            $pickModel = new Pick($db);
            $firstPicks = [1 => 1, 2 => 9, 3 => 17, 4 => 25, 5 => 33, 6 => 41];
            $secondPicks = [1 => 2, 2 => 10, 3 => 18, 4 => 26, 5 => 34, 6 => 42];

            $pickModel->saveForUser($userId, $firstPicks);
            $this->assertTrue($pickModel->hasCompletePicks($userId));

            $pickModel->saveForUser($userId, $secondPicks);

            $saved = $pickModel->forUser($userId);
            $this->assertCount(6, $saved);

            $savedByPot = [];
            foreach ($saved as $pick) {
                $savedByPot[$pick->pot] = $pick->teamId;
            }
            ksort($savedByPot);

            $this->assertSame($firstPicks, $savedByPot);
        } finally {
            @unlink($tmp);
        }
    }
}
