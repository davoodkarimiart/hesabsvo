<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireDeveloper();
header('Content-Type: text/plain; charset=utf-8');
try {
    $done = Migrator::runPending();
    echo "Soltan Hesab v0.4.2 upgrade\n";
    echo $done ? "Applied: ".implode(', ', $done)."\n" : "No pending migrations.\n";
    echo "Latest: ".Migrator::latest()."\n";
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: ".$e->getMessage()."\n";
}
