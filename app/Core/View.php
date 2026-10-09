<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $base = dirname(__DIR__, 2) . '/app/Views';
        $viewPath = $base . '/' . ltrim($view, '/') . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException('View not found: ' . $viewPath);
        }

        extract($data, EXTR_SKIP);
        require $base . '/layouts/main.php';
    }
}
