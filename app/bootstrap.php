<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$configFile = base_path('config/config.php');
if (!is_file($configFile) && !str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/install/')) {
    header('Location: install/');
    exit;
}

if (is_file($configFile)) {
    $tz = (string) config_get('app.timezone', 'Asia/Tehran');
    date_default_timezone_set($tz ?: 'Asia/Tehran');
    ini_set('display_errors', config_get('app.debug', false) ? '1' : '0');
    error_reporting(E_ALL);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime'=>0,
        'path'=>'/',
        'secure'=>$secure,
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Migrator.php';
require_once __DIR__ . '/Support/XlsxReader.php';
require_once __DIR__ . '/Support/NameHelper.php';
require_once __DIR__ . '/Services/CompanyService.php';
require_once __DIR__ . '/Services/AccountSettingsService.php';
require_once __DIR__ . '/Services/AccountingService.php';
require_once __DIR__ . '/Services/SettingsService.php';
require_once __DIR__ . '/Services/CustomerService.php';
require_once __DIR__ . '/Services/ReportingService.php';

set_exception_handler(function(Throwable $e): void {
    if (class_exists('Logger')) Logger::error($e->getMessage(), ['type'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]);
    http_response_code(500);
    $debug = (bool) config_get('app.debug', false);
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:Tahoma;padding:30px"><h2>خطای برنامه</h2><p>یک خطای داخلی رخ داده است.</p>';
    if ($debug) echo '<pre>' . e((string)$e) . '</pre>';
    echo '</body></html>';
});
