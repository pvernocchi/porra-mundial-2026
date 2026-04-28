<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
$user  = $app->auth()->user();
$base  = $e($app->baseUrl());
$title = $title ?? 'Porra Mundial 2026';
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
    <a class="brand" href="<?= $base ?>/game/leaderboard"><?= $e((string)$app->config()->get('site.name', 'Porra')) ?></a>
    <nav>
      <a href="<?= $base ?>/game/picks">Mis equipos</a>
      <a href="<?= $base ?>/game/leaderboard">Clasificación</a>
      <a href="<?= $base ?>/game/results">Resultados</a>
      <?php if ($app->auth()->canManageUsers()): ?>
        <a href="<?= $base ?>/admin/game/matches">Admin partidos</a>
        <a href="<?= $base ?>/admin/game/progress">Admin avances</a>
        <a href="<?= $base ?>/admin">Panel</a>
      <?php endif ?>
    </nav>
    <div class="user">
      <?php if ($user !== null): ?>
        <a href="<?= $base ?>/account/mfa"><?= $e($user->fullName) ?></a>
        <form method="post" action="<?= $base ?>/logout" style="display:inline">
          <?= $app->csrf()->field() ?>
          <button class="btn btn-link" type="submit">Cerrar sesión</button>
        </form>
      <?php endif ?>
    </div>
  </div>
</header>
<main class="container">
  <h1><?= $e($title) ?></h1>
  <?php if (!empty($flash) && is_string($flash)): ?>
    <div class="alert alert-info"><?= $e($flash) ?></div>
  <?php endif ?>
  <?= $view->yield('content') ?>
</main>
<footer class="container muted">
  <hr>
  <small>Porra Mundial 2026 · v<?= $e($app->version()) ?></small>
</footer>
</body>
</html>
