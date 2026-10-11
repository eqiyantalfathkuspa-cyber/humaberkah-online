<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ChecklistService;
use App\Core\View;
use App\Policies\Permissions;

final class ChecklistController
{
    public static function index(): void
    {
        $user = Auth::user();
        $role = (string) ($user['role_code'] ?? $user['role'] ?? 'GUEST');
        if (!Permissions::can($role, 'admin.checklist.view')) {
            http_response_code($user === null ? 302 : 403);
            if ($user === null && !headers_sent()) {
                header('Location: /login', true, 302);
                return;
            }
            View::render('pages/error', [
                'title' => '403',
                'message' => 'Anda tidak berhak mengakses halaman ini.',
            ]);
            return;
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store');
        }
        View::render('pages/admin_checklist', [
            'title' => 'Checklist Eviden',
            ...ChecklistService::matrix(),
        ]);
    }
}
