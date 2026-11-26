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
p4check(TransferService::formatToman('1234567890.123456',['digit_mode'=>'en'])==='1,234,567,890','whole toman display');
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
p4check(str_contains($html,'id="p4TransferTable"')&&str_contains($html,'TR-1')&&str_contains($html,'TR-2')&&str_contains($html,'>180</b>')&&str_contains($html,'نتیجه نهایی: برد سایت'),'actual period template renders rounded final and documents');
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
  const summary=document.querySelector('#p4FinalTable');if(summary.rows.length!==2||summary.rows[0].cells.length!==3||summary.rows[1].cells.length!==3)throw Error('three-column summary');
  const form=document.getElementById('reportFilterForm'),type=document.getElementById('reportTypeSelect');
  for(const value of ['cumulative','daily','monthly','detail','customer_detail','period_transfer','transfer_history']){
    type.value=value;type.dispatchEvent(new Event('change',{bubbles:true}));
    const grid=form.querySelector('.p6-primary-grid'),dateGrid=form.querySelector('.p6-date-grid');
    if(!grid||!dateGrid||getComputedStyle(grid).display!=='grid'||getComputedStyle(dateGrid).display!=='grid')throw Error('grid '+value);
    if(form.scrollWidth>form.clientWidth+2)throw Error('horizontal overflow '+value);
  }
  type.value='period_transfer';type.dispatchEvent(new Event('change',{bubbles:true}));
  const clear=input.parentElement.querySelector('.tc-clear-date');if(!clear||clear.hidden)throw Error('selected date clear missing');const oldValue=input.value;clear.click();if(!clear.hidden||input.value)throw Error('empty date clear');input.value=oldValue;input.dispatchEvent(new Event('change',{bubbles:true}));
  const result=await makeReportImage();
  if(!result||result.size<1000)throw Error('PNG missing');
  document.body.setAttribute('data-p4-browser','PASS');
 }catch(err){document.body.setAttribute('data-p4-browser','FAIL '+String(err));}
});
</script></body></html>
HTML;
 file_put_contents($file,$head.$html.$tail);
 $cmd=escapeshellarg($chrome).' --headless --no-sandbox --disable-dev-shm-usage --allow-file-access-from-files --window-size=390,844 --virtual-time-budget=12000 --dump-dom '.escapeshellarg('file://'.$file).' 2>/dev/null';
 exec($cmd,$dom,$status);
 $result=implode("\n",$dom);
 if(!str_contains($result,'data-p4-browser="PASS"')){preg_match('/data-p4-browser="[^"]*"/',$result,$m);echo 'Browser marker: '.($m[0]??'missing').PHP_EOL;}
 p4check($status===0&&str_contains($result,'data-p4-browser="PASS"'),'Chromium mobile-sized actual template and PNG smoke');
 $desktop=[];exec(str_replace('--window-size=390,844','--window-size=1440,900',$cmd),$desktop,$status);unlink($file);$desktop=implode("\n",$desktop);if(!str_contains($desktop,'data-p4-browser="PASS"')){preg_match('/data-p4-browser="[^"]*"/',$desktop,$m);echo 'Desktop marker: '.($m[0]??'missing').PHP_EOL;}p4check($status===0&&str_contains($desktop,'data-p4-browser="PASS"'),'p6 desktop all seven report types and PNG');
}else{echo "[SKIP] Chromium not available".PHP_EOL;}

$manifest=json_decode(file_get_contents($root.'/updates/channel.json'),true,512,JSON_THROW_ON_ERROR);
if(in_array($manifest['release'],['0.6.53-p4','0.6.53-p5','0.6.53-p6','0.6.53-p7','0.6.53-p8'],true)){
 p4check(count($manifest['files'])===(in_array($manifest['release'],['0.6.53-p7','0.6.53-p8'],true)?11:10),'release manifest has ten deployment files');
 foreach($manifest['files'] as $f)p4check(hash_file('sha256',$root.'/'.$f['path'])===$f['sha256'],'release SHA256 '.$f['path']);
}

p4check(TransferService::wholeToman('0.499999')==='0'&&TransferService::wholeToman('0.500000')==='1'&&TransferService::wholeToman('-0.500000')==='-1'&&TransferService::wholeToman('999.999999')==='1000','p5 exact whole-toman half-up rounding');
p4check(ReportingService::normalizeFilters(['type'=>'period_transfer','date_mode'=>'single','from'=>'1405/08/01','to'=>'1405/08/30'])['date_mode']==='range','p5 period report forces range');
$export=file_get_contents($root.'/export.php');
$start=strpos($export,"if(isset(\$data)&&in_array(\$data['type'],['period_transfer','transfer_history'],true)){");
$end=strpos($export,'$fname=',$start);
p4check($start!==false&&$end!==false,'p5 actual XLSX section located');
$xlsxCode=substr($export,$start,$end-$start);
foreach([$d,$history] as $data){
 $settings=SettingsService::defaults();$factor=SettingsService::scaleFactor($settings);$reportPeriod='1405/08/01 تا 1405/08/30';
 eval($xlsxCode);
 $n=count($headers);foreach($items as $item)p4check(count($item['values'])===$n,'p5 XLSX row width '.$data['type']);
 $last=end($items);$lastIndex=$data['type']==='period_transfer'?4:3;p4check(is_int($last['values'][$lastIndex]),'p7 numeric final amount '.$data['type']);
 if($data['type']==='period_transfer')p4check($last['values'][$lastIndex]===180,'p7 rounded adjusted net in workbook');
 if(class_exists('ZipArchive')){
  $script=tempnam(sys_get_temp_dir(),'p5xlsx');$file=tempnam(sys_get_temp_dir(),'p5zip');
  $args=['Fixture',$reportPeriod,$headers,$widths,$types,$items,'test.xlsx','گزارش',false,$xlsxOptions];
  file_put_contents($script,'<?php require '.var_export($root.'/app/Support/ReportXlsx68.php',true).'; ReportXlsx68::download(...'.var_export($args,true).');');
  exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' > '.escapeshellarg($file),$out,$status);
  $zip=new ZipArchive();$opened=$zip->open($file);
  p4check($status===0&&$opened===true,'p5 generated workbook opens '.$data['type']);
  $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$strings=$zip->getFromName('xl/sharedStrings.xml');
  p4check(is_string($sheet)&&is_string($strings)&&str_contains($sheet,'<v>'.($data['type']==='period_transfer'?'180':'0').'</v>'),'p5 actual workbook contains integer values '.$data['type']);
  p4check(str_contains($sheet,'rightToLeft="0"')&&strpos($sheet,'<autoFilter')<strpos($sheet,'<mergeCells'),'p8 LTR sheet and Excel 2010 worksheet ordering');
  $sx=simplexml_load_string($sheet);$ss=simplexml_load_string($strings);$ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$sx->registerXPathNamespace('m',$ns);$ss->registerXPathNamespace('m',$ns);$str=$ss->xpath('//m:si/m:t');$cellText=static function($ref)use($sx,$str){$node=$sx->xpath('//m:c[@r="'.$ref.'"]/m:v');return $node?(string)$str[(int)$node[0]]:'';};$endCol=chr(64+$n);p4check($cellText($endCol.'3')===$headers[0]&&$cellText('A3')===$headers[$n-1],'p8 physical header order');p4check($cellText(chr(63+$n).'2')===$xlsxOptions['dates']['from']&&$cellText(chr(61+$n).'2')===$xlsxOptions['dates']['to'],'p8 separate literal date cells');
  p4check(str_contains($sheet,'<mergeCells count="')&&str_contains($strings,'جمع‌بندی'),'p7 horizontal merged summary');
  foreach(['xl/worksheets/sheet1.xml','xl/styles.xml','xl/sharedStrings.xml','xl/workbook.xml','[Content_Types].xml'] as $part)p4check(simplexml_load_string($zip->getFromName($part))!==false,'p7 valid XML '.$part);
  p4check(!str_contains($strings,'180.125')&&!str_contains($strings,'60.125000'),'p5 no fractional toman strings');
  $zip->close();unlink($script);unlink($file);
 }else{echo "[SKIP] ZipArchive not available".PHP_EOL;}
}
