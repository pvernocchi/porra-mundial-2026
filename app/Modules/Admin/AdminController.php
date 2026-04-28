<?php
declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Models\Invitation;
use App\Models\MfaCredential;

/**
 * Admin module entry + dashboard.
 */
final class AdminController
{
    public function __construct(private Application $app)
    {
    }

    public function dashboard(Request $req): Response
    {
        $userModel = new User($this->app->db());
        [$users, $total] = $userModel->paginate('', null, null, 1, 5);
        $pending = (new Invitation($this->app->db()))->listPending();
        $audit   = $this->app->audit()->recent(20);

        return (new Response())->html($this->app->view()->render('admin.dashboard', [
            'totalUsers' => $total,
            'recentUsers' => $users,
            'pendingInvites' => $pending,
            'audit' => $audit,
        ]));
    }
}
