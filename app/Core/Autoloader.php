<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-4 autoloader. Used when Composer's autoloader is not
 * available (e.g. on a shared host where composer install is not run).
 *
 * If vendor/autoload.php exists, the bootstrap will load it instead so
 * Composer-managed dependencies (PHPMailer, web-auth) are also wired up.
 */
final class Autoloader
{
    /** @var array<string, string> */
    private array $prefixes = [];

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix   = trim($prefix, '\\') . '\\';
        $baseDir  = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->prefixes[$prefix] = $baseDir;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (str_starts_with($class, $prefix)) {
                $relative = substr($class, strlen($prefix));
                $file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return true;
                }
            }
        }
        return false;
    }
}
