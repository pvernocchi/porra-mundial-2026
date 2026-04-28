<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var \App\Models\User $user */
/** @var int $picksCount */
/** @var float $totalScore */
/** @var int $rank */
/** @var int $totalPlayers */

$view->extend('game.layout', ['title' => 'Inicio']);
$view->section('content');
$base = $e($app->baseUrl());
?>

<section class="home-welcome">
  <div class="home-welcome-icon">
    <svg viewBox="0 0 64 64" width="64" height="64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="32" cy="32" r="30" fill="#e0f2fe" stroke="#0284c7" stroke-width="2"/>
      <path d="M32 8 L36 20 H48 L38 28 L42 40 L32 32 L22 40 L26 28 L16 20 H28 Z" fill="#0284c7" opacity=".85"/>
    </svg>
  </div>
  <h1>¡Hola, <?= $e($user->fullName) ?>!</h1>
  <p class="home-welcome-sub">Bienvenido a la Porra del Mundial 2026. Elige tus selecciones, sigue tus puntos y compite.</p>
</section>

<section class="home-stats">
  <div class="home-stat-card">
    <svg class="home-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    <div class="home-stat-value"><?= $picksCount ?>/6</div>
    <div class="home-stat-label">Equipos elegidos</div>
  </div>
  <div class="home-stat-card">
    <svg class="home-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    <div class="home-stat-value"><?= number_format($totalScore, 1) ?></div>
    <div class="home-stat-label">Puntos totales</div>
  </div>
  <div class="home-stat-card">
    <svg class="home-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M4 22h16"/><path d="M10 22V8a4 4 0 0 0-4-4"/><path d="M14 22V8a4 4 0 0 1 4-4"/><path d="M10 14h4"/></svg>
    <div class="home-stat-value"><?= $rank > 0 ? '#' . $rank : '—' ?></div>
    <div class="home-stat-label"><?= $totalPlayers > 0 ? 'de ' . $totalPlayers . ' jugadores' : 'Sin clasificación' ?></div>
  </div>
</section>

<section class="home-nav-cards">

  <a href="<?= $base ?>/game/picks" class="home-card home-card--picks">
    <div class="home-card-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 2a14 14 0 0 1 0 20"/>
        <path d="M12 2a14 14 0 0 0 0 20"/>
        <path d="M2 12h20"/>
      </svg>
    </div>
    <div class="home-card-body">
      <h2>Mis Selecciones</h2>
      <p>Elige 1 equipo de cada bombo para competir en la porra</p>
    </div>
    <svg class="home-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
  </a>

  <a href="<?= $base ?>/game/my-scores" class="home-card home-card--scores">
    <div class="home-card-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
      </svg>
    </div>
    <div class="home-card-body">
      <h2>Mis Puntos</h2>
      <p>Consulta tu puntuación detallada y desglose por equipo</p>
    </div>
    <svg class="home-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
  </a>

  <a href="<?= $base ?>/game/leaderboard" class="home-card home-card--leaderboard">
    <div class="home-card-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/>
        <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/>
        <path d="M4 22h16"/>
        <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 19.24 7 20v2"/>
        <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 19.24 17 20v2"/>
        <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
      </svg>
    </div>
    <div class="home-card-body">
      <h2>Clasificación</h2>
      <p>Mira el ranking general y compara con otros jugadores</p>
    </div>
    <svg class="home-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
  </a>

  <a href="<?= $base ?>/account/mfa" class="home-card home-card--account">
    <div class="home-card-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
    </div>
    <div class="home-card-body">
      <h2>Mi Cuenta</h2>
      <p>Configura tu perfil, seguridad y autenticación</p>
    </div>
    <svg class="home-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
  </a>

</section>

<?php $view->endSection(); ?>
