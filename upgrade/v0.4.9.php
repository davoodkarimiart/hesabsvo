<?php

declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
Auth::requireDeveloper();
$pdo=Database::connection();
function v049_col(PDO $pdo,string $table,string $col): bool {$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$st->execute([$table,$col]);return (bool)$st->fetchColumn();}
if(!v049_col($pdo,'daily_reports','source_storage_path')){$pdo->exec("ALTER TABLE daily_reports ADD COLUMN source_storage_path VARCHAR(500) NULL AFTER source_filename");echo "source_storage_path: ADDED\n";}else echo "source_storage_path: OK\n";
try{$r=CustomerService::autoGroupUnlinked((int)(Auth::user()['id']??0));echo 'customer directory: OK (created '.(int)$r['created'].', linked '.(int)$r['linked'].")\n";}catch(Throwable $e){echo 'customer directory: ERROR '.$e->getMessage()."\n";throw $e;}
@mkdir(base_path('storage/report_sources'),0775,true);echo 'report_sources writable: '.(is_writable(base_path('storage/report_sources'))?'OK':'ERROR')."\n";
echo "v0.4.9 upgrade complete.\n";
