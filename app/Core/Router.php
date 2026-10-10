<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\ApplicationController;
use App\Controllers\ComplaintsController;
use App\Controllers\AuthController;
use App\Controllers\PortalController;
use App\Controllers\ServiceController;
use App\Policies\Permissions;

final class Router
{
    /**
     * @return array{status:int, route:string, method:string}
     */
    public static function dispatch(string $method, string $path, ?array $routes = null): array
    {
        $method = strtoupper($method);
        $path = self::normalize($path);
        $routes = $routes ?? self::routes();

        foreach (($routes[$method] ?? []) as $pattern => $route) {
            $params = [];
            if (self::matches($pattern, $path, $params)) {
                if (!self::applyRouteChecks($route, $params)) {
                    return ['status' => 403, 'route' => $pattern, 'method' => $method];
                }

                self::status(200);
                self::invokeHandler($route['handler'] ?? null, $params);
                return ['status' => http_response_code() ?: 200, 'route' => $pattern, 'method' => $method];
            }
        }

        foreach ($routes as $candidateMethod => $defs) {
            if ($candidateMethod === $method) {
                continue;
            }

            foreach ($defs as $pattern => $route) {
                $params = [];
                if (self::matches($pattern, $path, $params)) {
                    self::status(405);
                    View::render('pages/error', [
                        'title' => '405',
                        'message' => 'Metode HTTP tidak diizinkan untuk rute ini.',
                    ]);
                    return ['status' => 405, 'route' => $path, 'method' => $method];
                }
            }
        }

        self::status(404);
        View::render('pages/error', [
            'title' => '404',
            'message' => 'Halaman tidak ditemukan.',
        ]);

        return ['status' => 404, 'route' => $path, 'method' => $method];
    }

    /**
     * @return array<string, array<string, array{handler:callable|array, ability?: string, middleware?: array<int, string>}>>
     */
    private static function routes(): array
    {
        return [
            'GET' => [
                '/' => [
                    'handler' => static function (): void {
                        View::render('pages/home', ['title' => 'HUMA MANTAP']);
                    },
                    'ability' => 'home.public',
                ],
                '/login' => [
                    'handler' => [AuthController::class, 'loginPage'],
                    'ability' => 'login',
                ],
                '/layanan' => [
                    'handler' => [ServiceController::class, 'index'],
                    'ability' => 'services.catalog',
                ],
                '/layanan/{code}' => [
                    'handler' => [ServiceController::class, 'detail'],
                    'ability' => 'services.detail',
                ],
                '/layanan/{code}/ajukan' => [
                    'handler' => [ApplicationController::class, 'form'],
                    'ability' => 'applications.submit',
                ],
                '/pengajuan/diterima' => [
                    'handler' => [ApplicationController::class, 'submissionComplete'],
                    'ability' => 'applications.submit',
                ],
                '/lacak' => [
                    'handler' => [ApplicationController::class, 'trackingForm'],
                    'ability' => 'applications.track',
                ],
                '/kahayan' => [
                    'handler' => [ComplaintsController::class, 'form'],
                    'ability' => 'complaints.submit',
                ],
                '/kahayan/diterima' => [
                    'handler' => [ComplaintsController::class, 'submitted'],
                    'ability' => 'complaints.submit',
                ],
                '/lacak-aduan' => [
                    'handler' => [ComplaintsController::class, 'trackingForm'],
                    'ability' => 'complaints.track',
                ],
                '/admin/aduan' => [
                    'handler' => [ComplaintsController::class, 'adminIndex'],
                    'ability' => 'admin.complaints.manage',
                ],
                '/admin/aduan/{id}' => [
                    'handler' => [ComplaintsController::class, 'adminDetail'],
                    'ability' => 'admin.complaints.detail',
                ],
                '/portal' => [
                    'handler' => [PortalController::class, 'index'],
                    'ability' => 'portal.view',
                ],
                '/portal/aduan/{id}' => [
                    'handler' => [ComplaintsController::class, 'portalDetail'],
                    'ability' => 'portal.complaints.view',
                ],
                '/admin' => [
                    'handler' => [AdminController::class, 'dashboard'],
                    'ability' => 'admin.dashboard',
                ],
                '/admin/pengguna' => [
                    'handler' => [AdminController::class, 'users'],
                    'ability' => 'admin.users.manage',
                ],
                '/admin/audit' => [
                    'handler' => [AdminController::class, 'audit'],
                    'ability' => 'admin.audit.view',
                ],
                '/admin/layanan' => [
                    'handler' => [AdminController::class, 'services'],
                    'ability' => 'admin.services.manage',
                ],
                '/admin/layanan/{code}/ubah' => [
                    'handler' => [AdminController::class, 'editService'],
                    'ability' => 'admin.services.edit',
                ],
                '/admin/pengajuan' => [
                    'handler' => [ApplicationController::class, 'adminIndex'],
                    'ability' => 'admin.applications.manage',
                ],
                '/admin/pengajuan/{id}' => [
                    'handler' => [ApplicationController::class, 'adminDetail'],
                    'ability' => 'admin.applications.detail',
                ],
                '/admin/pengajuan/{id}/berkas/{docId}' => [
                    'handler' => [ApplicationController::class, 'downloadDocument'],
                    'ability' => 'admin.applications.documents.download',
                ],
            ],
            'POST' => [
                '/login' => [
                    'handler' => [AuthController::class, 'login'],
                    'ability' => 'login',
                    'middleware' => ['csrf'],
                ],
                '/logout' => [
                    'handler' => [AuthController::class, 'logout'],
                    'ability' => 'logout',
                    'middleware' => ['csrf'],
                ],
                '/admin/pengguna' => [
                    'handler' => [AdminController::class, 'createUser'],
                    'ability' => 'admin.users.create',
                    'middleware' => ['csrf'],
                ],
                '/admin/pengguna/{id}/role' => [
                    'handler' => [AdminController::class, 'changeRole'],
                    'ability' => 'admin.users.role',
                    'middleware' => ['csrf'],
                ],
                '/admin/pengguna/{id}/nonaktif' => [
                    'handler' => [AdminController::class, 'deactivateUser'],
                    'ability' => 'admin.users.deactivate',
                    'middleware' => ['csrf'],
                ],
                '/admin/pengguna/{id}/reset-sandi' => [
                    'handler' => [AdminController::class, 'resetPassword'],
                    'ability' => 'admin.users.reset_password',
                    'middleware' => ['csrf'],
                ],
                '/admin/layanan/{code}' => [
                    'handler' => [AdminController::class, 'updateService'],
                    'ability' => 'admin.services.update',
                    'middleware' => ['csrf'],
                ],
                '/layanan/{code}/ajukan' => [
                    'handler' => [ApplicationController::class, 'submit'],
                    'ability' => 'applications.submit',
                    'middleware' => ['csrf'],
                ],
                '/lacak' => [
                    'handler' => [ApplicationController::class, 'track'],
                    'ability' => 'applications.track.lookup',
                    'middleware' => ['csrf'],
                ],
                '/kahayan' => [
                    'handler' => [ComplaintsController::class, 'submit'],
                    'ability' => 'complaints.submit',
                    'middleware' => ['csrf'],
                ],
                '/lacak-aduan' => [
                    'handler' => [ComplaintsController::class, 'track'],
                    'ability' => 'complaints.track.lookup',
                    'middleware' => ['csrf'],
                ],
                '/admin/aduan/{id}/status' => [
                    'handler' => [ComplaintsController::class, 'updateStatus'],
                    'ability' => 'admin.complaints.status',
                    'middleware' => ['csrf'],
                ],
                '/admin/aduan/{id}/tanggapan' => [
                    'handler' => [ComplaintsController::class, 'updateResponse'],
                    'ability' => 'admin.complaints.response',
                    'middleware' => ['csrf'],
                ],
                '/admin/pengajuan/{id}/status' => [
                    'handler' => [ApplicationController::class, 'updateStatus'],
                    'ability' => 'admin.applications.status',
                    'middleware' => ['csrf'],
                ],
            ],
        ];
    }

    private static function applyRouteChecks(array $route, array $params): bool
    {
        $ability = (string) ($route['ability'] ?? 'home.public');
        $handler = $route['handler'] ?? null;
        if (
            $handler === [\App\Controllers\ComplaintsController::class, 'trackingForm']
            || $handler === [\App\Controllers\ComplaintsController::class, 'track']
        ) {
            if (!headers_sent()) {
                header('Cache-Control: no-store');
                header('Referrer-Policy: no-referrer');
            }
        }

        if (!empty($route['middleware']) && in_array('csrf', $route['middleware'], true)) {
            if (!isset($_POST['_csrf']) || !hash_equals(Csrf::token(), (string) $_POST['_csrf'])) {
                if (
                    $handler === [ApplicationController::class, 'submit']
                    && ApplicationController::requestExceedsPostMaxSize()
                ) {
                    return self::authorizedRoute($route, $ability);
                }

                self::status(403);
                View::render('pages/error', ['title' => '403', 'message' => 'Token CSRF tidak valid atau tidak ada.']);
                return false;
            }
        }

        return self::authorizedRoute($route, $ability);
    }

    private static function authorizedRoute(array $route, string $ability): bool
    {
        $user = Auth::user();
        $allowedRoles = Permissions::routeRoles($ability);

        if ($user === null) {
            if (in_array('GUEST', $allowedRoles, true)) {
                return true;
            }

            self::redirect('/login', 302);
            return false;
        }

        $role = strtoupper((string) ($user['role_code'] ?? $user['role'] ?? 'GUEST'));
        if (!Permissions::can($role, $ability)) {
            self::status(403);
            View::render('pages/error', ['title' => '403', 'message' => 'Anda tidak berhak mengakses halaman ini.']);
            return false;
        }

        return true;
    }

    private static function invokeHandler(mixed $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (method_exists($class, $method)) {
                $class::$method($params);
                return;
            }
        }

        if (is_callable($handler)) {
            $handler($params);
            return;
        }
    }

    private static function matches(string $pattern, string $path, array &$params): bool
    {
        if ($pattern === $path) {
            return true;
        }

        if (!str_contains($pattern, '{')) {
            return false;
        }

        $regex = preg_quote($pattern, '#');
        $regex = preg_replace('/\\\{([a-zA-Z0-9_]+)\\\}/', '([^/]+)', $regex);
        if (preg_match('#^' . $regex . '$#', $path, $matches)) {
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $pattern, $keys);
            foreach ($keys[1] as $index => $key) {
                $params[$key] = $matches[$index + 1] ?? null;
            }
            return true;
        }

        return false;
    }

    private static function redirect(string $location, int $code = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $location, true, $code);
        }
    }

    private static function status(int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    private static function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }
}
