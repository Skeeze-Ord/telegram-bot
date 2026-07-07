<?php

$config = require __DIR__ . '/../../config.php';
$pdo = require __DIR__ . '/../../db.php';

require_once __DIR__ . '/../../bot_handler.php';

$lock = fopen('/tmp/telegram-bot-poll.lock', 'c');

if(!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Polling is already running in this container.\n";
    exit(1);
}

$offset = null;

echo "Polling started. Press Ctrl+C to stop.\n";

while(true) {
    $params = [
        'timeout' => 25,
        'allowed_updates' => json_encode(['message', 'callback_query']),
    ];

    if($offset !== null) {
        $params['offset'] = $offset;
    }

    $result = telegramRequest($config['telegram_token'], 'getUpdates', $params);

    if(($result['ok'] ?? false) !== true) {
        if(($result['error_code'] ?? null) === 409) {
            echo "getUpdates conflict: another bot instance is already polling Telegram.\n";
            echo "Stop the other polling process, then start this script again.\n";
            exit(1);
        }

        echo 'getUpdates failed: ' . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        sleep(3);
        continue;
    }

    foreach($result['result'] as $update) {
        $offset = $update['update_id'] + 1;
        handleTelegramUpdate($pdo, $config, $update);
    }
}

function telegramRequest(string $token, string $method, array $params): array
{
    $query = http_build_query($params);
    $result = file_get_contents(
        "https://api.telegram.org/bot{$token}/{$method}?{$query}",
        false,
        stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ])
    );

    if($result === false) {
        return ['ok' => false, 'description' => 'Request failed'];
    }

    return json_decode($result, true) ?: ['ok' => false, 'description' => $result];
}
