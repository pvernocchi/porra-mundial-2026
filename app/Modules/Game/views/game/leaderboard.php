<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, array{user_id: int, full_name: string, total: float, teams: array}> $board */

use App\Core\Flags;

$view->extend('game.layout', ['title' => '🏆 Clasificación']);
$view->section('content');
?>

<div class="game-hero">
  <h1>🏆 Clasificación</h1>
  <p>Puntuación en vivo de todos los participantes</p>
</div>

<?php if ($board === []): ?>
  <div class="alert alert-info">Aún no hay participantes con equipos seleccionados.</div>
<?php else: ?>
  <div style="overflow-x:auto">
  <table class="table leaderboard-table">
    <thead>
      <tr>
        <th style="width:3rem">#</th>
        <th>Jugador</th>
        <th>⭐ B1</th>
        <th>🔥 B2</th>
        <th>💪 B3</th>
        <th>⚡ B4</th>
        <th>🎲 B5</th>
        <th>🌟 B6</th>
        <th style="width:5rem">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php $rank = 0; $prevTotal = null; $pos = 0; ?>
    <?php foreach ($board as $entry): ?>
      <?php
        $pos++;
        if ($entry['total'] !== $prevTotal) {
            $rank = $pos;
            $prevTotal = $entry['total'];
        }
        $rankClass = '';
        if ($rank === 1) $rankClass = ' rank-1';
        elseif ($rank === 2) $rankClass = ' rank-2';
        elseif ($rank === 3) $rankClass = ' rank-3';
        // Index teams by pot
        $teamsByPot = [];
        foreach ($entry['teams'] as $t) {
            $teamsByPot[$t['pot']] = $t;
        }
      ?>
      <tr>
        <td class="rank<?= $rankClass ?>">
          <?php if ($rank === 1): ?>🥇
          <?php elseif ($rank === 2): ?>🥈
          <?php elseif ($rank === 3): ?>🥉
          <?php else: ?><?= $rank ?>
          <?php endif ?>
        </td>
        <td class="player-name"><?= $e($entry['full_name']) ?></td>
        <?php for ($p = 1; $p <= 6; $p++): ?>
          <td class="team-cell">
            <?php if (isset($teamsByPot[$p])): ?>
              <?= Flags::img($teamsByPot[$p]['team_name'], 20) ?>
              <?= $e($teamsByPot[$p]['team_name']) ?>
              <small>(<?= number_format($teamsByPot[$p]['points'], 1) ?>)</small>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif ?>
          </td>
        <?php endfor ?>
        <td class="total"><?= number_format($entry['total'], 1) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>

  <div class="rules-section">
    <h2>📖 Criterios de puntuación</h2>
    <div class="rules-cards">
      <div class="rules-card">
        <h3>⚽ Resultados</h3>
        <ul>
          <li>✅ Victoria: <strong>+3</strong></li>
          <li>➖ Empate: <strong>+1</strong></li>
        </ul>
      </div>
      <div class="rules-card">
        <h3>🏟️ Avances</h3>
        <ul>
          <li>Fase de grupos: <strong>+3</strong></li>
          <li>Octavos: <strong>+3</strong></li>
          <li>Cuartos: <strong>+4</strong></li>
          <li>Semifinales: <strong>+6</strong></li>
          <li>Final: <strong>+8</strong></li>
          <li>🏆 Campeón: <strong>+12</strong></li>
        </ul>
      </div>
      <div class="rules-card">
        <h3>🎁 Bonus</h3>
        <ul>
          <li>3+ goles: <strong>+2</strong></li>
          <li>Portería a cero: <strong>+1</strong></li>
          <li>Remontada: <strong>+1</strong></li>
        </ul>
      </div>
      <div class="rules-card">
        <h3>⚠️ Penalizaciones</h3>
        <ul>
          <li>🟨 Amarilla: <strong>−0,2</strong></li>
          <li>🟨🟨 Doble amarilla: <strong>−1</strong></li>
          <li>🟥 Roja: <strong>−2</strong></li>
          <li>Recibir 3+ goles: <strong>−2</strong></li>
          <li>Último del grupo: <strong>−1</strong></li>
        </ul>
      </div>
      <div class="rules-card">
        <h3>🏅 Premios individuales</h3>
        <ul>
          <li>MVP: <strong>+3</strong></li>
          <li>Goleador: <strong>+2</strong></li>
          <li>Mejor portero: <strong>+2</strong></li>
          <li>Mejor joven: <strong>+2</strong></li>
        </ul>
      </div>
    </div>
  </div>
<?php endif ?>

<?php $view->endSection(); ?>
