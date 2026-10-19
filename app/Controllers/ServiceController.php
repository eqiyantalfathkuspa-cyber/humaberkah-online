<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\ServiceCatalog;
use App\Core\View;

final class ServiceController
{
    public static function index(): void
    {
        $categories = Db::all(
            'SELECT c.id, c.code, c.name FROM service_categories c INNER JOIN services s ON s.category_id = c.id AND s.is_active = 1 GROUP BY c.id, c.code, c.name ORDER BY c.id'
        );
        foreach ($categories as &$category) {
            $category['name'] = self::categoryDisplayName((string) $category['code'], (string) $category['name']);
        }
        unset($category);

        $services = Db::all(
            'SELECT s.code, s.name, s.mode, c.code AS category_code FROM services s INNER JOIN service_categories c ON c.id = s.category_id WHERE s.is_active = 1 ORDER BY c.id, s.code'
        );
        $grouped = [];
        foreach ($services as $service) {
            $service['name'] = self::serviceDisplayName((string) $service['code'], (string) $service['name']);
            $grouped[$service['category_code']][] = $service;
        }

        View::render('pages/services', [
            'title' => 'Katalog Layanan',
            'categories' => $categories,
            'servicesByCategory' => $grouped,
        ]);
    }

    public static function detail(array $params = []): void
    {
        $service = self::findService((string) ($params['code'] ?? ''));
        if ($service === null || (int) $service['is_active'] !== 1) {
            self::notFound();
            return;
        }

        $service['name'] = self::serviceDisplayName((string) $service['code'], (string) $service['name']);
        $service['category_name'] = self::categoryDisplayName((string) $service['category_code'], (string) $service['category_name']);
        $service['steps'] = Db::all(
            'SELECT step_no, step_name FROM service_steps WHERE service_id = ? ORDER BY step_no',
            [(int) $service['id']]
        );
        $service['standards'] = Db::one(
            'SELECT sla_text, responsible_unit, required_documents_text FROM service_standards WHERE service_id = ?',
            [(int) $service['id']]
        ) ?? [];
        $displayName = $service['code'] === 'TU-02' ? 'ADMINISTRASI MANDIRI' : $service['name'];

        View::render('pages/service_detail', [
            'title' => $service['code'] . ' — ' . $displayName,
            'service' => $service,
            'displayName' => $displayName,
            'informationStepsNotice' => ServiceCatalog::INFORMATION_STEPS_NOTICE,
            'modeLabel' => ServiceCatalog::modeLabel((string) $service['mode']),
        ]);
    }

    private static function findService(string $code): ?array
    {
        return Db::one(
            'SELECT s.id, s.code, s.name, s.mode, s.is_active, c.code AS category_code, c.name AS category_name FROM services s INNER JOIN service_categories c ON c.id = s.category_id WHERE s.code = ?',
            [strtoupper(trim($code))]
        );
    }

    private static function categoryDisplayName(string $code, string $fallback): string
    {
        return [
            'TU' => 'TATA USAHA',
            'KUR' => 'KURIKULUM',
            'KES' => 'KESISWAAN',
            'SAR' => 'SARANA PRASARANA',
            'HUM' => 'HUMAS',
        ][$code] ?? strtoupper($fallback);
    }

    private static function serviceDisplayName(string $code, string $fallback): string
    {
        return $code === 'TU-01' ? 'Srikandi' : $fallback;
    }

    private static function notFound(): void
    {
        http_response_code(404);
        View::render('pages/error', ['title' => '404', 'message' => 'Layanan tidak ditemukan.']);
    }
}
