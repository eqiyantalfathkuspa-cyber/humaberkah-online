<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Auth.php';

session_start();
App\Core\Auth::login([
    'id' => 1,
    'role' => 'ADMIN',
    'role_code' => 'ADMIN',
    'identifier' => 'cookie-probe',
    'full_name' => 'Cookie Probe',
    'auth_token' => 'probe-token',
]);
echo 'ok';
