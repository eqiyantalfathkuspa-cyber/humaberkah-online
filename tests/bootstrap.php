<?php
declare(strict_types=1);

putenv('APP_ENV=test');
putenv('HUMA_TEST_DATABASE_NAME');
putenv('HUMA_TEST_FIXTURE_TOKEN');
putenv('HUMA_MIGRATOR_TEST_CRASH_AT');
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';
unset($_SERVER['HUMA_TEST_DATABASE_NAME'], $_ENV['HUMA_TEST_DATABASE_NAME']);

require __DIR__ . '/../app/Core/helpers.php';
require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Db.php';
require __DIR__ . '/../app/Core/Csrf.php';
require __DIR__ . '/../app/Core/View.php';
require __DIR__ . '/../app/Core/Router.php';
require __DIR__ . '/../app/Policies/Permissions.php';
require __DIR__ . '/../app/Core/IdGen.php';
require __DIR__ . '/../app/Core/Auth.php';

use App\Core\Config;
use App\Core\Db;

if (!file_exists(__DIR__ . '/../config/config.php')) {
    throw new RuntimeException('config.php missing.');
}

$testDb = Config::database('test');
if (
    ($testDb['name'] ?? null) !== 'humamantap_test'
    ||
    !isset($testDb['host'], $testDb['port'], $testDb['user'], $testDb['pass'])
    || !is_string($testDb['host'])
    || !is_string($testDb['user'])
    || !is_string($testDb['pass'])
) {
    throw new RuntimeException('Konfigurasi koneksi basis data tes tidak lengkap.');
}

$dsn = 'mysql:host=' . $testDb['host'] . ';port=' . $testDb['port'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $testDb['user'], $testDb['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$fixtureName = 'humamantap_test_' . bin2hex(random_bytes(12));
$fixtureToken = bin2hex(random_bytes(32));
$fixtureOwnershipEstablished = false;
if (!preg_match('/^humamantap_test_[a-f0-9]{24}$/', $fixtureName)) {
    throw new RuntimeException('Nama fixture tes tidak valid.');
}
$pdo->exec('CREATE DATABASE ' . $fixtureName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$fixtureCreatedByThisProcess = true;
putenv('HUMA_TEST_DATABASE_NAME=' . $fixtureName);
putenv('HUMA_TEST_FIXTURE_TOKEN=' . $fixtureToken);
register_shutdown_function(static function () use ($testDb, $fixtureName, $fixtureToken, &$fixtureCreatedByThisProcess, &$fixtureOwnershipEstablished): void {
    if (!$fixtureCreatedByThisProcess) {
        return;
    }
    try {
        if (!preg_match('/^humamantap_test_[a-f0-9]{24}$/', $fixtureName)) {
            throw new RuntimeException('Nama fixture berubah sebelum pembersihan.');
        }
        if ($fixtureOwnershipEstablished) {
            $cleanupPdo = new PDO(
                'mysql:host=' . $testDb['host'] . ';port=' . $testDb['port'] . ';dbname=' . $fixtureName . ';charset=utf8mb4',
                $testDb['user'],
                $testDb['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $ownership = $cleanupPdo->query(
                'SELECT fixture_token FROM __huma_test_fixture WHERE fixture_id = 1'
            )->fetchColumn();
            if (!is_string($ownership) || !hash_equals($fixtureToken, $ownership)) {
                throw new RuntimeException('Tidak dapat membuktikan kepemilikan fixture; database dibiarkan utuh.');
            }
        }
        $serverPdo = new PDO(
            'mysql:host=' . $testDb['host'] . ';port=' . $testDb['port'] . ';charset=utf8mb4',
            $testDb['user'],
            $testDb['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $serverPdo->exec('DROP DATABASE ' . $fixtureName);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Pembersihan fixture tes gagal: ' . $exception->getMessage() . PHP_EOL);
    }
});

$pdo->exec('USE ' . $fixtureName);
$activeFixture = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($activeFixture !== $fixtureName) {
    throw new RuntimeException('Koneksi bootstrap tidak menunjuk database fixture yang baru dibuat.');
}
$marker = $pdo->prepare(
    'CREATE TABLE __huma_test_fixture (fixture_id TINYINT UNSIGNED NOT NULL PRIMARY KEY, fixture_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL)'
);
$marker->execute();
$markerInsert = $pdo->prepare(
    'INSERT INTO __huma_test_fixture (fixture_id, fixture_token) VALUES (1, ?)'
);
$markerInsert->execute([$fixtureToken]);
$fixtureOwnershipEstablished = true;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Db::pdo('test');
