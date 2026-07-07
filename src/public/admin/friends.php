<?php
$pdo = require __DIR__ . '/../../db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO friends (name) VALUES (?)');
        $stmt->execute([$name]);

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$friends = $pdo->query('SELECT * FROM friends ORDER BY name')->fetchAll();

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
    <title>Друзья - Админка бота</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <h1 class="brand__title">Друзья</h1>
                <div class="brand__meta">Список людей, по именам которых бот ищет фразы</div>
            </div>

            <nav class="nav" aria-label="Админ-навигация">
                <a class="nav__link nav__link--active" href="/admin/friends.php">Друзья</a>
                <a class="nav__link" href="/admin/phrases.php">Фразы</a>
            </nav>
        </header>

        <section class="grid">
            <aside class="panel">
                <div class="panel__header">
                    <h2 class="panel__title">Новый друг</h2>
                    <p class="panel__hint">Имя должно совпадать с текстом, который пользователь отправит боту.</p>
                </div>

                <form class="form" method="POST">
                    <label class="field">
                        <span>Имя</span>
                        <input name="name" placeholder="Например, Арман" autocomplete="off" required>
                    </label>

                    <button>Добавить</button>
                </form>
            </aside>

            <section class="panel">
                <div class="toolbar">
                    <h2 class="panel__title">Все друзья</h2>
                    <span class="count"><?= count($friends) ?></span>
                </div>

                <?php if(count($friends) === 0): ?>
                    <p class="empty">Пока никого нет. Добавь первого друга слева.</p>
                <?php else: ?>
                    <ul class="list">
                        <?php foreach($friends as $friend): ?>
                            <li class="item">
                                <span class="item__title"><?= e($friend['name']) ?></span>
                                <?php if(!empty($friend['created_at'])): ?>
                                    <span class="item__meta">Добавлен: <?= e($friend['created_at']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </section>
    </main>
</body>
</html>
