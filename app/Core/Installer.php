<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Installer helper. Owns the side-effects of "first install":
 *   - preflight checks
 *   - testing the DB connection given a candidate config
 *   - applying migrations
 *   - writing config/config.php and storage/installed.lock
 *   - upgrade mode (apply pending migrations against an installed app)
 */
final class Installer
{
    public function __construct(private Application $app)
    {
    }

    /**
     * @return array<int, array{key:string, label:string, ok:bool, hint?:string}>
     */
    public function preflight(): array
    {
        $checks = [];

        $checks[] = [
            'key' => 'php_version',
            'label' => 'PHP >= 8.1',
            'ok'    => version_compare(PHP_VERSION, '8.1.0', '>='),
            'hint'  => 'Versión actual: ' . PHP_VERSION,
        ];

        foreach (['pdo_mysql', 'openssl', 'mbstring', 'curl', 'sodium'] as $ext) {
            $checks[] = [
                'key'   => 'ext_' . $ext,
                'label' => 'Extensión PHP: ' . $ext,
                'ok'    => extension_loaded($ext),
                'hint'  => extension_loaded($ext) ? '' : 'Pídele a tu hosting que la habilite.',
            ];
        }

        $checks[] = [
            'key'   => 'ext_gd',
            'label' => 'Extensión PHP: gd (opcional, para QR de TOTP)',
            'ok'    => extension_loaded('gd'),
            'hint'  => extension_loaded('gd') ? '' : 'Sin gd, el QR no se renderizará en servidor (se usará un servicio externo o el secreto en texto).',
        ];

        $writableConfig  = is_writable($this->app->path('config'));
        $writableStorage = is_writable($this->app->path('storage'));
        $checks[] = ['key' => 'writable_config', 'label' => 'Carpeta /config escribible', 'ok' => $writableConfig, 'hint' => $writableConfig ? '' : 'Cambia los permisos a 775 desde cPanel/FTP.'];
        $checks[] = ['key' => 'writable_storage', 'label' => 'Carpeta /storage escribible', 'ok' => $writableStorage, 'hint' => $writableStorage ? '' : 'Cambia los permisos a 775 desde cPanel/FTP.'];

        return $checks;
    }

    public function preflightOk(): bool
    {
        foreach ($this->preflight() as $c) {
            if (!$c['ok'] && !str_contains($c['label'], 'opcional')) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $dbConfig
     * @return array{ok:bool, error?:string}
     */
    public function testDbConnection(array $dbConfig): array
    {
        try {
            Database::fromConfig($dbConfig);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Run all migration files in order.
     *
     * @param array<string, mixed> $dbConfig
     */
    public function runMigrations(array $dbConfig): int
    {
        $db = Database::fromConfig($dbConfig);
        $applied = 0;
        foreach ($this->migrationFiles() as $file) {
            $sql = (string)file_get_contents($file);
            $sql = $this->normaliseSql($sql, $db->driver());
            $sql = $db->rewrite($sql);
            // Split on `;` at end of line — naive but enough for our schema.
            $statements = array_filter(array_map('trim', preg_split('/;\s*\n/m', $sql) ?: []));
            foreach ($statements as $stmt) {
                if ($stmt === '') {
                    continue;
                }
                $db->pdo()->exec($stmt);
            }
            $applied++;
        }
        return $applied;
    }

    /**
     * @return array<int, string>
     */
    public function migrationFiles(): array
    {
        $dir = $this->app->path('database/migrations');
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    public function latestMigrationVersion(): string
    {
        $files = $this->migrationFiles();
        if ($files === []) {
            return '0000';
        }
        $base = basename(end($files), '.sql');
        return explode('_', $base)[0] ?? '0000';
    }

    /**
     * Normalise SQL so it works on both MySQL and SQLite (used by tests
     * and dev sandboxes). Strips MySQL-specific clauses for SQLite.
     */
    private function normaliseSql(string $sql, string $driver): string
    {
        if ($driver !== 'sqlite') {
            return $sql;
        }
        $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql) ?? $sql;
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\bENGINE=\w+\s*/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\bDEFAULT\s+CHARSET=\w+\s*/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\bCOLLATE=\w+\s*/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\bTINYINT\(\d+\)\b/i', 'INTEGER', $sql) ?? $sql;
        return $sql;
    }

    /**
     * Persist config/config.php using the provided settings.
     *
     * @param array<string, mixed> $payload
     */
    public function writeConfig(array $payload): void
    {
        $tpl = "<?php\nreturn " . $this->varExport($payload) . ";\n";
        $file = $this->app->path('config/config.php');
        if (file_put_contents($file, $tpl, LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo escribir config/config.php (permisos?).');
        }
        @chmod($file, 0600);
    }

    public function writeInstalledLock(string $version): void
    {
        $file = $this->app->path('storage/installed.lock');
        $payload = [
            'version'    => $version,
            'installed_at' => gmdate('c'),
            'php'        => PHP_VERSION,
        ];
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT) ?: '{}', LOCK_EX);
        @chmod($file, 0640);
    }

    /**
     * Pretty-prints arrays as `[]`-style PHP code (var_export uses array(...)).
     */
    private function varExport(mixed $value, int $indent = 0): string
    {
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            $pad = str_repeat('    ', $indent + 1);
            $out = "[\n";
            foreach ($value as $k => $v) {
                $out .= $pad;
                if (!$isList) {
                    $out .= var_export($k, true) . ' => ';
                }
                $out .= $this->varExport($v, $indent + 1) . ",\n";
            }
            $out .= str_repeat('    ', $indent) . ']';
            return $out;
        }
        return var_export($value, true);
    }
}
