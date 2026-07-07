<?php

$config = require __DIR__ . '/../../config.php';
$pdo = require __DIR__ . '/../../db.php';

$token = $config['telegram_token'] ?? '';

if($token === '') {
    echo "TELEGRAM_TOKEN is empty\n";
    exit(1);
}

echo "Token: " . maskToken($token) . "\n";

$me = telegramRequest($token, 'getMe');
echo "getMe: " . formatTelegramResult($me) . "\n";

$webhook = telegramRequest($token, 'getWebhookInfo');
echo "getWebhookInfo: " . formatTelegramResult($webhook) . "\n";

$friendsCount = (int) $pdo->query('SELECT COUNT(*) FROM friends')->fetchColumn();
$phrasesCount = (int) $pdo->query('SELECT COUNT(*) FROM phrases')->fetchColumn();
echo "Database: {$friendsCount} friends, {$phrasesCount} phrases\n";

function telegramRequest(string $token, string $method): array
{
    $result = file_get_contents(
        "https://api.telegram.org/bot{$token}/{$method}",
        false,
        stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ])
    );

    if($result === false) {
        return ['ok' => false, 'description' => 'Request failed'];
    }

    return json_decode($result, true) ?: ['ok' => false, 'description' => $result];
}

function formatTelegramResult(array $result): string
{
    if(($result['ok'] ?? false) !== true) {
        return 'ERROR ' . ($result['error_code'] ?? '') . ' ' . ($result['description'] ?? 'Unknown error');
    }

    if(isset($result['result']['username'])) {
        return 'OK @' . $result['result']['username'];
    }

    if(isset($result['result']['url'])) {
        return 'OK url=' . ($result['result']['url'] ?: '<empty>');
    }

    return 'OK';
}

function maskToken(string $token): string
{
    if(strlen($token) <= 12) {
        return '***';
    }

    return substr($token, 0, 6) . '...' . substr($token, -6);
}
