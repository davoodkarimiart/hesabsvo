<?php

declare(strict_types=1);

final class Logger {
    private static ?string $requestId = null;
    private static bool $writing = false;

    public static function requestId(): string {
        if (self::$requestId === null) self::$requestId = bin2hex(random_bytes(8));
        return self::$requestId;
    }
    public static function audit(string $action, array $context = []): void { self::write('audit', $action, $context); }
    public static function system(string $event, array $context = []): void { self::write('system', $event, $context); }
    public static function error(string $message, array $context = []): void { self::write('error', $message, $context); }

    private static function write(string $channel, string $message, array $context): void {
        if (self::$writing) return;
        self::$writing = true;
        try {
            $record = [
                'ts' => date(DATE_ATOM), 'channel' => $channel,
                'request_id' => self::requestId(),
                'message' => substr($message, 0, 400),
                'user_id' => $_SESSION['user']['id'] ?? null,
                'ip_hash' => function_exists('ip_hash') ? ip_hash() : null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'route' => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '',
                'context' => self::sanitize($context),
            ];
            $dir = base_path('storage/logs');
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) throw new RuntimeException('Cannot create log directory');
            $line = json_encode($record, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($line === false || file_put_contents($dir.'/'.$channel.'.log', $line.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                throw new RuntimeException('Cannot write log');
            }
        } catch (Throwable $e) {
            error_log('[soltan-logger] log write failed: '.$e->getMessage());
        } finally { self::$writing = false; }
    }

    private static function sanitize(array $context, int $depth = 0): array {
        if ($depth >= 5) return ['_truncated' => true];
        $out = []; $count = 0;
        foreach ($context as $k => $v) {
            if (++$count > 60) { $out['_truncated'] = true; break; }
            $key = (string)$k;
            if (preg_match('/pass(word)?|secret|token|auth|cookie|session|csrf|api.?key|credential|private.?key|database|dsn|email|phone|mobile|card|iban|body|payload|headers?/i', $key)) {
                $out[$key] = '[REDACTED]';
            } elseif (is_array($v)) {
                $out[$key] = self::sanitize($v, $depth + 1);
            } elseif (is_scalar($v) || $v === null) {
                $out[$key] = is_string($v) ? mb_substr($v, 0, 500, 'UTF-8') : $v;
            } else { $out[$key] = '['.get_debug_type($v).']'; }
        }
        return $out;
    }
}
