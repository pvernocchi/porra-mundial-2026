<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, array<int, \App\Models\Team>> $teamsByPot */
/** @var array<int, int> $currentPicks */
/** @var bool $locked */
/** @var bool $saved */

$potLabels = [
    1 => 'Bombo 1 – Favoritos',
    2 => 'Bombo 2 – Grandes aspirantes',
    3 => 'Bombo 3 – Sólidos',
    4 => 'Bombo 4 – Competitivos',
    5 => 'Bombo 5 – Outsiders',
    6 => 'Bombo 6 – Improbables',
];

$view->extend('game.layout', ['title' => 'Mis equipos']);
$view->section('content');
$base = $e($app->baseUrl());
?>

<?php if ($saved): ?>
  <div class="alert alert-info">✅ Tus equipos se han guardado correctamente.</div>
<?php endif ?>

<?php if ($locked): ?>
  <div class="alert alert-info">🔒 Las selecciones están bloqueadas. Ya no se pueden modificar.</div>
<?php endif ?>

<p>Elige <strong>1 selección de cada bombo</strong> (6 equipos en total):</p>

<form method="post" action="<?= $base ?>/game/picks">
  <?= $app->csrf()->field() ?>

  <?php foreach ($teamsByPot as $pot => $teams): ?>
    <fieldset <?= $locked ? 'disabled' : '' ?>>
      <legend><?= $e($potLabels[$pot] ?? 'Bombo ' . $pot) ?></legend>
      <div class="pick-grid">
        <?php foreach ($teams as $team): ?>
          <label class="pick-option">
            <input type="radio"
                   name="pot_<?= $pot ?>"
                   value="<?= $team->id ?>"
                   <?= (isset($currentPicks[$pot]) && $currentPicks[$pot] === $team->id) ? 'checked' : '' ?>
                   required>
            <span><?= $e($team->name) ?></span>
          </label>
        <?php endforeach ?>
      </div>
    </fieldset>
  <?php endforeach ?>

  <?php if (!$locked): ?>
    <button type="submit" class="btn btn-primary">💾 Guardar selección</button>
  <?php endif ?>
</form>

<?php $view->endSection(); ?>
