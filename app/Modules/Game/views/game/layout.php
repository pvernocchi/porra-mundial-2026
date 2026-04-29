<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
$user  = $app->auth()->user();
$base  = $e($app->baseUrl());
$title = $title ?? 'Porra Mundial 2026';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$isActive = fn(string $path): string => str_contains($currentPath, $path) ? ' active' : '';
$isHome = str_contains($currentPath, '/home');
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
<header class="topbar">
  <div class="container">
    <a class="brand" href="<?= $base ?>/home">
      <span class="brand-ball">⚽</span>
      <span class="brand-text">
        <span><?= $e((string)$app->config()->get('site.name', 'Porra')) ?></span>
        <span class="brand-pill">Mundial 2026</span>
      </span>
    </a>
    <button class="nav-toggle" aria-label="Menú" aria-expanded="false">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <nav id="main-nav">
      <a href="<?= $base ?>/home" class="<?= $isHome ? 'active' : '' ?>">
        <span class="nav-emoji">🏠</span><span class="nav-label">Inicio</span>
      </a>
      <a href="<?= $base ?>/game/picks" class="<?= $isActive('/game/picks') ?>">
        <span class="nav-emoji">⚽</span><span class="nav-label">Selecciones</span>
      </a>
      <a href="<?= $base ?>/game/my-scores" class="<?= $isActive('/game/my-scores') ?>">
        <span class="nav-emoji">📊</span><span class="nav-label">Mis Puntos</span>
      </a>
      <a href="<?= $base ?>/game/leaderboard" class="<?= $isActive('/game/leaderboard') ?>">
        <span class="nav-emoji">🏆</span><span class="nav-label">Clasificación</span>
      </a>
      <a href="<?= $base ?>/game/results" class="<?= $isActive('/game/results') ?>">
        <span class="nav-emoji">📅</span><span class="nav-label">Resultados</span>
      </a>
      <a href="<?= $base ?>/account/mfa" class="<?= $isActive('/account') ?>">
        <span class="nav-emoji">👤</span><span class="nav-label">Mi Cuenta</span>
      </a>
      <?php if ($app->auth()->canManageUsers()): ?>
        <a href="<?= $base ?>/admin" class="nav-admin-link">
          <span class="nav-emoji">⚙️</span><span class="nav-label">Admin</span>
        </a>
      <?php endif ?>
    </nav>
    <div class="user">
      <?php if ($user !== null): ?>
        <div class="user-avatar" title="<?= $e($user->fullName) ?>"><?= $e($initials) ?></div>
        <span class="user-name"><?= $e($user->fullName) ?></span>
        <form method="post" action="<?= $base ?>/logout" style="display:inline">
          <?= $app->csrf()->field() ?>
          <button class="btn-logout" type="submit">🚪 Salir</button>
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
  <small>⚽ La Porra del Mundial 2026 · By JaimeSan & PVernocchi v · <?= $e($app->version()) ?></small>
</footer>
<script>
(function () {
  var btn = document.querySelector('.nav-toggle');
  var nav = document.getElementById('main-nav');
  if (!btn || !nav) return;
  btn.addEventListener('click', function () {
    var expanded = this.getAttribute('aria-expanded') === 'true';
    this.setAttribute('aria-expanded', String(!expanded));
    nav.classList.toggle('open');
  });
  document.addEventListener('click', function (e) {
    if (nav.classList.contains('open') && !nav.contains(e.target) && !btn.contains(e.target)) {
      nav.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
</body>
</html>
