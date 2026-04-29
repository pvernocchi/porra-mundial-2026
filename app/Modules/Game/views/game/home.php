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
$base    = $e($app->baseUrl());
$isAdmin = $app->auth()->canManageUsers();

$rankLabel  = $rank > 0 ? '#' . $rank : '—';
$rankSub    = $totalPlayers > 0 ? 'de ' . $totalPlayers . ' jugadores' : 'Sin clasificación';
$picksDone  = $picksCount >= 6;
?>

<!-- ░░ HERO ░░ -->
<section class="h-hero">
  <div class="h-hero-bg"></div>
  <div class="h-hero-content">
    <div class="h-hero-balls" aria-hidden="true">⚽ 🏆 🌍</div>
    <h1 class="h-hero-title">¡Hola, <?= $e($user->fullName) ?>! 👋</h1>
    <p class="h-hero-sub">
      Bienvenido a la <strong>Porra del Mundial 2026</strong>.<br>
      Elige tus equipos, acumula puntos y sube al podio. 🚀
    </p>
    <?php if (!$picksDone): ?>
      <a href="<?= $base ?>/game/picks" class="h-hero-cta">
        ✏️ Completar mis selecciones &rarr;
      </a>
    <?php else: ?>
      <span class="h-hero-badge">✅ ¡Selecciones completas!</span>
    <?php endif ?>
  </div>
</section>

<!-- ░░ STATS ░░ -->
<section class="h-stats">
  <div class="h-stat h-stat--picks">
    <span class="h-stat-emoji">✅</span>
    <span class="h-stat-value"><?= $picksCount ?><small>/6</small></span>
    <span class="h-stat-label">Equipos elegidos</span>
  </div>
  <div class="h-stat h-stat--score">
    <span class="h-stat-emoji">⭐</span>
    <span class="h-stat-value"><?= number_format($totalScore, 1) ?></span>
    <span class="h-stat-label">Puntos totales</span>
  </div>
  <div class="h-stat h-stat--rank">
    <span class="h-stat-emoji">🏅</span>
    <span class="h-stat-value"><?= $rankLabel ?></span>
    <span class="h-stat-label"><?= $rankSub ?></span>
  </div>
</section>

<!-- ░░ NAV CARDS ░░ -->
<section class="h-cards">

  <a href="<?= $base ?>/game/picks" class="h-card h-card--picks">
    <span class="h-card-emoji">⚽</span>
    <div class="h-card-body">
      <h2>Selecciones</h2>
      <p>Elige 1 equipo de cada bombo para competir en la porra</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/game/my-scores" class="h-card h-card--scores">
    <span class="h-card-emoji">📊</span>
    <div class="h-card-body">
      <h2>Mis Puntos</h2>
      <p>Consulta tu puntuación detallada y desglose por equipo</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/game/leaderboard" class="h-card h-card--leaderboard">
    <span class="h-card-emoji">🏆</span>
    <div class="h-card-body">
      <h2>Clasificación</h2>
      <p>Mira el ranking general y compara con otros jugadores</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/game/results" class="h-card h-card--results">
    <span class="h-card-emoji">📅</span>
    <div class="h-card-body">
      <h2>Resultados</h2>
      <p>Consulta los partidos jugados y sus marcadores</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/account/mfa" class="h-card h-card--account">
    <span class="h-card-emoji">👤</span>
    <div class="h-card-body">
      <h2>Mi Cuenta</h2>
      <p>Configura tu seguridad y autenticación en dos pasos</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <?php if ($isAdmin): ?>
  <a href="<?= $base ?>/admin" class="h-card h-card--admin">
    <span class="h-card-emoji">⚙️</span>
    <div class="h-card-body">
      <h2>Administración</h2>
      <p>Gestiona partidos, usuarios y configuración del torneo</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>
  <?php endif ?>

</section>

<!-- ░░ PICKED TEAMS ░░ -->
<?php if (!empty($pickedTeams)): ?>
<section class="h-teams">
  <h2 class="h-teams-title">🎯 Tus Selecciones</h2>
  <div class="h-teams-grid">
    <?php foreach ($pickedTeams as $team): ?>
      <div class="h-team-card">
        <span class="h-team-pot-badge">Bombo <?= $team['pot'] ?></span>
        <?= Flags::img($team['name'], 32) ?>
        <span class="h-team-name"><?= $e($team['name']) ?></span>
      </div>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<?php $view->endSection(); ?>
