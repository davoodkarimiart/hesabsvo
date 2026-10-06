<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireRole('developer');
header('Content-Type: text/plain; charset=utf-8');
$pdo=Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
function col_exists(PDO $pdo,string $table,string $col): bool {$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$st->execute([$table,$col]);return (bool)$st->fetchColumn();}
function table_exists_v046(PDO $pdo,string $table): bool {$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$st->execute([$table]);return (bool)$st->fetchColumn();}
if(!col_exists($pdo,'daily_reports','source_storage_path')){$pdo->exec("ALTER TABLE daily_reports ADD COLUMN source_storage_path VARCHAR(500) NULL AFTER source_filename");echo "source_storage_path: ADDED\n";}else echo "source_storage_path: OK\n";
$pdo->prepare('INSERT IGNORE INTO migrations(version,applied_at) VALUES(?,NOW())')->execute(['008_v045_report_source']);
if(!table_exists_v046($pdo,'customer_autogroup_exclusions')){
 $pdo->exec("CREATE TABLE customer_autogroup_exclusions (account_id BIGINT UNSIGNED PRIMARY KEY,excluded_by BIGINT UNSIGNED NULL,excluded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_customer_autogroup_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,CONSTRAINT fk_customer_autogroup_user FOREIGN KEY (excluded_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 echo "customer_autogroup_exclusions: CREATED\n";
}else echo "customer_autogroup_exclusions: OK\n";
try{Migrator::runPending();}catch(Throwable $e){echo "pending migration warning: ".$e->getMessage()."\n";}
$dir=base_path('storage/report_sources');if(!is_dir($dir))@mkdir($dir,0775,true);
$pdo->exec("INSERT INTO app_meta(meta_key,meta_value) VALUES('app_version','0.4.6'),('schema_version','009_v046_stability') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
$pdo->prepare('INSERT IGNORE INTO migrations(version,applied_at) VALUES(?,NOW())')->execute(['009_v046_stability']);
echo 'report_sources writable: '.(is_dir($dir)&&is_writable($dir)?'OK':'CHECK PERMISSION')."\n";
echo "v0.4.6 upgrade complete\n";
