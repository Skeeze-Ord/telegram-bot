<?php
$pdo = require __DIR__ . '/../../db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $friend_id = (int) ($_POST['friend_id'] ?? 0);
    $text = trim($_POST['text'] ?? '');

    if($friend_id > 0 && $text !== '') {
        $stmt = $pdo->prepare('INSERT INTO phrases (friend_id, text) VALUES (?, ?)');
        $stmt->execute([$friend_id, $text]);

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$friends = $pdo->query('SELECT * FROM friends ORDER BY name')->fetchAll();
$phrases = $pdo->query('
    SELECT phrases.*, friends.name
    FROM phrases
    JOIN friends ON friends.id = phrases.friend_id
    ORDER BY phrases.id DESC
')->fetchAll();

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Фразы - Админка бота</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <h1 class="brand__title">Фразы</h1>
                <div class="brand__meta">База смешных фраз, которые бот отправляет в Telegram</div>
            </div>

            <nav class="nav" aria-label="Админ-навигация">
                <a class="nav__link" href="/admin/friends.php">Друзья</a>
                <a class="nav__link nav__link--active" href="/admin/phrases.php">Фразы</a>
            </nav>
        </header>

        <section class="grid">
            <aside class="panel">
                <div class="panel__header">
                    <h2 class="panel__title">Новая фраза</h2>
                    <p class="panel__hint">Выбери друга и добавь одну фразу. После сохранения список обновится.</p>
                </div>

                <?php if(count($friends) === 0): ?>
                    <div class="form">
                        <p class="notice">Сначала добавь хотя бы одного друга.</p>
                        <a class="nav__link nav__link--active" href="/admin/friends.php">Перейти к друзьям</a>
                    </div>
                <?php else: ?>
                    <form class="form" method="POST">
                        <label class="field">
                            <span>Друг</span>
                            <select name="friend_id" required>
                                <?php foreach($friends as $friend): ?>
                                    <option value="<?= e($friend['id']) ?>">
                                        <?= e($friend['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            <span>Фраза</span>
                            <input name="text" placeholder="Введи цитату" autocomplete="off" required>
                        </label>

                        <button>Добавить</button>
                    </form>
                <?php endif; ?>
            </aside>

            <section class="panel">
                <div class="toolbar">
                    <h2 class="panel__title">Все фразы</h2>
                    <span class="count"><?= count($phrases) ?></span>
                </div>

                <?php if(count($phrases) === 0): ?>
                    <p class="empty">Фраз пока нет. Добавь первую через форму слева.</p>
                <?php else: ?>
                    <ul class="list">
                        <?php foreach($phrases as $phrase): ?>
                            <li class="item">
                                <span class="quote"><?= e($phrase['text']) ?></span>
                                <span class="item__meta"><?= e($phrase['name']) ?><?php if(!empty($phrase['created_at'])): ?> · <?= e($phrase['created_at']) ?><?php endif; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </section>
    </main>
</body>
</html>
