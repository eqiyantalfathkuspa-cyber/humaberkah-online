<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\MadaniReportService;
use App\Core\View;
use App\Policies\Permissions;
use InvalidArgumentException;

final class MadaniController
{
    public static function index(): void
    {
        self::setPrivateHeaders();
        if (self::adminUser('admin.madani.view') === null) {
            return;
        }

        try {
            $filters = MadaniReportService::validateFilters($_GET);
        } catch (InvalidArgumentException) {
            http_response_code(422);
            View::render('pages/error', ['title' => 'Filter tidak valid', 'message' => 'Filter laporan tidak valid.']);
            return;
        }
        $services = Db::all('SELECT id, code, name FROM services ORDER BY code');
        $report = MadaniReportService::report($filters);
        View::render('pages/admin_madani', [
            'title' => 'Dashboard MADANI',
            'services' => $services,
            'report' => $report,
        ]);
    }

    public static function export(): void
    {
        self::setPrivateHeaders();
        if (self::adminUser('admin.madani.export') === null) {
            return;
        }

        try {
            $filters = MadaniReportService::validateFilters($_GET);
        } catch (InvalidArgumentException) {
            http_response_code(422);
            View::render('pages/error', ['title' => 'Filter tidak valid', 'message' => 'Filter laporan tidak valid.']);
            return;
        }

        $csv = MadaniReportService::csv(MadaniReportService::report($filters));
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="madani-agregat.csv"');
        echo $csv;
    }

    private static function adminUser(string $ability): ?array
    {
        $actor = Auth::user();
        if ($actor !== null && Permissions::can((string) ($actor['role_code'] ?? ''), $ability)) {
            return $actor;
        }
        http_response_code($actor === null ? 302 : 403);
        if ($actor === null && !headers_sent()) {
            header('Location: /login', true, 302);
        } else {
            View::render('pages/error', ['title' => '403', 'message' => 'Anda tidak berhak mengakses halaman ini.']);
        }
        return null;
    }

    private static function setPrivateHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
        }
    }
}
