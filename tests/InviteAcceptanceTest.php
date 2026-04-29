<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Database;
use App\Core\Installer;
use App\Models\Invitation;
use App\Models\User;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests the invite acceptance flow: creating a user from an invitation
 * works correctly after the team_name column was added (migration 0004).
 */
final class InviteAcceptanceTest extends TestCase
{
    private string $dbFile;
    private Database $db;

    protected function setUp(): void
    {
        $root = dirname(__DIR__);
        require_once $root . '/app/Core/Autoloader.php';
        $autoloader = new \App\Core\Autoloader();
        $autoloader->addNamespace('App', $root . '/app');
        $autoloader->register();

        $this->dbFile = tempnam(sys_get_temp_dir(), 'sqlite');
        $cfg = ['driver' => 'sqlite', 'database' => $this->dbFile, 'prefix' => 'pm_'];

        $app  = new Application($root);
        $inst = new Installer($app);
        $inst->runMigrations($cfg);

        $this->db = Database::fromConfig($cfg);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbFile);
    }

    public function testCreateUserFromInvitation(): void
    {
        $invModel = new Invitation($this->db);
        $inv = $invModel->create('Test User', 'test@example.com', 'user', 0);

        $found = $invModel->findValidByToken($inv['token']);
        $this->assertNotNull($found, 'Invitation should be valid');

        // Simulate the InviteController::submit flow
        $userModel = new User($this->db);
        $this->assertFalse($userModel->emailExists((string)$found['email']));

        $userId = $userModel->create(
            'Test User',
            (string)$found['email'],
            'TestPass123!',
            (string)$found['role']
        );
        $this->assertGreaterThan(0, $userId);

        // Verify user can be found and has correct attributes
        $user = $userModel->find($userId);
        $this->assertNotNull($user);
        $this->assertSame('Test User', $user->fullName);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertSame('', $user->teamName);

        // Mark invitation as used
        $invModel->markUsed((int)$found['id']);
        $this->assertNull($invModel->findValidByToken($inv['token']));
    }

    public function testCreateUserWithTeamName(): void
    {
        $userModel = new User($this->db);
        $userId = $userModel->create(
            'Player One',
            'player@example.com',
            'StrongPass1!',
            'user',
            'Los Galácticos'
        );
        $this->assertGreaterThan(0, $userId);

        $user = $userModel->find($userId);
        $this->assertNotNull($user);
        $this->assertSame('Los Galácticos', $user->teamName);
    }

    public function testCreateUserWithoutTeamNameDefaultsToEmpty(): void
    {
        $userModel = new User($this->db);
        $userId = $userModel->create(
            'No Team',
            'noteam@example.com',
            'SecurePass2@',
            'user'
        );
        $this->assertGreaterThan(0, $userId);

        $user = $userModel->find($userId);
        $this->assertNotNull($user);
        $this->assertSame('', $user->teamName);
    }

    public function testCreateUserStillWorksBeforeTeamNameMigration(): void
    {
        $legacyDbFile = tempnam(sys_get_temp_dir(), 'sqlite');
        try {
            $pdo = new PDO('sqlite:' . $legacyDbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec(
                'CREATE TABLE pm_users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    full_name VARCHAR(150) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    role VARCHAR(20) NOT NULL DEFAULT \'user\',
                    status VARCHAR(20) NOT NULL DEFAULT \'active\',
                    mfa_enforced INTEGER NOT NULL DEFAULT 0,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    last_login_at DATETIME NULL,
                    deleted_at DATETIME NULL,
                    UNIQUE (email)
                )'
            );

            $userModel = new User(new Database($pdo, 'pm_', 'sqlite'));
            $userId = $userModel->create(
                'Legacy User',
                'legacy@example.com',
                'SecurePass3#',
                'user',
                'Legacy Team'
            );

            $this->assertGreaterThan(0, $userId);
            $user = $userModel->find($userId);
            $this->assertNotNull($user);
            $this->assertSame('Legacy User', $user->fullName);
            $this->assertSame('', $user->teamName);

            $emptyTeamUserId = $userModel->create(
                'Legacy Empty Team',
                'legacy-empty@example.com',
                'SecurePass4#',
                'user'
            );
            $this->assertGreaterThan(0, $emptyTeamUserId);
        } finally {
            @unlink($legacyDbFile);
        }
    }

    public function testMigrationCanRunTwice(): void
    {
        // Running migrations again should not throw (idempotent).
        $root = dirname(__DIR__);
        $app  = new Application($root);
        $inst = new Installer($app);
        $cfg  = ['driver' => 'sqlite', 'database' => $this->dbFile, 'prefix' => 'pm_'];

        // This should not throw even though tables and columns already exist
        $count = $inst->runMigrations($cfg);
        $this->assertGreaterThanOrEqual(1, $count);
    }
}
