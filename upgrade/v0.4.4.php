<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireDeveloper();
header('Content-Type: text/plain; charset=utf-8');
try {
    $done = Migrator::runPending();
    $pdo = Database::connection();
    $hasExclusions=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customer_autogroup_exclusions'")->fetchColumn();
    if(!$hasExclusions) throw new RuntimeException('جدول customer_autogroup_exclusions هنوز ساخته نشده؛ Migration 006 را بررسی کن.');
    try{$pdo->prepare("INSERT INTO app_meta(meta_key,meta_value) VALUES('app_version','0.4.4') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)")->execute();}catch(Throwable){}
    echo "Soltan Hesab v0.4.4 repair\n";
    echo $done ? "Applied: ".implode(', ', $done)."\n" : "No pending migrations.\n";
    echo "Latest: ".Migrator::latest()."\n";
    echo "customer_autogroup_exclusions: OK\n";
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: ".$e->getMessage()."\n";
}
