<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    public static function activeDatabaseKey(): string
    {
        $env = strtolower((string) (getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'development')));
        return $env === 'test' ? 'test' : 'default';
    }

    public static function activeDatabaseName(): string
    {
        $database = self::database(self::activeDatabaseKey());
        return (string) ($database['name'] ?? self::activeDatabaseKey());
    }

    public static function load(): array
    {
        static $config = null;

        if ($config === null) {
            $path = dirname(__DIR__, 2) . '/config/config.php';
            if (!is_file($path)) {
                throw new \RuntimeException('Missing config/config.php. Copy config.example.php into config.php first.');
            }
            $config = require $path;
        }

        return $config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::load();
        return $config[$key] ?? $default;
    }

    public static function database(string $name = 'default'): array
    {
        $config = self::load();
        $databases = $config['databases'] ?? [];
        $requested = strtolower((string)$name);

        if ($requested === 'default') {
            $requested = self::activeDatabaseKey();
        }

        if ($requested === 'humamantap_test') {
            $requested = 'test';
        }

        if ($requested === 'humamantap_dev' || $requested === 'development') {
            $requested = 'default';
        }

        return $databases[$requested] ?? $databases['default'] ?? [];
    }
}
