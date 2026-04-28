<?php
declare(strict_types=1);

namespace App\Modules\Game;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Models\Team;
use App\Models\GameMatch;
use App\Models\Score;

final class AdminGameController
{
    public function __construct(private Application $app) {}

    /**
     * List all matches.
     */
    public function matches(Request $req): Response
    {
        $matchModel = new GameMatch($this->app->db());
        $teamModel  = new Team($this->app->db());

        return (new Response())->html($this->app->view()->render('game.admin-matches', [
            'matches' => $matchModel->all(),
            'teams'   => $teamModel->all(),
        ]));
    }

    /**
     * Create a new match (POST).
     */
    public function createMatch(Request $req): Response
    {
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches');
        }

        $matchModel = new GameMatch($this->app->db());
        $matchModel->create([
            'phase'               => $req->input('phase', 'group'),
            'match_date'          => $req->input('match_date', '') ?: null,
            'home_team_id'        => (int)$req->input('home_team_id', '0'),
            'away_team_id'        => (int)$req->input('away_team_id', '0'),
            'home_goals'          => $req->input('home_goals', '') !== '' ? (int)$req->input('home_goals', '0') : null,
            'away_goals'          => $req->input('away_goals', '') !== '' ? (int)$req->input('away_goals', '0') : null,
            'home_yellows'        => (int)$req->input('home_yellows', '0'),
            'away_yellows'        => (int)$req->input('away_yellows', '0'),
            'home_double_yellows' => (int)$req->input('home_double_yellows', '0'),
            'away_double_yellows' => (int)$req->input('away_double_yellows', '0'),
            'home_reds'           => (int)$req->input('home_reds', '0'),
            'away_reds'           => (int)$req->input('away_reds', '0'),
            'home_comeback'       => $req->input('home_comeback', '0') === '1' ? 1 : 0,
            'away_comeback'       => $req->input('away_comeback', '0') === '1' ? 1 : 0,
            'played'              => $req->input('played', '0') === '1' ? 1 : 0,
        ]);

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches');
    }

    /**
     * Edit match form.
     */
    public function editMatch(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $matchModel = new GameMatch($this->app->db());
        $match = $matchModel->find($id);
        if ($match === null) {
            return (new Response())->html('<h1>404</h1>', 404);
        }
        $teamModel = new Team($this->app->db());

        return (new Response())->html($this->app->view()->render('game.admin-match-edit', [
            'match' => $match,
            'teams' => $teamModel->all(),
        ]));
    }

    /**
     * Update match (POST).
     */
    public function updateMatch(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches/' . $id);
        }

        $matchModel = new GameMatch($this->app->db());
        $matchModel->updateResult($id, [
            'home_goals'          => $req->input('home_goals', '') !== '' ? (int)$req->input('home_goals', '0') : null,
            'away_goals'          => $req->input('away_goals', '') !== '' ? (int)$req->input('away_goals', '0') : null,
            'home_yellows'        => (int)$req->input('home_yellows', '0'),
            'away_yellows'        => (int)$req->input('away_yellows', '0'),
            'home_double_yellows' => (int)$req->input('home_double_yellows', '0'),
            'away_double_yellows' => (int)$req->input('away_double_yellows', '0'),
            'home_reds'           => (int)$req->input('home_reds', '0'),
            'away_reds'           => (int)$req->input('away_reds', '0'),
            'home_comeback'       => $req->input('home_comeback', '0') === '1' ? 1 : 0,
            'away_comeback'       => $req->input('away_comeback', '0') === '1' ? 1 : 0,
            'played'              => $req->input('played', '0') === '1' ? 1 : 0,
        ]);

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches');
    }

    /**
     * Delete match (POST).
     */
    public function deleteMatch(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches');
        }
        $matchModel = new GameMatch($this->app->db());
        $matchModel->deleteMatch($id);

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches');
    }

    /**
     * Progress & awards management page.
     */
    public function progress(Request $req): Response
    {
        $teamModel  = new Team($this->app->db());
        $scoreModel = new Score($this->app->db());

        return (new Response())->html($this->app->view()->render('game.admin-progress', [
            'teams'    => $teamModel->all(),
            'progress' => $scoreModel->allProgress(),
            'awards'   => $scoreModel->allAwards(),
        ]));
    }

    /**
     * Add progress (POST).
     */
    public function addProgress(Request $req): Response
    {
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
        }
        $teamId     = (int)$req->input('team_id', '0');
        $achievement = $req->input('achievement', '');

        $valid = ['passed_group', 'round_of_16', 'quarter', 'semi', 'final', 'champion', 'last_in_group'];
        if ($teamId > 0 && in_array($achievement, $valid, true)) {
            $scoreModel = new Score($this->app->db());
            $scoreModel->addProgress($teamId, $achievement);
        }

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
    }

    /**
     * Remove progress (POST).
     */
    public function removeProgress(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
        }
        $teamId     = (int)($params['team_id'] ?? 0);
        $achievement = $params['achievement'] ?? '';

        $scoreModel = new Score($this->app->db());
        $scoreModel->removeProgress($teamId, $achievement);

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
    }

    /**
     * Set award (POST).
     */
    public function setAward(Request $req): Response
    {
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
        }
        $awardType  = $req->input('award_type', '');
        $teamId     = (int)$req->input('team_id', '0');
        $playerName = $req->input('player_name', '') ?: null;

        $valid = ['mvp', 'golden_boot', 'golden_glove', 'best_young'];
        if ($teamId > 0 && in_array($awardType, $valid, true)) {
            $scoreModel = new Score($this->app->db());
            $scoreModel->setAward($awardType, $teamId, $playerName);
        }

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
    }

    /**
     * Remove award (POST).
     */
    public function removeAward(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
        }
        $awardType = $params['award'] ?? '';

        $scoreModel = new Score($this->app->db());
        $scoreModel->removeAward($awardType);

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/progress');
    }

    /**
     * Lock/unlock picks (POST).
     */
    public function togglePicksLock(Request $req): Response
    {
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches');
        }
        $current = $this->app->settings()->get('game.picks_locked', '0');
        $this->app->settings()->set('game.picks_locked', $current === '1' ? '0' : '1');

        return (new Response())->redirect($this->app->baseUrl() . '/admin/game/matches');
    }
}
