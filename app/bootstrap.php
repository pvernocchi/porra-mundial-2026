<?php
declare(strict_types=1);

/**
 * Bootstrap: defines paths, sets up autoloading, error handling and
 * default services. Returns the configured Application instance.
 *
 * Used by both public/index.php (the front controller) and tests.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Always run from a known timezone unless config overrides it later.
date_default_timezone_set('UTC');

// Strict error reporting; the Application will install proper handlers.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Prefer Composer autoload when available (production zip / dev with composer install).
$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Always register our own PSR-4 autoloader for App\* classes — works even
// when composer install has not been run, which is the common case on
// shared hosting (FTP-only deploy).
require_once APP_ROOT . '/app/Core/Autoloader.php';
$autoloader = new \App\Core\Autoloader();
$autoloader->addNamespace('App', APP_ROOT . '/app');
$autoloader->register();

// Build and return the application kernel.
return new \App\Core\Application(APP_ROOT);
