<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Core\Db;

final class PortalController
{
    public static function index(): void
    {
        $user = Auth::user();
        $complaints = $user === null ? [] : Db::all(
            'SELECT id, ticket_no, status, created_at FROM complaints WHERE user_id = ? ORDER BY created_at DESC, id DESC',
            [(int) $user['id']]
        );
        View::render('pages/portal', [
            'title' => 'Portal pengguna',
            'complaints' => $complaints,
            'complaintStatusLabels' => \App\Core\ComplaintWorkflow::STATUS_LABELS,
        ]);
    }
}
