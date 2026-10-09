<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @return array{status:int, route:string, method:string}
     */
    public static function dispatch(string $method, string $path): array
    {
        $method = strtoupper($method);
        $path = self::normalize($path);

        $routes = [
            'GET' => [
                '/' => static function (): void {
                    View::render('pages/home', ['title' => 'HUMA MANTAP']);
                },
                '/layanan' => static function (): void {
                    View::render('pages/error', [
                        'title' => 'Belum terhubung',
                        'message' => 'Halaman layanan belum terhubung ke proses nyata.',
                    ]);
                },
                '/laporan' => static function (): void {
                    View::render('pages/error', [
                        'title' => 'Belum terhubung',
                        'message' => 'Laporan belum terhubung ke data real-time.',
                    ]);
                },
            ],
            'POST' => [
                '/' => static function (): void {
                    if (!isset($_POST['_csrf']) || !hash_equals(Csrf::token(), (string) $_POST['_csrf'])) {
                        http_response_code(403);
                        View::render('pages/error', [
                            'title' => '403',
                            'message' => 'Token CSRF tidak valid atau tidak ada.',
                        ]);
                        return;
                    }

                    View::render('pages/home', ['title' => 'HUMA MANTAP']);
                },
            ],
        ];

        if (isset($routes[$method][$path])) {
            http_response_code(200);
            $routes[$method][$path]();
            return ['status' => 200, 'route' => $path, 'method' => $method];
        }

        $isKnownPath = isset($routes['GET'][$path]) || isset($routes['POST'][$path]);
        if ($isKnownPath) {
            http_response_code(405);
            View::render('pages/error', [
                'title' => '405',
                'message' => 'Metode HTTP tidak diizinkan untuk rute ini.',
            ]);
            return ['status' => 405, 'route' => $path, 'method' => $method];
        }

        http_response_code(404);
        View::render('pages/error', [
            'title' => '404',
            'message' => 'Halaman tidak ditemukan.',
        ]);

        return ['status' => 404, 'route' => $path, 'method' => $method];
    }

    private static function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }
}
