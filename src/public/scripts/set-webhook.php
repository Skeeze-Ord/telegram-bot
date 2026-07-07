<?php

$config = require __DIR__ . '/../../config.php';

$webhookUrl = $argv[1] ?? ($_ENV['WEBHOOK_URL'] ?? '');

if($webhookUrl === '') {
    echo "Usage: php public/scripts/set-webhook.php https://your-public-domain.example/bot.php\n";
    exit(1);
}

if(!str_starts_with($webhookUrl, 'https://')) {
    echo "Webhook URL must start with https://\n";
    exit(1);
}

$result = file_get_contents(
    "https://api.telegram.org/bot{$config['telegram_token']}/setWebhook",
    false,
    stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'url' => $webhookUrl,
                'allowed_updates' => json_encode(['message', 'callback_query']),
            ]),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ])
);

echo $result . PHP_EOL;
