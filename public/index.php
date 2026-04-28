<?php
declare(strict_types=1);

/**
 * Front controller. Loads the app, then dispatches the request through
 * the router. If the application is not installed yet, every request
 * is redirected to /install.
 */

/** @var \App\Core\Application $app */
$app = require __DIR__ . '/../app/bootstrap.php';

// Apply timezone from config (or fall back to UTC).
$tz = (string)$app->config()->get('site.timezone', 'UTC');
@date_default_timezone_set($tz);

$request = \App\Core\Request::fromGlobals();

// First-run / upgrade redirects.
if (!$app->isInstalled()
    && !str_starts_with($request->path(), '/install')
    && !str_starts_with($request->path(), '/assets')
) {
    (new \App\Core\Response())
        ->redirect($app->baseUrl() . '/install')
        ->send();
    return;
}

if ($app->isInstalled()) {
    $codeVer = $app->version();
    $haveVer = $app->installedVersion() ?? '0.0.0';
    if (version_compare($codeVer, $haveVer, '>')
        && !str_starts_with($request->path(), '/install/upgrade')
        && !str_starts_with($request->path(), '/login')
        && !str_starts_with($request->path(), '/assets')
    ) {
        (new \App\Core\Response())
            ->redirect($app->baseUrl() . '/install/upgrade')
            ->send();
        return;
    }
}

// Build router and dispatch.
/** @var \App\Core\Router $router */
$router = (require __DIR__ . '/../app/routes.php')($app);

try {
    $response = $router->dispatch($request, $app);
} catch (\Throwable $e) {
    $errorId = bin2hex(random_bytes(4));
    @file_put_contents(
        $app->path('storage/logs/app.log'),
        sprintf("[%s] %s %s\n%s\n", gmdate('c'), $errorId, $e->getMessage(), $e->getTraceAsString()),
        FILE_APPEND
    );
    $isDev = (string)$app->config()->get('env', 'production') === 'development';
    $detail = $isDev ? '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES) . '</pre>' : '';
    $response = (new \App\Core\Response())->html(
        '<!doctype html><meta charset="utf-8"><title>Error</title><h1>Error interno</h1>' .
        '<p>Algo salió mal. Referencia: <code>' . $errorId . '</code>.</p>' . $detail,
        500
    );
}
$response->send();
