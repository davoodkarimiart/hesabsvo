<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireDeveloper();
header('Content-Type: text/plain; charset=utf-8');
try {
    $fontDir = dirname(__DIR__) . '/assets/fonts';
    if (!is_dir($fontDir) && !mkdir($fontDir, 0775, true) && !is_dir($fontDir)) {
        throw new RuntimeException('assets/fonts ساخته نشد.');
    }
    // Normalize the legacy filename with a space so the browser never requests two aliases.
    $legacy = $fontDir . '/Iranian Sans.ttf';
    $canonical = $fontDir . '/Iranian-Sans.ttf';
    if (is_file($legacy) && !is_file($canonical)) {
        if (!rename($legacy, $canonical)) throw new RuntimeException('تغییر نام Iranian Sans.ttf انجام نشد.');
    }
    $rootLegacy = dirname(__DIR__) . '/Iranian Sans.ttf';
    if (is_file($rootLegacy) && !is_file($canonical)) {
        if (!rename($rootLegacy, $canonical)) throw new RuntimeException('انتقال Iranian Sans.ttf به assets/fonts انجام نشد.');
    }
    $rootCanonical = dirname(__DIR__) . '/Iranian-Sans.ttf';
    if (is_file($rootCanonical) && !is_file($canonical)) {
        if (!rename($rootCanonical, $canonical)) throw new RuntimeException('انتقال Iranian-Sans.ttf به assets/fonts انجام نشد.');
    }
    $pdo = DB::pdo();
    $st = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('app_version','0.4.3') ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $st->execute();
    echo "Soltan Hesab v0.4.3 font-path fix\n";
    echo "Vazirmatn local 400: ".(is_file($fontDir.'/vazirmatn-400.woff2')?'OK':'WEBFONT FALLBACK')."\n";
    echo "Vazirmatn local 700: ".(is_file($fontDir.'/vazirmatn-700.woff2')?'OK':'WEBFONT FALLBACK')."\n";
    echo "IRANSans local: ".(is_file($canonical)?'OK':'FALLBACK TO VAZIRMATN')."\n";
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: ".$e->getMessage()."\n";
}
