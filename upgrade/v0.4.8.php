<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
Auth::requireDeveloper();
$pdo=Database::connection();
$pay=(string)($pdo->query("SELECT setting_value FROM settings WHERE setting_key='theme_pay' LIMIT 1")->fetchColumn() ?: '#92cdd9');
$recv=(string)($pdo->query("SELECT setting_value FROM settings WHERE setting_key='theme_recv' LIMIT 1")->fetchColumn() ?: '#ff5d60');
$st=$pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=setting_value");
$st->execute(['theme_site_win',$pay]);
$st->execute(['theme_site_loss',$recv]);
$pdo->exec("INSERT INTO app_meta(meta_key,meta_value) VALUES('app_version','0.4.8'),('schema_version','010_v048_customer_reporting_ui') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
try { CustomerService::autoGroupUnlinked((int)(Auth::user()['id']??0)); } catch(Throwable $e) { Logger::error('v048.autogroup_failed',['error'=>$e->getMessage()]); }
echo "v0.4.8 upgrade complete\n";
echo "theme_site_win: {$pay}\n";
echo "theme_site_loss: {$recv}\n";
