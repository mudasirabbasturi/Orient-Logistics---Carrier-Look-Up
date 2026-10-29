<?php
// Load .env if present
if (class_exists('Core\DotEnv')) {
    Core\DotEnv::load(__DIR__ . '/../.env');
}

return [
    'app' => [
        'name' => getenv('APP_NAME') ?: 'Orient Logistics - Carrier Intelligence',
        'url'  => getenv('APP_URL') ?: 'http://localhost:8000',
        'env'  => getenv('APP_ENV') ?: 'production',
    ],
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'carrier-lookup',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        'charset'  => 'utf8mb4',
    ],
    'fmcsa' => [
        'default_webkey' => getenv('FMCSA_DEFAULT_WEBKEY') ?: '134fafdfaa76ae82a7eb3c6a3386e8d0f7f5012a',
    ]
];
