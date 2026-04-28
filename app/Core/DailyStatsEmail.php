<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Models\Score;

/**
 * Daily statistics email service.
 * Sends a summary email to all active users with their current stats.
 */
final class DailyStatsEmail
{
    public function __construct(private Application $app)
    {
    }

    /**
     * Send daily stats to a single user.
     */
    public function sendToUser(User $user): bool
    {
        $scoreModel = new Score($this->app->db());
        $breakdown = $scoreModel->userScoreBreakdown($user->id);
        
        $subject = '⚽ Porra Mundial 2026 - Resumen Diario';
        $html = $this->buildEmailHtml($user, $breakdown);
        
        return $this->app->mail()->send($user->email, $subject, $html);
    }

    /**
     * Send daily stats to all active users.
     * @return array{sent: int, failed: int}
     */
    public function sendToAllUsers(): array
    {
        $userModel = new User($this->app->db());
        [$users, $total] = $userModel->paginate('', null, 'active', 1, 1000);
        
        $sent = 0;
        $failed = 0;
        
        foreach ($users as $user) {
            if ($this->sendToUser($user)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Build the HTML email content.
     * @param array{total: float, rank: int, total_players: int, teams: array} $breakdown
     */
    private function buildEmailHtml(User $user, array $breakdown): string
    {
        $siteName = (string)$this->app->config()->get('site.name', 'Porra Mundial 2026');
        $baseUrl = $this->app->baseUrl();
        
        // Calculate position change indicator
        $positionEmoji = '';
        if ($breakdown['rank'] === 1) {
            $positionEmoji = '🥇';
        } elseif ($breakdown['rank'] === 2) {
            $positionEmoji = '🥈';
        } elseif ($breakdown['rank'] === 3) {
            $positionEmoji = '🥉';
        }
        
        $rankText = $breakdown['rank'] > 0 ? "#{$breakdown['rank']}" : "Sin clasificar";
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$siteName} - Resumen Diario</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f3f2f1;color:#323130;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;">
        <!-- Header -->
        <div style="background:linear-gradient(135deg,#0078d4,#5c2d91);padding:2rem;text-align:center;">
            <div style="font-size:3rem;margin-bottom:0.5rem;">⚽</div>
            <h1 style="margin:0;color:#ffffff;font-size:1.5rem;font-weight:600;">Resumen Diario</h1>
            <p style="margin:0.5rem 0 0;color:#deecf9;font-size:0.95rem;">{$siteName}</p>
        </div>
        
        <!-- Greeting -->
        <div style="padding:2rem 2rem 1rem;">
            <h2 style="margin:0 0 0.5rem;color:#323130;font-size:1.3rem;">¡Hola, {$user->fullName}!</h2>
            <p style="margin:0;color:#605e5c;line-height:1.6;">Aquí está tu resumen diario del Mundial 2026.</p>
        </div>
        
        <!-- Stats Cards -->
        <div style="padding:0 2rem 2rem;">
            <table cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="padding:0.5rem;width:50%;">
                        <div style="background:#deecf9;border-radius:8px;padding:1.25rem;text-align:center;">
                            <div style="color:#0078d4;font-size:2rem;font-weight:700;margin-bottom:0.25rem;">{$breakdown['total']}</div>
                            <div style="color:#605e5c;font-size:0.85rem;">Puntos Totales</div>
                        </div>
                    </td>
                    <td style="padding:0.5rem;width:50%;">
                        <div style="background:#e6d9f2;border-radius:8px;padding:1.25rem;text-align:center;">
                            <div style="color:#5c2d91;font-size:2rem;font-weight:700;margin-bottom:0.25rem;">{$positionEmoji} {$rankText}</div>
                            <div style="color:#605e5c;font-size:0.85rem;">de {$breakdown['total_players']} jugadores</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
HTML;

        // Teams breakdown
        if (!empty($breakdown['teams'])) {
            $html .= <<<HTML
        <!-- Teams Breakdown -->
        <div style="padding:0 2rem 2rem;">
            <h3 style="margin:0 0 1rem;color:#323130;font-size:1.1rem;font-weight:600;">📊 Desglose por Equipo</h3>
HTML;
            
            foreach ($breakdown['teams'] as $team) {
                $totalPts = number_format($team['total'], 1);
                $matchPts = number_format($team['match_pts'], 1);
                $progressPts = number_format($team['progress_pts'], 1);
                $awardPts = number_format($team['award_pts'], 1);
                
                $html .= <<<HTML
            <div style="background:#ffffff;border:1px solid #edebe9;border-radius:8px;padding:1rem;margin-bottom:0.75rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                    <div>
                        <div style="font-weight:600;color:#323130;font-size:1rem;">{$team['team_name']}</div>
                        <div style="font-size:0.8rem;color:#605e5c;">Bombo {$team['pot']}</div>
                    </div>
                    <div style="font-size:1.3rem;font-weight:700;color:#0078d4;">{$totalPts}</div>
                </div>
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:0.85rem;">
                    <tr>
                        <td style="padding:0.25rem 0;color:#605e5c;">⚽ Partidos:</td>
                        <td style="padding:0.25rem 0;text-align:right;font-weight:600;color:#107c10;">{$matchPts}</td>
                    </tr>
                    <tr>
                        <td style="padding:0.25rem 0;color:#605e5c;">🏆 Avances:</td>
                        <td style="padding:0.25rem 0;text-align:right;font-weight:600;color:#107c10;">{$progressPts}</td>
                    </tr>
                    <tr>
                        <td style="padding:0.25rem 0;color:#605e5c;">🏅 Premios:</td>
                        <td style="padding:0.25rem 0;text-align:right;font-weight:600;color:#107c10;">{$awardPts}</td>
                    </tr>
                </table>
            </div>
HTML;
            }
            
            $html .= '</div>';
        }
        
        $html .= <<<HTML
        <!-- CTA Button -->
        <div style="padding:0 2rem 2rem;">
            <a href="{$baseUrl}/home" style="display:block;background:#0078d4;color:#ffffff;text-align:center;padding:1rem;border-radius:6px;text-decoration:none;font-weight:600;">
                Ver Clasificación Completa
            </a>
        </div>
        
        <!-- Footer -->
        <div style="background:#f3f2f1;padding:1.5rem 2rem;text-align:center;color:#605e5c;font-size:0.85rem;border-top:1px solid #edebe9;">
            <p style="margin:0 0 0.5rem;">⚽ {$siteName}</p>
            <p style="margin:0;">
                <a href="{$baseUrl}/home" style="color:#0078d4;text-decoration:none;">Ver Dashboard</a> · 
                <a href="{$baseUrl}/game/leaderboard" style="color:#0078d4;text-decoration:none;">Clasificación</a> · 
                <a href="{$baseUrl}/game/my-scores" style="color:#0078d4;text-decoration:none;">Mis Puntos</a>
            </p>
            <p style="margin:0.75rem 0 0;font-size:0.75rem;color:#8a8886;">
                Este es un correo automático. Microsoft Porra Mundial 2026.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}
