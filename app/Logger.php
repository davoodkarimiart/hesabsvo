<?php

declare(strict_types=1);

final class Logger {
    public static function audit(string $action, array $context = []): void { self::write('audit', $action, $context); }
    public static function system(string $event, array $context = []): void { self::write('system', $event, $context); }
    public static function error(string $message, array $context = []): void { self::write('error', $message, $context); }

    private static function write(string $channel, string $message, array $context): void {
        $record = [
            'ts' => date(DATE_ATOM),
            'channel' => $channel,
            'message' => $message,
            'user_id' => $_SESSION['user']['id'] ?? null,
            'ip_hash' => function_exists('ip_hash') ? ip_hash() : null,
            'context' => self::sanitize($context),
        ];
        $dir = base_path('storage/logs');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/' . $channel . '.log', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function sanitize(array $context): array {
        $blocked = ['password','pass','token','secret','bot_token','authorization','cookie'];
        $out = [];
        foreach ($context as $k => $v) {
            if (in_array(strtolower((string)$k), $blocked, true)) $out[$k] = '[REDACTED]';
            elseif (is_array($v)) $out[$k] = self::sanitize($v);
            else $out[$k] = is_string($v) && strlen($v) > 1000 ? substr($v, 0, 1000) . '…' : $v;
        }
        return $out;
    }
}
