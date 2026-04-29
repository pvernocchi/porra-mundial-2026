<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
$user  = $app->auth()->user();
$base  = $e($app->baseUrl());
$title = $title ?? 'Porra Mundial 2026';
// User avatar initials
$initials = '';
if ($user !== null) {
    $parts    = array_values(array_filter(explode(' ', trim($user->fullName))));
    $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($title) ?> · <?= $e((string)$app->config()->get('site.name', 'Porra')) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css">
</head>
<body>
<main class="container">
  <?php if (!empty($flash) && is_string($flash)): ?>
    <div class="alert alert-info"><?= $e($flash) ?></div>
  <?php endif ?>
  <?= $view->yield('content') ?>
</main>
<footer class="container muted" style="text-align:center">
  <hr>
  <small>⚽ Porra Mundial 2026 · v<?= $e($app->version()) ?></small>
</footer>
</body>
</html>
