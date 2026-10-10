<?php
declare(strict_types=1);

require __DIR__ . '/../app/autoload.php';

use App\Core\Router;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
Router::dispatch($method, $uri);
