<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/helpers.php';
require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Db.php';
require __DIR__ . '/../app/Core/Csrf.php';
require __DIR__ . '/../app/Core/View.php';
require __DIR__ . '/../app/Core/Router.php';
require __DIR__ . '/../app/Core/IdGen.php';

use App\Core\Config;
use App\Core\Db;

if (!file_exists(__DIR__ . '/../config/config.php')) {
    throw new RuntimeException('config.php missing.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Db::pdo('test');
