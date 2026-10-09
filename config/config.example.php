<?php
return [
    'app' => [
        'name' => 'HUMA MANTAP',
        'env' => 'development',
        'base_url' => 'http://localhost:8000',
    ],
    'databases' => [
        'default' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'humamantap_dev',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'test' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'humamantap_test',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'security' => [
        'session_lifetime_seconds' => 7200,
        'login_attempt_limit' => 5,
        'ip_attempt_limit' => 30,
        'lockout_window_seconds' => 15 * 60,
        'upload_max_size_bytes' => 5 * 1024 * 1024,
        'allowed_upload_types' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],
    'trusted_proxies' => [],
];
