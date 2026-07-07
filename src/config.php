<?php

require_once __DIR__ . '/env.php';

$envPath = dirname(__DIR__) . '/.env';

if(!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env';
}

loadEnv($envPath);

return [
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? 'mysql',
        'dbname' => $_ENV['DB_NAME'] ?? 'bot',
        'user' => $_ENV['DB_USER'] ?? 'bot',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
    'telegram_token' => $_ENV['TELEGRAM_TOKEN'] ?? '',
    'admin_chat_id' => $_ENV['ADMIN_CHAT_ID'] ?? '',
];
