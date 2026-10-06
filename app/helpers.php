<?php

declare(strict_types=1);

function base_path(string $path = ''): string {
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

function app_config(): array {
    static $config;
    if ($config !== null) return $config;
    $file = base_path('config/config.php');
    if (!is_file($file)) return [];
    $config = require $file;
    return is_array($config) ? $config : [];
}

function config_get(string $key, mixed $default = null): mixed {
    $value = app_config();
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function is_post(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function app_secret(): string {
    return (string) config_get('app.secret', '');
}

function ip_hash(): string {
    return hash_hmac('sha256', request_ip(), app_secret() ?: 'soltan-hesab-fallback');
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void {
    $sent = (string)($_POST['_csrf'] ?? '');
    $stored = (string)($_SESSION['_csrf'] ?? '');
    if ($stored === '' || !hash_equals($stored, $sent)) {
        throw new RuntimeException('درخواست نامعتبر است. صفحه را تازه‌سازی و دوباره تلاش کنید.');
    }
}

function now_sql(): string {
    return date('Y-m-d H:i:s');
}

function flash_set(string $type, string $message): void {
    $_SESSION['_flash'] = ['type'=>$type,'message'=>$message];
}

function flash_get(): ?array {
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($f) ? $f : null;
}
