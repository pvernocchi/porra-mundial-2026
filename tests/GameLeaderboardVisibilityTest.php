<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Installer;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Game\GameController;
use PHPUnit\Framework\TestCase;

final class GameLeaderboardVisibilityTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $repoRoot = dirname(__DIR__);
        $this->tmpRoot = sys_get_temp_dir() . '/pm_game_leaderboard_' . bin2hex(random_bytes(6));

        mkdir($this->tmpRoot, 0777, true);
        mkdir($this->tmpRoot . '/config', 0777, true);
        mkdir($this->tmpRoot . '/storage/cache/ratelimit', 0777, true);

        symlink($repoRoot . '/app', $this->tmpRoot . '/app');
        symlink($repoRoot . '/database', $this->tmpRoot . '/database');
        symlink($repoRoot . '/VERSION', $this->tmpRoot . '/VERSION');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpRoot);
        parent::tearDown();
    }

    public function testLeaderboardIsHiddenWhilePicksAreOpen(): void
    {
        $app = $this->buildAppWithPicksLockedSetting('0');
        $controller = new GameController($app);

        $response = $controller->leaderboard(new Request([], [], ['REQUEST_URI' => '/game/leaderboard']));
        $html = $this->responseBody($response);

        $this->assertStringContainsString(
            'La clasificación estará disponible una vez finalizado el período de elección de selecciones nacionales.',
            $html
        );
        $this->assertStringNotContainsString('leaderboard-table', $html);
    }

    public function testLeaderboardRemainsVisibleWhenPicksAreLocked(): void
    {
        $app = $this->buildAppWithPicksLockedSetting('1');
        $controller = new GameController($app);

        $response = $controller->leaderboard(new Request([], [], ['REQUEST_URI' => '/game/leaderboard']));
        $html = $this->responseBody($response);

        $this->assertStringContainsString('Aún no hay participantes con equipos seleccionados.', $html);
        $this->assertStringNotContainsString(
            'La clasificación estará disponible una vez finalizado el período de elección de selecciones nacionales.',
            $html
        );
    }

    private function buildAppWithPicksLockedSetting(string $value): Application
    {
        $dbPath = $this->tmpRoot . '/storage/test.sqlite';
        $config = [
            'db' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => 'pm_',
            ],
            'site' => [
                'name' => 'Porra',
                'base_url' => 'http://localhost',
            ],
            'session' => [
                'name' => 'porra_test',
                'idle' => 3600,
            ],
        ];

        file_put_contents($this->tmpRoot . '/config/config.php', "<?php\nreturn " . var_export($config, true) . ";\n");
        file_put_contents($this->tmpRoot . '/storage/installed.lock', json_encode(['version' => 'test'], JSON_THROW_ON_ERROR));

        $app = new Application($this->tmpRoot);
        $installer = new Installer($app);
        $installer->runMigrations($config['db']);

        $app->settings()->set('game.picks_locked', $value);

        return $app;
    }

    private function responseBody(Response $response): string
    {
        $ref = new \ReflectionProperty($response, 'body');
        $ref->setAccessible(true);
        return (string)$ref->getValue($response);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
