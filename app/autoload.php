<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $namespaces = [
        'App\\Core\\' => __DIR__ . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR,
        'App\\Controllers\\' => __DIR__ . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR,
        'App\\Policies\\' => __DIR__ . DIRECTORY_SEPARATOR . 'Policies' . DIRECTORY_SEPARATOR,
    ];

    foreach ($namespaces as $namespace => $directory) {
        if (!str_starts_with($class, $namespace)) {
            continue;
        }

        $relativeClass = substr($class, strlen($namespace));
        $file = $directory
            . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
            . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});

require_once __DIR__ . '/Core/helpers.php';
