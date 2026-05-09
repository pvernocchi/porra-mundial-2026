<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Database;
use App\Core\Installer;
use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Modules\Auth\MfaController;
use PHPUnit\Framework\TestCase;

final class AccountPreferencesTest extends TestCase
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

        $_SESSION = [];

        $this->dbFile = tempnam(sys_get_temp_dir(), 'sqlite');
        $cfg = ['driver' => 'sqlite', 'database' => $this->dbFile, 'prefix' => 'pm_'];
        (new Installer(new Application($root)))->runMigrations($cfg);
        $this->db = Database::fromConfig($cfg);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbFile);
    }

    public function testLoggedUserCanUpdateTeamName(): void
    {
        $userModel = new User($this->db);
        $userId = $userModel->create('Player One', 'player1@example.com', 'OldPass123!', 'user', '');

        $app = $this->applicationWithDatabase();
        $app->session()->set('_user_id', $userId);
        $token = $app->csrf()->token();

        $response = (new MfaController($app))->updateTeamName(new Request(post: [
            '_token' => $token,
            'team_name' => 'Los Campeones',
        ]));

        $this->assertSame(302, $this->responseStatus($response));
        $this->assertSame($app->baseUrl() . '/account/mfa', $this->responseHeader($response, 'Location'));
        $this->assertSame('Los Campeones', (new User($this->db))->find($userId)?->teamName);
    }

    public function testLoggedUserCanChangePassword(): void
    {
        $userModel = new User($this->db);
        $userId = $userModel->create('Player Two', 'player2@example.com', 'OldPass123!', 'user', '');
        $before = $userModel->find($userId);
        $this->assertNotNull($before);

        $app = $this->applicationWithDatabase();
        $app->session()->set('_user_id', $userId);
        $token = $app->csrf()->token();

        $response = (new MfaController($app))->changeOwnPassword(new Request(post: [
            '_token' => $token,
            'current_password' => 'OldPass123!',
            'password' => 'NewPass123!',
            'password_confirm' => 'NewPass123!',
        ]));

        $after = (new User($this->db))->find($userId);
        $this->assertNotNull($after);
        $this->assertSame(302, $this->responseStatus($response));
        $this->assertSame($app->baseUrl() . '/account/mfa', $this->responseHeader($response, 'Location'));
        $this->assertNotSame($before->passwordHash, $after->passwordHash);
        $this->assertTrue(Password::verify('NewPass123!', $after->passwordHash));
    }

    public function testPasswordIsNotChangedWhenCurrentPasswordIsWrong(): void
    {
        $userModel = new User($this->db);
        $userId = $userModel->create('Player Three', 'player3@example.com', 'OldPass123!', 'user', '');
        $before = $userModel->find($userId);
        $this->assertNotNull($before);

        $app = $this->applicationWithDatabase();
        $app->session()->set('_user_id', $userId);
        $token = $app->csrf()->token();

        $response = (new MfaController($app))->changeOwnPassword(new Request(post: [
            '_token' => $token,
            'current_password' => 'IncorrectPass!',
            'password' => 'NewPass123!',
            'password_confirm' => 'NewPass123!',
        ]));

        $after = (new User($this->db))->find($userId);
        $this->assertNotNull($after);
        $this->assertSame(302, $this->responseStatus($response));
        $this->assertSame($before->passwordHash, $after->passwordHash);
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

    private function responseHeader(Response $response, string $name): ?string
    {
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $prop->setAccessible(true);
        $headers = (array)$prop->getValue($response);
        return isset($headers[$name]) ? (string)$headers[$name] : null;
    }
}
