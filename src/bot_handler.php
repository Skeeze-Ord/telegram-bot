<?php

function handleTelegramUpdate(PDO $pdo, array $config, array $data): void
{
    $token = $config['telegram_token'] ?? '';

    if($token === '') {
        error_log('TELEGRAM_TOKEN is not configured');
        return;
    }

    if(isset($data['callback_query'])) {
        handleCallbackQuery($pdo, $config, $data['callback_query']);
        return;
    }

    handleMessage($pdo, $config, $data['message'] ?? []);
}

function handleMessage(PDO $pdo, array $config, array $message): void
{
    $chat_id = $message['chat']['id'] ?? null;
    $text = trim($message['text'] ?? '');

    if(!$chat_id || $text === '') {
        return;
    }

    error_log("Telegram message received: chat_id={$chat_id}, text={$text}");

    if($text === '/start' || $text === '/friends') {
        clearUserState($pdo, $chat_id);
        sendFriendsKeyboard($pdo, $config, $chat_id);
        return;
    }

    $state = getUserState($pdo, $chat_id);

    if($state && $state['action'] === 'add_phrase') {
        handlePhraseSuggestion($pdo, $config, $chat_id, $state, $text);
        return;
    }

    sendTelegramMessage($config, $chat_id, 'Нажми /start и выбери друга кнопкой.');
}

function handleCallbackQuery(PDO $pdo, array $config, array $callback): void
{
    $callback_id = $callback['id'] ?? null;
    $chat_id = $callback['message']['chat']['id'] ?? null;
    $message_id = $callback['message']['message_id'] ?? null;
    $data = $callback['data'] ?? '';

    if(!$chat_id || $data === '') {
        if($callback_id) {
            answerCallbackQuery($config, $callback_id);
        }

        return;
    }

    error_log("Telegram callback received: chat_id={$chat_id}, data={$data}");

    if($callback_id) {
        answerCallbackQuery($config, $callback_id);
    }

    if($data === 'friends:list') {
        deleteTelegramMessage($config, $chat_id, $message_id);
        sendFriendsKeyboard($pdo, $config, $chat_id);
        return;
    }

    if(preg_match('/^friend:(\d+)$/', $data, $matches)) {
        $friend = findFriendById($pdo, (int) $matches[1]);

        if(!$friend) {
            sendTelegramMessage($config, $chat_id, 'Друг не найден.');
            return;
        }

        deleteTelegramMessage($config, $chat_id, $message_id);
        sendFriendActionsKeyboard($config, $chat_id, (int) $friend['id'], $friend['name']);
        return;
    }

    if(preg_match('/^phrases:(\d+):(random|all|suggest)$/', $data, $matches)) {
        $friend = findFriendById($pdo, (int) $matches[1]);

        if(!$friend) {
            sendTelegramMessage($config, $chat_id, 'Друг не найден.');
            return;
        }

        if($matches[2] === 'random') {
            deleteTelegramMessage($config, $chat_id, $message_id);
            sendRandomPhrase($pdo, $config, $chat_id, (int) $friend['id'], $friend['name']);
            return;
        }

        if($matches[2] === 'suggest') {
            deleteTelegramMessage($config, $chat_id, $message_id);
            $response = sendTelegramMessage($config, $chat_id, "Напиши новую фразу для {$friend['name']} одним сообщением.");
            $prompt_message_id = $response['result']['message_id'] ?? null;
            setUserState($pdo, $chat_id, 'add_phrase', (int) $friend['id'], $prompt_message_id);
            return;
        }

        deleteTelegramMessage($config, $chat_id, $message_id);
        sendAllPhrases($pdo, $config, $chat_id, (int) $friend['id'], $friend['name']);
        return;
    }

    if(preg_match('/^suggestion:(\d+):(approve|reject)$/', $data, $matches)) {
        handleSuggestionReview($pdo, $config, $chat_id, $message_id, (int) $matches[1], $matches[2]);
    }
}

function sendFriendsKeyboard(PDO $pdo, array $config, $chat_id): void
{
    $friends = $pdo->query('SELECT id, name FROM friends ORDER BY name')->fetchAll();

    if(count($friends) === 0) {
        sendTelegramMessage($config, $chat_id, 'Пока нет друзей в базе.');
        return;
    }

    $keyboard = [];

    foreach(array_chunk($friends, 2) as $row) {
        $keyboardRow = [];

        foreach($row as $friend) {
            $keyboardRow[] = [
                'text' => $friend['name'],
                'callback_data' => 'friend:' . $friend['id'],
            ];
        }

        $keyboard[] = $keyboardRow;
    }

    sendTelegramMessage(
        $config,
        $chat_id,
        'Выбери друга:',
        ['inline_keyboard' => $keyboard]
    );
}

function sendFriendActionsKeyboard(array $config, $chat_id, int $friend_id, string $friend_name): void
{
    sendTelegramMessage(
        $config,
        $chat_id,
        "Что показать для {$friend_name}?",
        [
            'inline_keyboard' => [
                [
                    ['text' => 'Случайная фраза', 'callback_data' => "phrases:{$friend_id}:random"],
                    ['text' => 'Все фразы', 'callback_data' => "phrases:{$friend_id}:all"],
                ],
                [
                    ['text' => 'Предложить фразу', 'callback_data' => "phrases:{$friend_id}:suggest"],
                ],
                [
                    ['text' => 'Выбрать другого', 'callback_data' => 'friends:list'],
                ],
            ],
        ]
    );
}

function handlePhraseSuggestion(PDO $pdo, array $config, $chat_id, array $state, string $text): void
{
    $friend = findFriendById($pdo, (int) $state['friend_id']);
    deleteTelegramMessage($config, $chat_id, $state['bot_message_id'] ?? null);
    clearUserState($pdo, $chat_id);

    if(!$friend) {
        sendTelegramMessage($config, $chat_id, 'Друг не найден. Нажми /start и попробуй снова.');
        return;
    }

    $admin_chat_id = $config['admin_chat_id'] ?? '';

    if($admin_chat_id === '') {
        sendTelegramMessage($config, $chat_id, 'Не получилось отправить фразу на проверку: ADMIN_CHAT_ID не настроен.');
        error_log('ADMIN_CHAT_ID is not configured');
        return;
    }

    $suggestion_id = createPhraseSuggestion($pdo, (int) $friend['id'], $text, (int) $chat_id);
    $from = "chat_id={$chat_id}";
    $message = "Новая предложенная фраза #{$suggestion_id}\n\nДруг: {$friend['name']}\nОт: {$from}\n\n{$text}";

    sendTelegramMessage(
        $config,
        $admin_chat_id,
        $message,
        [
            'inline_keyboard' => [
                [
                    ['text' => 'Одобрить', 'callback_data' => "suggestion:{$suggestion_id}:approve"],
                    ['text' => 'Отклонить', 'callback_data' => "suggestion:{$suggestion_id}:reject"],
                ],
            ],
        ]
    );
    sendTelegramMessage($config, $chat_id, 'Фраза отправлена на проверку.');
}

function handleSuggestionReview(PDO $pdo, array $config, $chat_id, $message_id, int $suggestion_id, string $action): void
{
    $admin_chat_id = (string) ($config['admin_chat_id'] ?? '');

    if($admin_chat_id === '' || (string) $chat_id !== $admin_chat_id) {
        sendTelegramMessage($config, $chat_id, 'Это действие доступно только администратору.');
        return;
    }

    $suggestion = findSuggestionById($pdo, $suggestion_id);

    if(!$suggestion) {
        sendTelegramMessage($config, $chat_id, 'Предложение не найдено.');
        return;
    }

    if($suggestion['status'] !== 'pending') {
        sendTelegramMessage($config, $chat_id, "Предложение уже обработано: {$suggestion['status']}.");
        return;
    }

    if($action === 'approve') {
        $stmt = $pdo->prepare('INSERT INTO phrases (friend_id, text) VALUES (?, ?)');
        $stmt->execute([(int) $suggestion['friend_id'], $suggestion['text']]);
        updateSuggestionStatus($pdo, $suggestion_id, 'approved');
        deleteTelegramMessage($config, $chat_id, $message_id);

        sendTelegramMessage($config, $chat_id, "Предложение #{$suggestion_id} одобрено и добавлено в базу.");
        sendTelegramMessage($config, $suggestion['suggester_chat_id'], 'Твою фразу одобрили и добавили в базу.');
        return;
    }

    updateSuggestionStatus($pdo, $suggestion_id, 'rejected');
    deleteTelegramMessage($config, $chat_id, $message_id);
    sendTelegramMessage($config, $chat_id, "Предложение #{$suggestion_id} отклонено.");
    sendTelegramMessage($config, $suggestion['suggester_chat_id'], 'Твою фразу отклонили.');
}

function sendRandomPhrase(PDO $pdo, array $config, $chat_id, int $friend_id, string $friend_name): void
{
    $stmt = $pdo->prepare('
        SELECT text FROM phrases
        WHERE friend_id = ?
        ORDER BY RAND()
        LIMIT 1
    ');
    $stmt->execute([$friend_id]);
    $phrase = $stmt->fetchColumn();

    sendTelegramMessage(
        $config,
        $chat_id,
        $phrase ? "{$friend_name}:\n{$phrase}" : "У {$friend_name} пока нет фраз."
    );
}

function sendAllPhrases(PDO $pdo, array $config, $chat_id, int $friend_id, string $friend_name): void
{
    $stmt = $pdo->prepare('
        SELECT text FROM phrases
        WHERE friend_id = ?
        ORDER BY id
    ');
    $stmt->execute([$friend_id]);
    $phrases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if(count($phrases) === 0) {
        sendTelegramMessage($config, $chat_id, "У {$friend_name} пока нет фраз.");
        return;
    }

    $lines = ["Все фразы {$friend_name}:"];

    foreach($phrases as $index => $phrase) {
        $lines[] = ($index + 1) . '. ' . $phrase;
    }

    foreach(splitTelegramMessage(implode("\n\n", $lines)) as $chunk) {
        sendTelegramMessage($config, $chat_id, $chunk);
    }
}

function findFriendById(PDO $pdo, int $friend_id): ?array
{
    $stmt = $pdo->prepare('SELECT id, name FROM friends WHERE id = ?');
    $stmt->execute([$friend_id]);
    $friend = $stmt->fetch();

    return $friend ?: null;
}

function createPhraseSuggestion(PDO $pdo, int $friend_id, string $text, int $suggester_chat_id): int
{
    $stmt = $pdo->prepare('
        INSERT INTO phrase_suggestions (friend_id, text, suggester_chat_id)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$friend_id, $text, $suggester_chat_id]);

    return (int) $pdo->lastInsertId();
}

function findSuggestionById(PDO $pdo, int $suggestion_id): ?array
{
    $stmt = $pdo->prepare('
        SELECT phrase_suggestions.*, friends.name AS friend_name
        FROM phrase_suggestions
        JOIN friends ON friends.id = phrase_suggestions.friend_id
        WHERE phrase_suggestions.id = ?
    ');
    $stmt->execute([$suggestion_id]);
    $suggestion = $stmt->fetch();

    return $suggestion ?: null;
}

function updateSuggestionStatus(PDO $pdo, int $suggestion_id, string $status): void
{
    $stmt = $pdo->prepare('
        UPDATE phrase_suggestions
        SET status = ?, reviewed_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->execute([$status, $suggestion_id]);
}

function getUserState(PDO $pdo, $chat_id): ?array
{
    $stmt = $pdo->prepare('SELECT action, friend_id, bot_message_id FROM user_states WHERE chat_id = ?');
    $stmt->execute([$chat_id]);
    $state = $stmt->fetch();

    return $state ?: null;
}

function setUserState(PDO $pdo, $chat_id, string $action, int $friend_id, $bot_message_id = null): void
{
    $stmt = $pdo->prepare('
        INSERT INTO user_states (chat_id, action, friend_id, bot_message_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            action = VALUES(action),
            friend_id = VALUES(friend_id),
            bot_message_id = VALUES(bot_message_id),
            created_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([$chat_id, $action, $friend_id, $bot_message_id]);
}

function clearUserState(PDO $pdo, $chat_id): void
{
    $stmt = $pdo->prepare('DELETE FROM user_states WHERE chat_id = ?');
    $stmt->execute([$chat_id]);
}

function splitTelegramMessage(string $text): array
{
    $limit = 3500;
    $chunks = [];
    $current = '';

    foreach(explode("\n\n", $text) as $part) {
        $next = $current === '' ? $part : $current . "\n\n" . $part;

        if(strlen($next) > $limit && $current !== '') {
            $chunks[] = $current;
            $current = $part;
            continue;
        }

        $current = $next;
    }

    if($current !== '') {
        $chunks[] = $current;
    }

    return $chunks;
}

function sendTelegramMessage(array $config, $chat_id, string $text, ?array $reply_markup = null): ?array
{
    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];

    if($reply_markup !== null) {
        $payload['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
    }

    return telegramApiRequest($config, 'sendMessage', $payload);
}

function answerCallbackQuery(array $config, string $callback_id): void
{
    telegramApiRequest($config, 'answerCallbackQuery', [
        'callback_query_id' => $callback_id,
    ]);
}

function deleteTelegramMessage(array $config, $chat_id, $message_id): void
{
    if(!$chat_id || !$message_id) {
        return;
    }

    telegramApiRequest($config, 'deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
    ]);
}

function telegramApiRequest(array $config, string $method, array $payload): ?array
{
    $url = "https://api.telegram.org/bot{$config['telegram_token']}/{$method}";
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $result = file_get_contents($url, false, $context);

    if($result === false) {
        error_log("Telegram {$method} failed: request failed");
        return null;
    }

    $response = json_decode($result, true);

    if(!is_array($response) || ($response['ok'] ?? false) !== true) {
        error_log(
            "Telegram {$method} failed: " .
            json_encode([
                'payload' => $payload,
                'response' => $response ?: $result,
            ], JSON_UNESCAPED_UNICODE)
        );
    }

    return $response;
}
