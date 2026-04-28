<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, array<int, \App\Models\Team>> $teamsByPot */
/** @var array<int, int> $currentPicks */
/** @var bool $locked */
/** @var bool $saved */

use App\Core\Flags;

$potLabels = [
    1 => ['Bombo 1', 'Favoritos', '⭐'],
    2 => ['Bombo 2', 'Grandes aspirantes', '🔥'],
    3 => ['Bombo 3', 'Sólidos', '💪'],
    4 => ['Bombo 4', 'Competitivos', '⚡'],
    5 => ['Bombo 5', 'Outsiders', '🎲'],
    6 => ['Bombo 6', 'Improbables', '🌟'],
];

$view->extend('game.layout', ['title' => '🎯 Mis equipos']);
$view->section('content');
$base = $e($app->baseUrl());
?>

<div class="game-hero">
  <h1>🎯 Elige tus selecciones</h1>
  <p>Selecciona <strong>1 equipo de cada bombo</strong> (6 en total)</p>
</div>

<?php if ($saved): ?>
  <div class="alert alert-success">✅ ¡Tus equipos se han guardado correctamente!</div>
<?php endif ?>

<?php if ($locked): ?>
  <div class="alert alert-warning">🔒 Las selecciones están bloqueadas. Ya no se pueden modificar.</div>
<?php endif ?>

<form method="post" action="<?= $base ?>/game/picks">
  <?= $app->csrf()->field() ?>

  <?php foreach ($teamsByPot as $pot => $teams): ?>
    <?php $info = $potLabels[$pot] ?? ['Bombo ' . $pot, '', '📌']; ?>
    <fieldset <?= $locked ? 'disabled' : '' ?>>
      <legend><?= $info[2] ?> <?= $e($info[0]) ?> — <?= $e($info[1]) ?></legend>
      <div class="pick-grid">
        <?php foreach ($teams as $team): ?>
          <label class="pick-option">
            <input type="radio"
                   name="pot_<?= $pot ?>"
                   value="<?= $team->id ?>"
                   <?= (isset($currentPicks[$pot]) && $currentPicks[$pot] === $team->id) ? 'checked' : '' ?>
                   required>
            <?= Flags::img($team->name, 24) ?>
            <span><?= $e($team->name) ?></span>
          </label>
        <?php endforeach ?>
      </div>
    </fieldset>
  <?php endforeach ?>

  <?php if (!$locked): ?>
    <div style="text-align:center;margin:1.5rem 0">
      <button type="submit" class="btn btn-primary" style="padding:.7rem 2rem;font-size:1.05rem">
        💾 Guardar selección
      </button>
    </div>
  <?php endif ?>
</form>

<?php $view->endSection(); ?>
