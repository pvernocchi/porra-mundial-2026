<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var \App\Models\User $user */
/** @var array{total: float, rank: int, total_players: int, teams: array} $breakdown */

use App\Core\Flags;

$potLabels = [
    1 => 'Bombo 1 — Favoritos',
    2 => 'Bombo 2 — Grandes aspirantes',
    3 => 'Bombo 3 — Sólidos',
    4 => 'Bombo 4 — Competitivos',
    5 => 'Bombo 5 — Outsiders',
    6 => 'Bombo 6 — Improbables',
];

$view->extend('game.layout', ['title' => 'Mis Puntos']);
$view->section('content');
$base = $e($app->baseUrl());
?>

<div class="game-hero">
  <h1>
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.3em;height:1.3em"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    Mis Puntos
  </h1>
  <p>Desglose detallado de tu puntuación por equipo</p>
</div>

<section class="score-summary-bar">
  <div class="score-summary-item">
    <span class="score-summary-label">Total</span>
    <span class="score-summary-value score-summary-total"><?= number_format($breakdown['total'], 1) ?></span>
  </div>
  <div class="score-summary-item">
    <span class="score-summary-label">Posición</span>
    <span class="score-summary-value"><?= $breakdown['rank'] > 0 ? '#' . $breakdown['rank'] : '—' ?></span>
  </div>
  <div class="score-summary-item">
    <span class="score-summary-label">Jugadores</span>
    <span class="score-summary-value"><?= $breakdown['total_players'] ?></span>
  </div>
</section>

<?php if ($breakdown['teams'] === []): ?>
  <div class="alert alert-info">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.1em;height:1.1em;vertical-align:-.15em"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
    Aún no has seleccionado equipos. <a href="<?= $base ?>/game/picks">Elige tus selecciones</a>.
  </div>
<?php else: ?>
  <div class="score-team-cards">
    <?php foreach ($breakdown['teams'] as $team): ?>
      <div class="score-team-card">
        <div class="score-team-header">
          <div class="score-team-flag"><?= Flags::img($team['team_name'], 32) ?></div>
          <div>
            <h3><?= $e($team['team_name']) ?></h3>
            <span class="score-team-pot"><?= $e($potLabels[$team['pot']] ?? 'Bombo ' . $team['pot']) ?></span>
          </div>
          <div class="score-team-total"><?= number_format($team['total'], 1) ?></div>
        </div>
        <div class="score-team-breakdown">
          <div class="score-row">
            <span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
              Partidos
            </span>
            <span class="<?= $team['match_pts'] >= 0 ? 'pts-positive' : 'pts-negative' ?>"><?= $team['match_pts'] >= 0 ? '+' : '' ?><?= number_format($team['match_pts'], 1) ?></span>
          </div>
          <div class="score-row">
            <span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
              Avances
            </span>
            <span class="<?= $team['progress_pts'] >= 0 ? 'pts-positive' : 'pts-negative' ?>"><?= $team['progress_pts'] >= 0 ? '+' : '' ?><?= number_format($team['progress_pts'], 1) ?></span>
          </div>
          <div class="score-row">
            <span>
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              Premios
            </span>
            <span class="<?= $team['award_pts'] >= 0 ? 'pts-positive' : 'pts-negative' ?>"><?= $team['award_pts'] >= 0 ? '+' : '' ?><?= number_format($team['award_pts'], 1) ?></span>
          </div>
        </div>
      </div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<?php $view->endSection(); ?>
