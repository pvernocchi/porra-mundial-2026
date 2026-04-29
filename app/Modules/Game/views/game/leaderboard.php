<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, array{user_id: int, full_name: string, display_name: string, total: float, teams: array}> $board */

use App\Core\Flags;

$view->extend('game.layout', ['title' => 'Clasificación']);
$view->section('content');
?>

<div class="game-hero">
  <h1>
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.3em;height:1.3em">
      <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/>
      <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/>
      <path d="M4 22h16"/>
      <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 19.24 7 20v2"/>
      <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 19.24 17 20v2"/>
      <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
    </svg>
    Clasificación General
  </h1>
  <p>Puntuación en vivo de todos los participantes · <?= count($board) ?> jugadores</p>
</div>

<?php if ($board === []): ?>
  <div class="alert alert-info">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.1em;height:1.1em;vertical-align:-.15em">
      <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
    </svg>
    Aún no hay participantes con equipos seleccionados.
  </div>
<?php else: ?>
  <!-- Podium for top 3 -->
  <?php if (count($board) >= 3): ?>
  <section class="podium-section">
    <div class="podium">
      <!-- 2nd Place -->
      <div class="podium-item podium-2">
        <div class="podium-medal">🥈</div>
        <div class="podium-avatar">
          <?= strtoupper(substr($board[1]['full_name'], 0, 2)) ?>
        </div>
        <div class="podium-name"><?= $e($board[1]['display_name']) ?></div>
        <div class="podium-score"><?= number_format($board[1]['total'], 1) ?> pts</div>
        <div class="podium-bar podium-bar-2"></div>
      </div>
      <!-- 1st Place -->
      <div class="podium-item podium-1">
        <div class="podium-medal">🥇</div>
        <div class="podium-avatar">
          <?= strtoupper(substr($board[0]['full_name'], 0, 2)) ?>
        </div>
        <div class="podium-name"><?= $e($board[0]['display_name']) ?></div>
        <div class="podium-score"><?= number_format($board[0]['total'], 1) ?> pts</div>
        <div class="podium-bar podium-bar-1"></div>
      </div>
      <!-- 3rd Place -->
      <div class="podium-item podium-3">
        <div class="podium-medal">🥉</div>
        <div class="podium-avatar">
          <?= strtoupper(substr($board[2]['full_name'], 0, 2)) ?>
        </div>
        <div class="podium-name"><?= $e($board[2]['display_name']) ?></div>
        <div class="podium-score"><?= number_format($board[2]['total'], 1) ?> pts</div>
        <div class="podium-bar podium-bar-3"></div>
      </div>
    </div>
  </section>
  <?php endif ?>

  <div style="overflow-x:auto">
  <table class="table leaderboard-table">
    <thead>
      <tr>
        <th style="width:3.5rem">Pos.</th>
        <th>Jugador</th>
        <th>⭐ B1</th>
        <th>🔥 B2</th>
        <th>💪 B3</th>
        <th>⚡ B4</th>
        <th>🎲 B5</th>
        <th>🌟 B6</th>
        <th style="width:6rem; text-align:center">Total</th>
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
          <?php else: ?><strong><?= $rank ?></strong>
          <?php endif ?>
        </td>
        <td class="player-name"><?= $e($entry['display_name']) ?></td>
        <?php for ($p = 1; $p <= 6; $p++): ?>
          <td class="team-cell">
            <?php if (isset($teamsByPot[$p])): ?>
              <div style="display:flex;align-items:center;gap:.35rem">
                <?= Flags::img($teamsByPot[$p]['team_name'], 20) ?>
                <div>
                  <div><?= $e($teamsByPot[$p]['team_name']) ?></div>
                  <small style="color:var(--muted)"><?= number_format($teamsByPot[$p]['points'], 1) ?> pts</small>
                </div>
              </div>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif ?>
          </td>
        <?php endfor ?>
        <td class="total" style="text-align:center"><?= number_format($entry['total'], 1) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>

<?php endif ?>

<?php $view->endSection(); ?>
