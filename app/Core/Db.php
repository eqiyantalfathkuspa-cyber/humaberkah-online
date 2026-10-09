<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Db
{
    private static array $pdoCache = [];

    public static function pdo(string $database = 'default'): PDO
    {
        $activeKey = Config::activeDatabaseKey();
        $requested = strtolower((string) $database);
        $resolved = $requested === 'default' ? $activeKey : $requested;

        if ($resolved === 'humamantap_test') {
            $resolved = 'test';
        }
        if ($resolved === 'humamantap_dev' || $resolved === 'development') {
            $resolved = 'default';
        }

        if ($activeKey === 'test' && $resolved !== 'test') {
            throw new \RuntimeException('Test environment requires humamantap_test; received database key: ' . $database);
        }
        if ($activeKey !== 'test' && $resolved === 'test') {
            throw new \RuntimeException('Development environment cannot use humamantap_test; received database key: ' . $database);
        }

        $key = $resolved;
        if (!isset(self::$pdoCache[$key])) {
            $c = Config::database($resolved);
            $dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['name'] . ';charset=' . ($c['charset'] ?? 'utf8mb4');
            self::$pdoCache[$key] = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$pdoCache[$key];
    }

    public static function all(string $sql, array $params = [], string $database = 'default'): array
    {
        $st = self::pdo($database)->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = [], string $database = 'default'): ?array
    {
        $st = self::pdo($database)->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: null;
    }

    public static function run(string $sql, array $params = [], string $database = 'default'): int
    {
        $st = self::pdo($database)->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }
}
