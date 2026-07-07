<?php

$pdo = require __DIR__ . '/../../db.php';

$sql1 = "
CREATE TABLE IF NOT EXISTS friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY friends_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$sql2 = "
CREATE TABLE IF NOT EXISTS phrases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    friend_id INT NOT NULL,
    text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX phrases_friend_id_index (friend_id),
    FOREIGN KEY (friend_id) REFERENCES friends(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$sql3 = "
CREATE TABLE IF NOT EXISTS user_states (
    chat_id BIGINT PRIMARY KEY,
    action VARCHAR(64) NOT NULL,
    friend_id INT NOT NULL,
    bot_message_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (friend_id) REFERENCES friends(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$sql4 = "
CREATE TABLE IF NOT EXISTS phrase_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    friend_id INT NOT NULL,
    text TEXT NOT NULL,
    suggester_chat_id BIGINT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX phrase_suggestions_friend_id_index (friend_id),
    INDEX phrase_suggestions_status_index (status),
    FOREIGN KEY (friend_id) REFERENCES friends(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($sql1);
$pdo->exec($sql2);
$pdo->exec($sql3);
$pdo->exec($sql4);

$pdo->exec('ALTER TABLE friends CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('ALTER TABLE phrases CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('ALTER TABLE user_states CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('ALTER TABLE phrase_suggestions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

if(!columnExists($pdo, 'friends', 'created_at')) {
    $pdo->exec('ALTER TABLE friends ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
}

if(!columnExists($pdo, 'phrases', 'created_at')) {
    $pdo->exec('ALTER TABLE phrases ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
}

if(!columnExists($pdo, 'user_states', 'bot_message_id')) {
    $pdo->exec('ALTER TABLE user_states ADD bot_message_id BIGINT NULL AFTER friend_id');
}

if(!indexExists($pdo, 'phrases', 'phrases_friend_id_index')) {
    $pdo->exec('ALTER TABLE phrases ADD INDEX phrases_friend_id_index (friend_id)');
}

if(!indexExists($pdo, 'friends', 'friends_name_unique')) {
    try {
        $pdo->exec('ALTER TABLE friends ADD UNIQUE KEY friends_name_unique (name)');
    } catch(PDOException $exception) {
        echo "Skipped unique index friends_name_unique: duplicate names exist\n";
    }
}

echo "Migration completed";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $column]);

    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ');
    $stmt->execute([$table, $index]);

    return (bool) $stmt->fetchColumn();
}
