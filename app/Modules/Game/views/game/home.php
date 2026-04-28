<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var \App\Models\User $user */
/** @var int $picksCount */
/** @var float $totalScore */
/** @var int $rank */
/** @var int $totalPlayers */
/** @var array<int, array{id: int, name: string, pot: int}> $pickedTeams */

use App\Core\Flags;

$view->extend('game.layout', ['title' => 'Inicio']);
$view->section('content');
$base = $e($app->baseUrl());
$isAdmin = $app->auth()->canManageUsers();
?>

<section class="home-welcome">
  <div class="home-welcome-icon">
    <svg viewBox="0 0 96 96" width="80" height="80" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="48" cy="48" r="44" fill="#deecf9" stroke="#0078d4" stroke-width="3"/>
      <path d="M48 12 L54 30 H72 L58 42 L64 60 L48 48 L32 60 L38 42 L24 30 H42 Z" fill="#0078d4" opacity=".9"/>
    </svg>
  </div>
  <h1>¡Hola, <?= $e($user->fullName) ?>!</h1>
  <p class="home-welcome-sub">Bienvenido a la Porra del Mundial 2026. Elige tus selecciones, sigue tus puntos y compite con tus colegas.</p>
</section>

<section class="home-stats">
  <div class="home-stat-card">
    <svg class="home-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <path d="M12 2a14 14 0 0 1 0 20"/>
      <path d="M12 2a14 14 0 0 0 0 20"/>
      <path d="M2 12h20"/>
    </svg>
    <div class="home-stat-value"><?= $picksCount ?>/6</div>
    <div class="home-stat-label">Equipos elegidos</div>
  </div>
  <div class="home-stat-card">
    <svg class="home-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
    </svg>
    <div class="home-stat-value"><?= number_format($totalScore, 1) ?></div>
    <div class="home-stat-label">Puntos totales</div>
  </div>
  <div class="home-stat-card">
    <svg class="home-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/>
      <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/>
      <path d="M4 22h16"/>
      <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 19.24 7 20v2"/>
      <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 19.24 17 20v2"/>
      <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
    </svg>
    <div class="home-stat-value"><?= $rank > 0 ? '#' . $rank : '—' ?></div>
    <div class="home-stat-label"><?= $totalPlayers > 0 ? 'de ' . $totalPlayers . ' jugadores' : 'Sin clasificación' ?></div>
  </div>
</section>

<?php if (!empty($pickedTeams)): ?>
<section class="home-teams-section">
  <h2 class="home-teams-title">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="9 11 12 14 22 4"/>
      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
    </svg>
    Tus Selecciones
  </h2>
  <div class="home-teams-grid">
    <?php foreach ($pickedTeams as $team): ?>
      <div class="home-team-card">
        <?= Flags::img($team['name'], 28) ?>
        <div class="team-info">
          <p class="team-name"><?= $e($team['name']) ?></p>
          <p class="team-pot">Bombo <?= $team['pot'] ?></p>
        </div>
      </div>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<section class="home-nav-cards">

  <a href="<?= $base ?>/game/picks" class="home-card home-card--picks">
    <div class="home-card-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 2a14 14 0 0 1 0 20"/>
        <path d="M12 2a14 14 0 0 0 0 20"/>
        <path d="M2 12h20"/>
      </svg>
    </div>
    <div class="home-card-body">
      <h2>Selecciones</h2>
      <p>Elige 1 equipo de cada bombo para competir en la porra</p>
    </div>
    <svg class="home-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
  </a>

  <a href="<?= $base ?>/game/my-scores" class="home-card home-card--scores">
    <div class="home-card-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

  <?php if ($isAdmin): ?>
    <a href="<?= $base ?>/admin" class="home-card home-card--admin">
      <div class="home-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
      </div>
      <div class="home-card-body">
        <h2>Administración</h2>
        <p>Gestiona partidos, usuarios y configuración del torneo</p>
      </div>
      <svg class="home-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
    </a>
  <?php endif ?>

</section>

<?php $view->endSection(); ?>
