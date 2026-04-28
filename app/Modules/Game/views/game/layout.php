<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
$user  = $app->auth()->user();
$base  = $e($app->baseUrl());
$title = $title ?? 'Porra Mundial 2026';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$isActive = fn(string $path): string => str_contains($currentPath, $path) ? ' active' : '';
$isHome = ($currentPath === $app->baseUrl() . '/home' || $currentPath === '/home');
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
    <a class="brand" href="<?= $base ?>/home">
      <svg class="brand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14 14 0 0 1 0 20"/><path d="M12 2a14 14 0 0 0 0 20"/><path d="M2 12h20"/></svg>
      <?= $e((string)$app->config()->get('site.name', 'Porra')) ?>
    </a>
    <button class="nav-toggle" aria-label="Menú" aria-expanded="false">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <nav id="main-nav">
      <a href="<?= $base ?>/home" class="<?= $isHome ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <span>Inicio</span>
      </a>
      <a href="<?= $base ?>/game/picks" class="<?= $isActive('/game/picks') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14 14 0 0 1 0 20"/><path d="M12 2a14 14 0 0 0 0 20"/><path d="M2 12h20"/></svg>
        <span>Selecciones</span>
      </a>
      <a href="<?= $base ?>/game/my-scores" class="<?= $isActive('/game/my-scores') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        <span>Mis Puntos</span>
      </a>
      <a href="<?= $base ?>/game/leaderboard" class="<?= $isActive('/game/leaderboard') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
        <span>Clasificación</span>
      </a>
      <a href="<?= $base ?>/game/results" class="<?= $isActive('/game/results') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
        <span>Resultados</span>
      </a>
      <a href="<?= $base ?>/account/mfa" class="<?= $isActive('/account') ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Mi Cuenta</span>
      </a>
      <?php if ($app->auth()->canManageUsers()): ?>
        <a href="<?= $base ?>/admin" class="nav-admin-link">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
          <span>Admin</span>
        </a>
      <?php endif ?>
    </nav>
    <div class="user">
      <?php if ($user !== null): ?>
        <span class="user-name"><?= $e($user->fullName) ?></span>
        <form method="post" action="<?= $base ?>/logout" style="display:inline">
          <?= $app->csrf()->field() ?>
          <button class="btn btn-link" type="submit">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Salir
          </button>
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
<script>
document.querySelector('.nav-toggle')?.addEventListener('click', function() {
  const nav = document.getElementById('main-nav');
  const expanded = this.getAttribute('aria-expanded') === 'true';
  this.setAttribute('aria-expanded', String(!expanded));
  nav.classList.toggle('open');
});
</script>
</body>
</html>
