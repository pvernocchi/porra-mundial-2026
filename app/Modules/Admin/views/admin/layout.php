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
    <a class="brand" href="<?= $base ?>/admin"><?= $e((string)$app->config()->get('site.name', 'Porra')) ?></a>
    <nav>
      <a href="<?= $base ?>/admin">Inicio</a>
      <a href="<?= $base ?>/admin/users">Usuarios</a>
      <a href="<?= $base ?>/admin/communications/smtp">Comunicaciones</a>
      <a href="<?= $base ?>/admin/security">Seguridad</a>
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
