<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class PortalController
{
    public static function index(): void
    {
        View::render('pages/portal', ['title' => 'Portal pengguna']);
    }
}
