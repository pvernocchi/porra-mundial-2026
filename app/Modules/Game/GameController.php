<?php
declare(strict_types=1);

namespace App\Modules\Game;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Models\Team;
use App\Models\Pick;
use App\Models\Score;
use App\Models\GameMatch;

final class GameController
{
    public function __construct(private Application $app) {}

    /**
     * Show the pick form (select 1 team per pot).
     */
    public function picks(Request $req): Response
    {
        $user = $this->app->auth()->user();
        if ($user === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }

        $teamModel = new Team($this->app->db());
        $pickModel = new Pick($this->app->db());

        $teamsByPot = $teamModel->allGroupedByPot();
        $existing   = $pickModel->forUser($user->id);

        // Index existing picks by pot
        $currentPicks = [];
        foreach ($existing as $p) {
            $currentPicks[$p->pot] = $p->teamId;
        }

        $locked = $this->app->settings()->get('game.picks_locked', '0') === '1';

        return (new Response())->html($this->app->view()->render('game.picks', [
            'teamsByPot'   => $teamsByPot,
            'currentPicks' => $currentPicks,
            'locked'       => $locked,
            'saved'        => $req->query('saved') === '1',
        ]));
    }

    /**
     * Save picks (POST).
     */
    public function savePicks(Request $req): Response
    {
        $user = $this->app->auth()->user();
        if ($user === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }

        if (!$this->app->csrf()->validate($req->post('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/game/picks');
        }

        $locked = $this->app->settings()->get('game.picks_locked', '0') === '1';
        if ($locked) {
            return (new Response())->redirect($this->app->baseUrl() . '/game/picks');
        }

        $teamModel = new Team($this->app->db());
        $pickModel = new Pick($this->app->db());

        // Validate: 1 team per pot (pots 1-6)
        $teamIdsByPot = [];
        $allTeams = $teamModel->all();
        $teamIndex = [];
        foreach ($allTeams as $t) {
            $teamIndex[$t->id] = $t;
        }

        for ($pot = 1; $pot <= 6; $pot++) {
            $teamId = (int)$req->post('pot_' . $pot, '0');
            if ($teamId <= 0 || !isset($teamIndex[$teamId]) || $teamIndex[$teamId]->pot !== $pot) {
                // Invalid pick; redirect back
                return (new Response())->redirect($this->app->baseUrl() . '/game/picks');
            }
            $teamIdsByPot[$pot] = $teamId;
        }

        $pickModel->saveForUser($user->id, $teamIdsByPot);

        return (new Response())->redirect($this->app->baseUrl() . '/game/picks?saved=1');
    }

    /**
     * Leaderboard page.
     */
    public function leaderboard(Request $req): Response
    {
        $scoreModel = new Score($this->app->db());
        $board = $scoreModel->leaderboard();

        return (new Response())->html($this->app->view()->render('game.leaderboard', [
            'board' => $board,
        ]));
    }

    /**
     * Results page: list of played matches.
     */
    public function results(Request $req): Response
    {
        $matchModel = new GameMatch($this->app->db());
        $matches = $matchModel->played();

        return (new Response())->html($this->app->view()->render('game.results', [
            'matches' => $matches,
        ]));
    }
}
