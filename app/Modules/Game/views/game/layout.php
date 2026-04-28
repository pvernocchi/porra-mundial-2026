<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
$user  = $app->auth()->user();
$base  = $e($app->baseUrl());
$title = $title ?? 'Porra Mundial 2026';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$isActive = fn(string $path): string => str_contains($currentPath, $path) ? ' active' : '';
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
<header class="topbar">
  <div class="container">
    <a class="brand" href="<?= $base ?>/game/leaderboard">⚽ <?= $e((string)$app->config()->get('site.name', 'Porra')) ?></a>
    <nav>
      <a href="<?= $base ?>/game/picks" class="<?= $isActive('/game/picks') ?>">🎯 Mis equipos</a>
      <a href="<?= $base ?>/game/leaderboard" class="<?= $isActive('/game/leaderboard') ?>">🏆 Clasificación</a>
      <a href="<?= $base ?>/game/results" class="<?= $isActive('/game/results') ?>">📊 Resultados</a>
      <a href="<?= $base ?>/account/mfa" class="<?= $isActive('/account') ?>">⚙️ Mi cuenta</a>
      <?php if ($app->auth()->canManageUsers()): ?>
        <a href="<?= $base ?>/admin/game/matches" class="<?= $isActive('/admin/game/matches') ?>">🔧 Partidos</a>
        <a href="<?= $base ?>/admin/game/progress" class="<?= $isActive('/admin/game/progress') ?>">📋 Avances</a>
        <a href="<?= $base ?>/admin" class="<?= $isActive('/admin') && !str_contains($currentPath, '/admin/game') ? ' active' : '' ?>">🛡️ Admin</a>
      <?php endif ?>
    </nav>
    <div class="user">
      <?php if ($user !== null): ?>
        <span style="font-size:.9rem"><?= $e($user->fullName) ?></span>
        <form method="post" action="<?= $base ?>/logout" style="display:inline">
          <?= $app->csrf()->field() ?>
          <button class="btn btn-link" type="submit" style="font-size:.9rem">Salir</button>
        </form>
      <?php endif ?>
    </div>
  </div>
</header>
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
