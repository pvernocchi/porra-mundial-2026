<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Database;
use App\Core\Installer;
use App\Core\Request;
use App\Core\Response;
use App\Models\DuplicateUserEmail;
use App\Models\Invitation;
use App\Models\User;
use App\Modules\Auth\InviteController;
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

    public function testDeletedUserEmailStillBlocksInvitationAcceptance(): void
    {
        $userModel = new User($this->db);
        $userId = $userModel->create(
            'Deleted User',
            'deleted@example.com',
            'SecurePass3!',
            'user'
        );
        $userModel->softDelete($userId);

        $this->assertFalse($userModel->emailExists('deleted@example.com'));
        $this->assertTrue($userModel->emailExistsIncludingDeleted('deleted@example.com'));
    }

    public function testInvitationAcceptanceShowsValidationErrorForDeletedUserEmail(): void
    {
        $userModel = new User($this->db);
        $userId = $userModel->create(
            'Deleted User',
            'deleted-invite@example.com',
            'SecurePass6!',
            'user'
        );
        $userModel->softDelete($userId);

        $invModel = new Invitation($this->db);
        $inv = $invModel->create('Invited User', 'deleted-invite@example.com', 'user', 0);
        $app = $this->applicationWithDatabase();
        $csrf = $app->csrf()->token();
        $req = new Request(post: [
            '_token' => $csrf,
            'full_name' => 'Invited User',
            'team_name' => '',
            'password' => 'SecurePass7!',
            'password_confirm' => 'SecurePass7!',
        ]);

        $response = (new InviteController($app))->submit($req, ['token' => $inv['token']]);

        $this->assertSame(400, $this->responseStatus($response));
        $this->assertStringContainsString(
            'Ya existe una cuenta con ese email. Pide al administrador que revise esa cuenta antes de reenviar la invitación.',
            $this->responseBody($response)
        );
        $this->assertNotNull($invModel->findValidByToken($inv['token']));
    }

    public function testDuplicateEmailCreateThrowsDomainException(): void
    {
        $userModel = new User($this->db);
        $userModel->create(
            'Existing User',
            'existing@example.com',
            'SecurePass4!',
            'user'
        );

        $this->expectException(DuplicateUserEmail::class);

        $userModel->create(
            'Duplicate User',
            'existing@example.com',
            'SecurePass5!',
            'user'
        );
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

    private function applicationWithDatabase(): Application
    {
        $app = new Application(dirname(__DIR__));
        $prop = new \ReflectionProperty(Application::class, 'db');
        $prop->setAccessible(true);
        $prop->setValue($app, $this->db);
        return $app;
    }

    private function responseStatus(Response $response): int
    {
        $prop = new \ReflectionProperty(Response::class, 'status');
        $prop->setAccessible(true);
        return (int)$prop->getValue($response);
    }

    private function responseBody(Response $response): string
    {
        $prop = new \ReflectionProperty(Response::class, 'body');
        $prop->setAccessible(true);
        return (string)$prop->getValue($response);
    }
}
