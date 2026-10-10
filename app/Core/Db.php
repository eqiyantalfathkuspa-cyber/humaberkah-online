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
        $configuredFixtureName = getenv('HUMA_TEST_DATABASE_NAME');

        if ($resolved === 'humamantap_test') {
            $resolved = 'test';
        }
        if ($activeKey === 'test' && is_string($configuredFixtureName) && $resolved === $configuredFixtureName) {
            $resolved = 'test';
        }
        if ($resolved === 'humamantap_dev' || $resolved === 'development') {
            $resolved = 'default';
        }

        if ($activeKey === 'test' && $resolved !== 'test') {
            throw new \RuntimeException('Test environment permits only its owned fixture database; received database key: ' . $database);
        }
        if ($activeKey !== 'test' && $resolved === 'test') {
            throw new \RuntimeException('Development environment cannot use humamantap_test; received database key: ' . $database);
        }

        $testFixtureName = null;
        $testFixtureToken = null;
        if ($activeKey === 'test') {
            $testFixtureName = getenv('HUMA_TEST_DATABASE_NAME');
            $testFixtureToken = getenv('HUMA_TEST_FIXTURE_TOKEN');
            if (
                $testFixtureName === false
                || !preg_match('/^humamantap_test_[a-f0-9]{24}$/', $testFixtureName)
                || $testFixtureToken === false
                || !preg_match('/^[a-f0-9]{64}$/', $testFixtureToken)
                || (Config::database('test')['name'] ?? null) !== $testFixtureName
            ) {
                throw new \RuntimeException('Koneksi tes memerlukan identitas fixture yang dibuat bootstrap.');
            }
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

        if ($activeKey === 'test') {
            $pdo = self::$pdoCache[$key];
            if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $testFixtureName) {
                throw new \RuntimeException('Koneksi tes tidak menunjuk fixture yang dibuat proses ini.');
            }
            $ownership = $pdo->prepare(
                'SELECT fixture_token FROM __huma_test_fixture WHERE fixture_id = 1'
            );
            $ownership->execute();
            $actualToken = $ownership->fetchColumn();
            if (!is_string($actualToken) || !hash_equals($testFixtureToken, $actualToken)) {
                throw new \RuntimeException('Kepemilikan fixture tes tidak dapat diverifikasi.');
            }
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
