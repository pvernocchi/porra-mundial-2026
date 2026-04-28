<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var \App\Models\GameMatch $match */
/** @var array<int, \App\Models\Team> $teams */

$phaseLabels = [
    'group'       => 'Fase de grupos',
    'round_of_32' => 'Dieciseisavos',
    'round_of_16' => 'Octavos de final',
    'quarter'     => 'Cuartos de final',
    'semi'        => 'Semifinales',
    'third_place' => 'Tercer puesto',
    'final'       => 'Final',
];

$view->extend('admin.layout', ['title' => 'Editar partido: ' . $match->homeTeamName . ' vs ' . $match->awayTeamName]);
$view->section('content');
$base = $e($app->baseUrl());
?>

<form method="post" action="<?= $base ?>/admin/game/matches/<?= $match->id ?>">
  <?= $app->csrf()->field() ?>

  <p><strong><?= $e($match->homeTeamName) ?></strong> vs <strong><?= $e($match->awayTeamName) ?></strong>
     — <?= $e($phaseLabels[$match->phase] ?? $match->phase) ?></p>

  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:end">
    <label>Goles Local: <input type="number" name="home_goals" min="0" value="<?= $match->homeGoals ?? '' ?>" style="width:4rem"></label>
    <label>Goles Visitante: <input type="number" name="away_goals" min="0" value="<?= $match->awayGoals ?? '' ?>" style="width:4rem"></label>
    <label>🟨 Local: <input type="number" name="home_yellows" min="0" value="<?= $match->homeYellows ?>" style="width:4rem"></label>
    <label>🟨 Visitante: <input type="number" name="away_yellows" min="0" value="<?= $match->awayYellows ?>" style="width:4rem"></label>
    <label>🟨🟨 Local: <input type="number" name="home_double_yellows" min="0" value="<?= $match->homeDoubleYellows ?>" style="width:4rem"></label>
    <label>🟨🟨 Visitante: <input type="number" name="away_double_yellows" min="0" value="<?= $match->awayDoubleYellows ?>" style="width:4rem"></label>
    <label>🟥 Local: <input type="number" name="home_reds" min="0" value="<?= $match->homeReds ?>" style="width:4rem"></label>
    <label>🟥 Visitante: <input type="number" name="away_reds" min="0" value="<?= $match->awayReds ?>" style="width:4rem"></label>
    <label><input type="checkbox" name="home_comeback" value="1" <?= $match->homeComeback ? 'checked' : '' ?>> Remontada Local</label>
    <label><input type="checkbox" name="away_comeback" value="1" <?= $match->awayComeback ? 'checked' : '' ?>> Remontada Visitante</label>
    <label><input type="checkbox" name="played" value="1" <?= $match->played ? 'checked' : '' ?>> Jugado</label>
  </div>

  <div style="margin-top:1rem">
    <button type="submit" class="btn btn-primary">Guardar cambios</button>
    <a href="<?= $base ?>/admin/game/matches" class="btn">Cancelar</a>
  </div>
</form>

<?php $view->endSection(); ?>
