<?php

declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
Auth::requireDeveloper();
header('Content-Type: text/plain; charset=utf-8');
$pdo=Database::connection();
function t410(PDO $p,string $t): bool {$s=$p->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$t]);return (bool)$s->fetchColumn();}
function c410(PDO $p,string $t,string $c): bool {$s=$p->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$s->execute([$t,$c]);return (bool)$s->fetchColumn();}
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if(!t410($pdo,'customer_autogroup_exclusions')){$pdo->exec("CREATE TABLE customer_autogroup_exclusions (account_id BIGINT UNSIGNED PRIMARY KEY,excluded_by BIGINT UNSIGNED NULL,excluded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_customer_autogroup_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,CONSTRAINT fk_customer_autogroup_user FOREIGN KEY (excluded_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");echo "customer_autogroup_exclusions: CREATED\n";}else echo "customer_autogroup_exclusions: OK\n";
if(!c410($pdo,'daily_reports','source_storage_path')){$pdo->exec("ALTER TABLE daily_reports ADD COLUMN source_storage_path VARCHAR(500) NULL AFTER source_filename");echo "source_storage_path: ADDED\n";}else echo "source_storage_path: OK\n";
if(!c410($pdo,'daily_reports','panel_id')){throw new RuntimeException('ستون daily_reports.panel_id وجود ندارد؛ Migration فاز 3 ناقص است و باید قبل از ادامه بررسی شود.');}
// Reconcile ledger for schema pieces that older ad-hoc upgrades may already have created.
$mark=$pdo->prepare('INSERT IGNORE INTO migrations(version,applied_at) VALUES(?,NOW())');
if(t410($pdo,'customer_autogroup_exclusions'))$mark->execute(['006_phase4_7_polish']);
if(c410($pdo,'daily_reports','source_storage_path'))$mark->execute(['008_v045_report_source']);
// Repair report ownership when all stored rows agree on exactly one panel.
$pdo->exec("UPDATE daily_reports r JOIN (SELECT daily_report_id,MIN(panel_id) panel_id FROM daily_report_rows WHERE panel_id IS NOT NULL GROUP BY daily_report_id HAVING COUNT(DISTINCT panel_id)=1) x ON x.daily_report_id=r.id SET r.panel_id=x.panel_id WHERE r.panel_id IS NULL OR r.panel_id<>x.panel_id");
// Repair saved report totals/status from immutable rows, eliminating stale/stringy presentation snapshots.
$pdo->exec("UPDATE daily_reports r JOIN (SELECT daily_report_id,ROUND(SUM(received),6) received,ROUND(SUM(paid),6) paid,ROUND(SUM(commission),6) commission FROM daily_report_rows GROUP BY daily_report_id) x ON x.daily_report_id=r.id SET r.total_received=x.received,r.total_paid=x.paid,r.total_commission=x.commission,r.site_net=ROUND(x.received-x.paid,6),r.site_status=CASE WHEN x.received-x.paid>0 THEN 'win' WHEN x.received-x.paid<0 THEN 'loss' ELSE 'settled' END WHERE r.status='final'");
foreach(['theme_site_win'=>'theme_pay','theme_site_loss'=>'theme_recv'] as $key=>$fallback){$st=$pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key=?');$st->execute([$key]);if(!(int)$st->fetchColumn()){$v=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$v->execute([$fallback]);$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?)')->execute([$key,(string)($v->fetchColumn()?:($key==='theme_site_win'?'#92cdd9':'#ff5d60'))]);}}
$r=CustomerService::autoGroupUnlinked((int)(Auth::user()['id']??0));echo 'customer directory: OK (created '.(int)$r['created'].', linked '.(int)$r['linked'].")\n";
@mkdir(base_path('storage/report_sources'),0775,true);echo 'report_sources writable: '.(is_writable(base_path('storage/report_sources'))?'OK':'ERROR')."\n";
$pdo->prepare("INSERT INTO app_meta(meta_key,meta_value) VALUES('app_version','0.4.10'),('schema_version','011_v0410_phase47_stabilize') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)")->execute();$mark->execute(['011_v0410_phase47_stabilize']);
$reports=(int)$pdo->query("SELECT COUNT(*) FROM daily_reports WHERE status='final'")->fetchColumn();$accounts=(int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();$customers=(int)$pdo->query('SELECT COUNT(DISTINCT customer_id) FROM customer_accounts')->fetchColumn();$unlinked=(int)$pdo->query('SELECT COUNT(*) FROM accounts a LEFT JOIN customer_accounts ca ON ca.account_id=a.id WHERE ca.account_id IS NULL')->fetchColumn();
echo "final reports: $reports\naccounts: $accounts\ncustomer groups: $customers\nunlinked accounts: $unlinked\n";echo "v0.4.10 repair complete.\n";
