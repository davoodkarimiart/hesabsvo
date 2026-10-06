<?php
return [
    'app' => [
        'name' => 'سلطان حساب',
        'url' => '',
        'timezone' => 'Asia/Tehran',
        'installed' => false,
        'secret' => '',
        'debug' => false,
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ],
    'backup' => [
        'keep_local' => 7,
        'schedule' => 'daily',
    ],
];
