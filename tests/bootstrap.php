<?php
declare(strict_types=1);

putenv('APP_ENV=test');
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$testDb = Config::database('test');
if (($testDb['name'] ?? '') !== 'humamantap_test') {
    throw new RuntimeException('Test bootstrap requires humamantap_test; active DB name is ' . ($testDb['name'] ?? 'unknown'));
}

$dsn = 'mysql:host=' . $testDb['host'] . ';port=' . $testDb['port'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $testDb['user'], $testDb['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('DROP DATABASE IF EXISTS humamantap_test');
$pdo->exec('CREATE DATABASE humamantap_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
Db::pdo('test');
