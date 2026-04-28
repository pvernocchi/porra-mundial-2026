<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, \App\Models\GameMatch> $matches */

use App\Core\Flags;

$phaseLabels = [
    'group'       => 'Fase de grupos',
    'round_of_32' => 'Dieciseisavos',
    'round_of_16' => 'Octavos de final',
    'quarter'     => 'Cuartos de final',
    'semi'        => 'Semifinales',
    'third_place' => 'Tercer puesto',
    'final'       => 'Final',
];

$view->extend('game.layout', ['title' => '📊 Resultados']);
$view->section('content');
?>

<div class="game-hero">
  <h1>📊 Resultados</h1>
  <p>Partidos jugados en el torneo</p>
</div>

<?php if ($matches === []): ?>
  <div class="alert alert-info">Aún no hay resultados registrados.</div>
<?php else: ?>
  <?php foreach ($matches as $m): ?>
    <div class="match-card">
      <div class="match-team">
        <?= Flags::img($m->homeTeamName, 28) ?>
        <span><?= $e($m->homeTeamName) ?></span>
      </div>
      <div>
        <div class="match-score"><?= $m->homeGoals ?? '—' ?> – <?= $m->awayGoals ?? '—' ?></div>
        <div class="match-meta">
          <span class="phase-badge phase-<?= $e($m->phase) ?>"><?= $e($phaseLabels[$m->phase] ?? $m->phase) ?></span>
        </div>
      </div>
      <div class="match-team away">
        <?= Flags::img($m->awayTeamName, 28) ?>
        <span><?= $e($m->awayTeamName) ?></span>
      </div>
    </div>
  <?php endforeach ?>
<?php endif ?>

<?php $view->endSection(); ?>
