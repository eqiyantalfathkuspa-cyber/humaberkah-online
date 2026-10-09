<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/helpers.php';
require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Db.php';
require __DIR__ . '/../app/Core/View.php';
require __DIR__ . '/../app/Core/Router.php';

use App\Core\Router;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
Router::dispatch($method, $uri);
