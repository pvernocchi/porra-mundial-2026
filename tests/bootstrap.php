<?php
declare(strict_types=1);

// PHPUnit bootstrap.
$root = dirname(__DIR__);

// Prefer Composer autoloader for PHPUnit when available.
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

// Always wire our own PSR-4 autoloader for App\* classes.
require_once $root . '/app/Core/Autoloader.php';
$loader = new \App\Core\Autoloader();
$loader->addNamespace('App', $root . '/app');
$loader->addNamespace('Tests', $root . '/tests');
$loader->register();
