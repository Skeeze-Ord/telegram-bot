<?php

$config = require __DIR__ . '/../config.php';
$pdo = require __DIR__ . '/../db.php';

require_once __DIR__ . '/../bot_handler.php';

$data = json_decode(file_get_contents('php://input'), true);

handleTelegramUpdate($pdo, $config, is_array($data) ? $data : []);
