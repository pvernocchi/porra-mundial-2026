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
     * Homepage dashboard for regular (non-admin) users.
     */
    public function home(Request $req): Response
    {
        $user = $this->app->auth()->user();
        if ($user === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }

        $pickModel  = new Pick($this->app->db());
        $scoreModel = new Score($this->app->db());
        $teamModel  = new Team($this->app->db());

        $picks      = $pickModel->forUser($user->id);
        $picksCount = count($picks);
        $breakdown  = $scoreModel->userScoreBreakdown($user->id);
        
        // Get team details for user's picks
        $pickedTeams = [];
        foreach ($picks as $pick) {
            $team = $teamModel->find($pick->teamId);
            if ($team !== null) {
                $pickedTeams[] = [
                    'id'   => $team->id,
                    'name' => $team->name,
                    'pot'  => $team->pot,
                ];
            }
        }

        return (new Response())->html($this->app->view()->render('game.home', [
            'user'        => $user,
            'picksCount'  => $picksCount,
            'totalScore'  => $breakdown['total'],
            'rank'        => $breakdown['rank'],
            'totalPlayers'=> $breakdown['total_players'],
            'pickedTeams' => $pickedTeams,
        ]));
    }

    /**
     * My scores page: detailed breakdown per team.
     */
    public function myScores(Request $req): Response
    {
        $user = $this->app->auth()->user();
        if ($user === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }

        $scoreModel = new Score($this->app->db());
        $breakdown  = $scoreModel->userScoreBreakdown($user->id);

        return (new Response())->html($this->app->view()->render('game.my-scores', [
            'user'      => $user,
            'breakdown' => $breakdown,
        ]));
    }

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

        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
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
            $teamId = (int)$req->input('pot_' . $pot, '0');
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
        $picksLocked = $this->app->settings()->get('game.picks_locked', '0') === '1';
        $board = [];
        if ($picksLocked) {
            $scoreModel = new Score($this->app->db());
            $board = $scoreModel->leaderboard();
        }

        return (new Response())->html($this->app->view()->render('game.leaderboard', [
            'board'       => $board,
            'picksLocked' => $picksLocked,
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
