<?php

header('Content-Type: application/json');

$root = dirname(__DIR__);

echo json_encode([
    'ok' => true,
    'php_version' => PHP_VERSION,
    'extensions' => [
        'pdo_sqlsrv' => extension_loaded('pdo_sqlsrv'),
        'sqlsrv' => extension_loaded('sqlsrv'),
        'openssl' => extension_loaded('openssl'),
        'mbstring' => extension_loaded('mbstring'),
        'pdo' => extension_loaded('PDO'),
    ],
    'files' => [
        'artisan' => file_exists($root.'/artisan'),
        'vendor_autoload' => file_exists($root.'/vendor/autoload.php'),
        'bootstrap_app' => file_exists($root.'/bootstrap/app.php'),
        'storage_writable' => is_writable($root.'/storage'),
        'cache_writable' => is_writable($root.'/bootstrap/cache'),
    ],
    'env' => [
        'app_env' => getenv('APP_ENV') ?: null,
        'app_debug' => getenv('APP_DEBUG') ?: null,
        'db_connection' => getenv('DB_CONNECTION') ?: null,
        'db_host_set' => getenv('DB_HOST') !== false,
        'db_database_set' => getenv('DB_DATABASE') !== false,
        'db_username_set' => getenv('DB_USERNAME') !== false,
        'db_password_set' => getenv('DB_PASSWORD') !== false,
    ],
], JSON_PRETTY_PRINT);
