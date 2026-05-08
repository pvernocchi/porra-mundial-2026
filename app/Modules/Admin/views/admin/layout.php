<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
$user  = $app->auth()->user();
$base  = $e($app->baseUrl());
$title = $title ?? (string)$app->config()->get('site.name', 'Porra');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($title) ?> · <?= $e((string)$app->config()->get('site.name', 'Porra')) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css">
</head>
<body class="admin">
<header class="topbar">
  <div class="container">
    <a class="brand" href="<?= $base ?>/home">
      <svg class="brand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14 14 0 0 1 0 20"/><path d="M12 2a14 14 0 0 0 0 20"/><path d="M2 12h20"/></svg>
      <?= $e((string)$app->config()->get('site.name', 'Porra')) ?>
    </a>
    <nav>
      <a href="<?= $base ?>/admin">Inicio</a>
      <a href="<?= $base ?>/admin/users">Usuarios</a>
      <a href="<?= $base ?>/admin/game/matches">Partidos</a>
      <a href="<?= $base ?>/admin/game/progress">Avances</a>
      <a href="<?= $base ?>/admin/reports">Reportes</a>
      <?php if ($app->auth()->isAdmin()): ?>
        <a href="<?= $base ?>/admin/communications/smtp">Comunicaciones</a>
        <a href="<?= $base ?>/admin/security">Seguridad</a>
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
  <small>v<?= $e($app->version()) ?></small>
</footer>
</body>
</html>
