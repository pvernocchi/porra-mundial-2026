<?php
declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Models\LeaderboardSnapshot;
use App\Models\Report;
use App\Models\Score;

/**
 * Broadcast report ("reporte de estado para difusión").
 *
 * Builds the data tables, formats a Teams-friendly text block ready to
 * be copy-pasted, and lets administrators take a fresh leaderboard
 * snapshot so the next report can highlight ranking movements.
 */
final class ReportController
{
    public function __construct(private Application $app) {}

    public function index(Request $req): Response
    {
        $report     = new Report($this->app->db());
        $score      = new Score($this->app->db());
        $snapshots  = new LeaderboardSnapshot($this->app->db());

        $board       = $score->leaderboard();
        $previous    = $snapshots->latest();
        $movements   = $report->movements($board, $previous);
        $teamScores  = $report->teamScores($score);

        $data = [
            'topLeaders'      => $report->topLeaders($board, 5),
            'topUpward'       => $report->topUpward($movements, 3),
            'topDownward'     => $report->topDownward($movements, 3),
            'bestTeams'       => $report->bestTeams($teamScores, 5),
            'worstTeams'      => $report->worstTeams($teamScores, 5),
            'mostPicked'      => $report->mostPickedTeams(5),
            'identicalGroups' => $report->identicalPickGroups(),
            'previousAt'      => $snapshots->latestTimestamp(),
            'hasPrevious'     => $previous !== [],
            'snapshotSaved'   => $req->query('snapshot') === '1',
        ];
        $data['teamsText'] = $this->formatForTeams($data);

        return (new Response())->html($this->app->view()->render('admin.reports', $data));
    }

    /**
     * Persist the current leaderboard as a snapshot so the next report
     * can show ranking movements relative to "now".
     */
    public function snapshot(Request $req): Response
    {
        if (!$this->app->csrf()->valid($req->input('_token', ''))) {
            return (new Response())->redirect($this->app->baseUrl() . '/admin/reports');
        }

        $score = new Score($this->app->db());
        $snapshots = new LeaderboardSnapshot($this->app->db());
        $snapshots->save($score->leaderboard());

        return (new Response())->redirect($this->app->baseUrl() . '/admin/reports?snapshot=1');
    }

    /**
     * Render the report as a plain-text block suitable for pasting in
     * Microsoft Teams. Markdown bullets and bold are kept since Teams
     * renders a basic subset of Markdown.
     *
     * @param array<string, mixed> $d
     */
    private function formatForTeams(array $d): string
    {
        $siteName = (string)$this->app->config()->get('site.name', 'Porra');
        $today    = gmdate('d/m/Y');
        $lines    = [];

        $lines[] = '**' . $siteName . ' — Reporte de estado (' . $today . ')**';
        $lines[] = '';

        $lines[] = '**🏆 Top 5 líderes**';
        if ($d['topLeaders'] === []) {
            $lines[] = '_Sin datos._';
        } else {
            foreach ($d['topLeaders'] as $row) {
                $lines[] = sprintf(
                    '%d. %s — %s pts',
                    (int)$row['position'],
                    (string)$row['display_name'],
                    $this->fmtPoints((float)$row['total'])
                );
            }
        }
        $lines[] = '';

        $lines[] = '**📈 Top 3 movimientos ascendentes**';
        if (!$d['hasPrevious']) {
            $lines[] = '_Aún no hay un snapshot anterior con el que comparar._';
        } elseif ($d['topUpward'] === []) {
            $lines[] = '_Sin movimientos ascendentes significativos._';
        } else {
            foreach ($d['topUpward'] as $row) {
                $lines[] = sprintf(
                    '- %s: %d → %d (+%d)',
                    (string)$row['display_name'],
                    (int)$row['previous_position'],
                    (int)$row['current_position'],
                    (int)$row['delta']
                );
            }
        }
        $lines[] = '';

        $lines[] = '**📉 Top 3 movimientos descendentes**';
        if (!$d['hasPrevious']) {
            $lines[] = '_Aún no hay un snapshot anterior con el que comparar._';
        } elseif ($d['topDownward'] === []) {
            $lines[] = '_Sin movimientos descendentes significativos._';
        } else {
            foreach ($d['topDownward'] as $row) {
                $lines[] = sprintf(
                    '- %s: %d → %d (%d)',
                    (string)$row['display_name'],
                    (int)$row['previous_position'],
                    (int)$row['current_position'],
                    (int)$row['delta']
                );
            }
        }
        $lines[] = '';

        $lines[] = '**⭐ Selecciones que mejor rindieron**';
        if ($d['bestTeams'] === []) {
            $lines[] = '_Sin datos._';
        } else {
            foreach ($d['bestTeams'] as $row) {
                $lines[] = sprintf(
                    '- %s (Bombo %d) — %s pts',
                    (string)$row['team_name'],
                    (int)$row['pot'],
                    $this->fmtPoints((float)$row['points'])
                );
            }
        }
        $lines[] = '';

        $lines[] = '**💤 Selecciones que peor rindieron**';
        if ($d['worstTeams'] === []) {
            $lines[] = '_Sin datos._';
        } else {
            foreach ($d['worstTeams'] as $row) {
                $lines[] = sprintf(
                    '- %s (Bombo %d) — %s pts',
                    (string)$row['team_name'],
                    (int)$row['pot'],
                    $this->fmtPoints((float)$row['points'])
                );
            }
        }
        $lines[] = '';

        $lines[] = '**🔥 Selecciones más elegidas**';
        if ($d['mostPicked'] === []) {
            $lines[] = '_Sin elecciones registradas._';
        } else {
            foreach ($d['mostPicked'] as $row) {
                $lines[] = sprintf(
                    '- %s (Bombo %d) — %d elecciones',
                    (string)$row['team_name'],
                    (int)$row['pot'],
                    (int)$row['picks']
                );
            }
        }
        $lines[] = '';

        $lines[] = '**👯 Participantes con elecciones idénticas en cada bombo**';
        if ($d['identicalGroups'] === []) {
            $lines[] = '_No hay coincidencias._';
        } else {
            foreach ($d['identicalGroups'] as $g) {
                $teams = [];
                foreach ($g['teams'] as $t) {
                    $teams[] = sprintf('B%d: %s', (int)$t['pot'], (string)$t['team_name']);
                }
                $lines[] = '- Selecciones: ' . implode(' · ', $teams);
                foreach ($g['participants'] as $p) {
                    $club = (string)$p['team_name'];
                    $line = '   • ' . (string)$p['full_name'];
                    if ($club !== '') {
                        $line .= ' — ' . $club;
                    }
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", $lines);
    }

    private function fmtPoints(float $value): string
    {
        // Show one decimal only when needed to keep the message tidy.
        if (abs($value - round($value)) < 0.0001) {
            return (string)(int)round($value);
        }
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
