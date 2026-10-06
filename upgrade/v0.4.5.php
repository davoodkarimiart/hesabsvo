<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireRole('developer');
header('Content-Type: text/plain; charset=utf-8');
Migrator::run();
$dir=base_path('storage/report_sources');if(!is_dir($dir))@mkdir($dir,0775,true);
echo "v0.4.5 upgrade complete\n";
$pdo=Database::connection();
$st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='daily_reports' AND column_name='source_storage_path'");$st->execute();echo 'source_storage_path: '.((int)$st->fetchColumn()>0?'OK':'MISSING')."\n";
echo 'report_sources writable: '.(is_dir($dir)&&is_writable($dir)?'OK':'CHECK PERMISSION')."\n";
