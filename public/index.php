<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

set_exception_handler(static function (Throwable $exception): void {
    error_log(sprintf(
        "[HUMA] Uncaught %s: %s\n%s",
        get_class($exception),
        $exception->getMessage(),
        $exception->getTraceAsString()
    ));

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Kesalahan</title><h1>Terjadi kesalahan pada server.</h1></html>';
});

require __DIR__ . '/../app/autoload.php';

use App\Core\Router;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
Router::dispatch($method, $uri);
