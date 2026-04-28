<?php
/**
 * CLI upgrade script. Detects pending application and database updates
 * and applies them automatically.
 *
 * Usage:
 *   php bin/upgrade.php            # interactive — asks for confirmation
 *   php bin/upgrade.php --force    # non-interactive — applies immediately
 *
 * Exit codes:
 *   0 — success (upgrades applied or nothing to do)
 *   1 — error (app not installed, migration failure, etc.)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));

/** @var \App\Core\Application $app */
$app = require APP_ROOT . '/app/bootstrap.php';

$force = in_array('--force', $argv ?? [], true);

// ── Helpers ────────────────────────────────────────────────────────

function info(string $msg): void
{
    fwrite(STDOUT, $msg . "\n");
}

function error(string $msg): void
{
    fwrite(STDERR, "ERROR: " . $msg . "\n");
}

// ── Pre-checks ─────────────────────────────────────────────────────

if (!$app->isInstalled()) {
    error('La aplicación no está instalada. Ejecuta el instalador web primero (/install).');
    exit(1);
}

$codeVersion      = $app->version();
$installedVersion = $app->installedVersion() ?? '0.0.0';

$inst = new \App\Core\Installer($app);
$lastMigration   = $inst->lastAppliedMigration();
$latestMigration = $inst->latestMigrationVersion();

$hasVersionBump      = version_compare($codeVersion, $installedVersion, '>');
$hasPendingMigrations = (int)$latestMigration > (int)$lastMigration;

if (!$hasVersionBump && !$hasPendingMigrations) {
    info("✓ La aplicación está al día (versión {$installedVersion}, última migración {$lastMigration}).");
    exit(0);
}

// ── Show what will happen ──────────────────────────────────────────

info('╔══════════════════════════════════════════╗');
info('║   Porra Mundial 2026 — Actualización    ║');
info('╚══════════════════════════════════════════╝');
info('');

if ($hasVersionBump) {
    info("  Versión instalada : {$installedVersion}");
    info("  Versión nueva     : {$codeVersion}");
} else {
    info("  Versión           : {$installedVersion} (sin cambios)");
}

if ($hasPendingMigrations) {
    info("  Última migración  : {$lastMigration}");
    info("  Migraciones hasta : {$latestMigration}");

    // List the pending migration files.
    $allFiles = $inst->migrationFiles();
    $pending  = [];
    foreach ($allFiles as $file) {
        $prefix = explode('_', basename($file, '.sql'))[0] ?? '0000';
        if ((int)$prefix > (int)$lastMigration) {
            $pending[] = basename($file);
        }
    }
    info('');
    info('  Migraciones pendientes:');
    foreach ($pending as $f) {
        info("    • {$f}");
    }
} else {
    info('  No hay migraciones de base de datos pendientes.');
}

info('');

// ── Confirm ────────────────────────────────────────────────────────

if (!$force) {
    fwrite(STDOUT, '¿Aplicar la actualización? [s/N] ');
    $answer = strtolower(trim((string)fgets(STDIN)));
    if ($answer !== 's' && $answer !== 'si' && $answer !== 'sí' && $answer !== 'y' && $answer !== 'yes') {
        info('Actualización cancelada.');
        exit(0);
    }
}

// ── Apply ──────────────────────────────────────────────────────────

try {
    $dbConfig = (array)$app->config()->get('db', []);

    if ($hasPendingMigrations) {
        info('Aplicando migraciones de base de datos…');
        $result = $inst->runPendingMigrations($dbConfig);
        foreach ($result['files'] as $f) {
            info("  ✓ {$f}");
        }
        info("  {$result['applied']} migración(es) aplicada(s).");
    }

    // Update the installed.lock with new version and migration marker.
    $newVersion = $hasVersionBump ? $codeVersion : $installedVersion;
    $inst->writeInstalledLock($newVersion);

    info('');
    info("✓ Actualización completada — versión {$newVersion}.");
    exit(0);
} catch (\Throwable $e) {
    error('No se pudo completar la actualización: ' . $e->getMessage());
    if ((string)$app->config()->get('env', 'production') === 'development') {
        error($e->getTraceAsString());
    }
    exit(1);
}
