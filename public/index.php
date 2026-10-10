<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/helpers.php';
require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Db.php';
require __DIR__ . '/../app/Core/Auth.php';
require __DIR__ . '/../app/Core/View.php';
require __DIR__ . '/../app/Core/Csrf.php';
require __DIR__ . '/../app/Policies/Permissions.php';
require __DIR__ . '/../app/Controllers/AuthController.php';
require __DIR__ . '/../app/Controllers/PortalController.php';
require __DIR__ . '/../app/Controllers/AdminController.php';
require __DIR__ . '/../app/Core/ServiceCatalog.php';
require __DIR__ . '/../app/Core/TrackingCode.php';
require __DIR__ . '/../app/Core/ApplicationWorkflow.php';
require __DIR__ . '/../app/Core/ApplicationTrackingLimiter.php';
require __DIR__ . '/../app/Controllers/ServiceController.php';
require __DIR__ . '/../app/Controllers/ApplicationController.php';
require __DIR__ . '/../app/Core/Router.php';

use App\Core\Router;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
Router::dispatch($method, $uri);
