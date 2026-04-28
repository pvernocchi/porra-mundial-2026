<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Core\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test: run all bundled migrations against an in-memory SQLite DB
 * and verify that the expected tables exist with their expected columns.
 */
final class InstallerSqliteTest extends TestCase
{
    public function testRunMigrationsCreatesAllTables(): void
    {
        $root = dirname(__DIR__);
        require_once $root . '/app/Core/Autoloader.php';
        $autoloader = new \App\Core\Autoloader();
        $autoloader->addNamespace('App', $root . '/app');
        $autoloader->register();

        $app = new Application($root);
        $inst = new Installer($app);

        $tmp = tempnam(sys_get_temp_dir(), 'sqlite');
        try {
            $cfg = ['driver' => 'sqlite', 'database' => $tmp, 'prefix' => 'pm_'];
            $count = $inst->runMigrations($cfg);
            $this->assertGreaterThanOrEqual(1, $count);

            $db = \App\Core\Database::fromConfig($cfg);
            foreach (['users', 'invitations', 'mfa_credentials', 'mfa_recovery_codes', 'settings', 'audit_log', 'password_resets'] as $t) {
                $row = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name = :n", ['n' => 'pm_' . $t]);
                $this->assertNotNull($row, "Table pm_{$t} should exist");
            }
        } finally {
            @unlink($tmp);
        }
    }
}
