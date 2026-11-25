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

// Render the actual analytics PHP template rather than a second copy of its HTML.
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$root=dirname(__DIR__);$src=file_get_contents($root.'/index.php');
$start=strpos($src,'<section class="section-title"><div><h1>گزارش‌ها و آرشیو</h1>');
$end=strpos($src,"<?php elseif(\$page==='settings')",$start);
p4check($start!==false&&$end!==false,'actual analytics template located');
$template=substr($src,$start,$end-$start);
$rf=$d['filters'];$_GET=$filters;$reportData=$d;$reportError='';$qs=http_build_query($rf);$selectedCustomerName='';
$reportFilters=['companies'=>[['id'=>1,'name'=>'Fixture']],'panels'=>[['id'=>1,'company_id'=>1,'name'=>'Panel A','company_name'=>'Fixture'],['id'=>4,'company_id'=>1,'name'=>'Panel B','company_name'=>'Fixture']],'customers'=>[]];
$uiSettings=SettingsService::defaults();$uiSettings['digit_mode']='en';
ob_start();eval('?>'.$template);$html=ob_get_clean();
p4check(str_contains($html,'id="p4TransferTable"')&&str_contains($html,'TR-1')&&str_contains($html,'TR-2')&&str_contains($html,'180.125'),'actual period template renders exact final and documents');
p4check(!str_contains($html,'TR-3')&&!str_contains($html,'TR-4'),'render excludes outside and void documents');
preg_match_all('~<script\b[^>]*>(.*?)</script>~s',$html,$scripts);
foreach($scripts[1] as $i=>$js){if(trim($js)==='')continue;$file=tempnam(sys_get_temp_dir(),'p4js');file_put_contents($file,$js);exec('node --check '.escapeshellarg($file).' 2>&1',$lines,$status);unlink($file);p4check($status===0,'rendered inline JS '.$i);}
$chrome=trim((string)shell_exec('command -v google-chrome || command -v chromium || true'));
if($chrome!==''){
 $file=tempnam(sys_get_temp_dir(),'p4html').'.html';
 $head='<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="file://'.$root.'/"><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/transfer619.css"><script src="assets/svo-image617.js" defer></script><script src="assets/transfer-calendar620.js" defer></script><script>window.appAlert=msg=>{throw Error(msg)};</script></head><body>';
 $tail=<<<'HTML'
<script>
window.addEventListener('load',async()=>{
 try{
  const input=document.querySelector('[name="from"]'),b=document.querySelector('[data-transfer-date="'+input.id+'"]');
  if(!b||b.innerText.indexOf('2026-10-23')<0)throw Error('dual date');
  b.click();const dialog=document.getElementById('transferCalendar620');
  if(!dialog.open||dialog.querySelectorAll('.tc-day.tc-plain').length!==42)throw Error('report calendar');
  dialog.querySelector('[data-mode="gregorian"]').click();dialog.close();
  const rows=reportCollectRows(false,'p4TransferTable');
  if(rows.length!==4||rows[1][1].text!=='TR-1')throw Error('transfer table');
  const result=await makeReportImage();
  if(!result||result.size<1000)throw Error('PNG missing');
  document.body.setAttribute('data-p4-browser','PASS');
 }catch(err){document.body.setAttribute('data-p4-browser','FAIL '+String(err));}
});
</script></body></html>
HTML;
 file_put_contents($file,$head.$html.$tail);
 $cmd=escapeshellarg($chrome).' --headless --no-sandbox --disable-dev-shm-usage --allow-file-access-from-files --window-size=390,844 --virtual-time-budget=12000 --dump-dom '.escapeshellarg('file://'.$file).' 2>/dev/null';
 exec($cmd,$dom,$status);unlink($file);
 $result=implode("\n",$dom);
 if(!str_contains($result,'data-p4-browser="PASS"')){preg_match('/data-p4-browser="[^"]*"/',$result,$m);echo 'Browser marker: '.($m[0]??'missing').PHP_EOL;}
 p4check($status===0&&str_contains($result,'data-p4-browser="PASS"'),'Chromium mobile-sized actual template and PNG smoke');
}else{echo "[SKIP] Chromium not available".PHP_EOL;}
