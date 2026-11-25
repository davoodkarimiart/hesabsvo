<?php
/** Isolated SQLite fixture: no real customer names or financial data. */
require_once dirname(__DIR__).'/app/Services/AccountingService.php';
require_once dirname(__DIR__).'/app/Services/TransferService.php';
require_once dirname(__DIR__).'/app/Services/ReportingService.php';
require_once dirname(__DIR__).'/app/Services/SettingsService.php';
require_once dirname(__DIR__).'/app/Support/ReportHeading626.php';
function p4check($condition,string $label):void{if(!$condition)throw new RuntimeException('FAIL '.$label);echo "[OK] ".$label.PHP_EOL;}
p4check(TransferService::decimalAdd('999999999999999999.999999','0.000001')==='1000000000000000000.000000','six-place carry without float overflow');
p4check(TransferService::decimalSubtract('10.000001','11.000002')==='-1.000001','signed subtraction');
p4check(TransferService::decimalAdd('-10.000001','10.000001')==='0.000000','exact zero');
p4check(TransferService::formatToman('1234567890.123456',['digit_mode'=>'en'])==='1,234,567,890.123456','exact transfer display');
p4check(TransferService::directionLabel('from_master',true)==='پرداختی به سایت'&&TransferService::directionLabel('from_master')==='دریافت از مشتری','stored direction reverses only in customer report');
p4check(TransferService::directionLabel('to_master',true)==='دریافتی از سایت'&&TransferService::directionClass('to_master',true)==='positive','customer receives green');
class Database {public static PDO $pdo;public static function connection():PDO{return self::$pdo;}}
$db=Database::$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE companies(id INTEGER,name TEXT);CREATE TABLE panels(id INTEGER,company_id INTEGER,name TEXT,active INTEGER);CREATE TABLE daily_reports(id INTEGER,company_id INTEGER,panel_id INTEGER,report_date TEXT,status TEXT,deleted_at TEXT,total_received DECIMAL,total_paid DECIMAL,total_commission DECIMAL);CREATE TABLE daily_report_rows(id INTEGER,daily_report_id INTEGER,panel_id INTEGER,received DECIMAL,paid DECIMAL,commission DECIMAL);CREATE TABLE transfers(id INTEGER,company_id INTEGER,company_name_snapshot TEXT,transfer_code TEXT,notes TEXT,status TEXT,direction TEXT,period_start TEXT,period_end TEXT,created_at TEXT,total_toman DECIMAL);");
$db->exec("INSERT INTO companies VALUES(1,'Fixture'),(2,'Other');INSERT INTO panels VALUES(1,1,'Panel A',1),(4,1,'Panel B',1);INSERT INTO daily_reports VALUES
 (1,1,1,'2026-10-23','final',NULL,1000,400,100),
 (2,1,NULL,'2026-10-24','final',NULL,300,800,30),
 (3,1,1,'2026-10-25','deleted','2026-10-26',900000,0,0),
 (4,2,1,'2026-10-25','final',NULL,900000,0,0),
 (5,1,4,'2026-10-26','final',NULL,200,100,20);
 INSERT INTO daily_report_rows VALUES(1,1,1,1000,400,100),(2,2,1,200,400,20),(3,2,4,100,400,10),(4,5,4,200,100,20);
 INSERT INTO transfers VALUES
 (1,1,'Fixture','TR-1','','final','from_master','2026-10-19','2026-10-25','2026-11-03',60.125),
 (2,1,'Fixture','TR-2','','final','to_master','2026-11-09','2026-11-15','2026-11-23',40.25),
 (3,1,'Fixture','TR-3','','final','from_master','2026-11-16','2026-11-22','2026-11-21',999999),
 (4,1,'Fixture','TR-4','','void','from_master','2026-10-26','2026-11-01','2026-11-03',999999),
 (5,2,'Other','TR-5','','final','from_master','2026-10-19','2026-10-25','2026-11-03',999999),
 (6,1,'Fixture','TR-6','','final','none','2026-11-02','2026-11-08','2026-11-03',0);");
$filters=['type'=>'period_transfer','company_id'=>1,'panel_id'=>4,'date_mode'=>'range','from'=>'1405/08/01','to'=>'1405/08/30'];
$d=ReportingService::report($filters);
p4check($d['filters']['panel_id']===0,'period includes all panels');
p4check(count($d['rows'])===4&&count($d['panel_totals'])===2,'multi-panel report split without duplicating header');
p4check($d['totals']['received']===1500.0&&$d['totals']['paid']===1300.0&&$d['totals']['commission']===150.0&&$d['totals']['net']===200.0,'canonical total excludes deleted/foreign reports');
p4check(array_column($d['transfers'],'id')===[1,6,2]||array_map('intval',array_column($d['transfers'],'id'))===[1,6,2],'settlement end date controls inclusion, late registration retained, void/next month excluded');
p4check($d['transfer_totals']['received']==='60.125000'&&$d['transfer_totals']['paid']==='40.250000','both transfer directions summed separately');
p4check($d['transfer_totals']['adjusted_net']==='180.125000','agreed customer-view formula');
$table=ReportingService::transferTable($d);
p4check($table['rows'][0]['values'][0]==='1405/08/03'&&$table['rows'][0]['values'][1]==='TR-1'&&$table['rows'][0]['values'][3]==='60.125000','end-date and full code and exact amount exported');
$h=TransferService::listingPaginated('',1,'','2026-10-23','2026-11-21',1,5,'from_master');
p4check($h['total']===2&&(float)$h['totals']['received']===60.125,'history filters stored direction and excludes void from sums');
$history=ReportingService::report(['type'=>'transfer_history','company_id'=>1,'date_mode'=>'range','from'=>'2026-10-23','to'=>'2026-11-21','status'=>'void']);
p4check(count($history['rows'])===1&&$history['transfer_totals']['received']==='0.000000','void history remains visible but nonfinancial');
p4check(ReportingService::netLabel(-1.0)==='باخت سایت'&&ReportingService::netLabel(0.0)==='تسویه صفر','negative and zero result labels');
$db->exec("UPDATE daily_report_rows SET received=999 WHERE id=2");
$blocked=false;try{ReportingService::report($filters);}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'اختلاف جمع');}
p4check($blocked,'inconsistent multi-panel data fails closed');
echo "[OK] P4 period/transfer fixtures complete.".PHP_EOL;
