<?php
declare(strict_types=1);
$root=dirname(__DIR__);
final class Database {public static PDO $pdo;public static function connection():PDO{return self::$pdo;}}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
require $root.'/app/Services/ReportingService.php';
function mc($v,string $label):void{if(!$v)throw new RuntimeException('FAIL '.$label);echo '[OK] '.$label.PHP_EOL;}
Database::$pdo=new PDO('sqlite::memory:');Database::$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
Database::$pdo->exec('CREATE TABLE settings(setting_key TEXT PRIMARY KEY,setting_value TEXT)');
mc(!MaintenanceP13::active(MaintenanceP13::read()),'default site open');
$now=time();$s=MaintenanceP13::start(1,'عنوان آزمایشی',"گزینه اول\nگزینه دوم",1,$now);
mc($s['until']===$now+60&&MaintenanceP13::active(MaintenanceP13::read(),$now),'one minute lock');
mc(MaintenanceP13::active($s,$now+59)&&!MaintenanceP13::active($s,$now+60),'exact deadline boundary');
mc(MaintenanceP13::pending(1)===null,'no announcement during lock');
Database::$pdo->exec("UPDATE settings SET setting_value='0' WHERE setting_key='maintenance_until_p13'");
mc(!MaintenanceP13::active(MaintenanceP13::read()),'emergency SQL opens site');
$a=MaintenanceP13::pending(1);
mc($a['items']===['گزینه اول','گزینه دوم'],'release notes after reopen');
MaintenanceP13::acknowledge(1,'invalid');
mc(MaintenanceP13::pending(1)!==null,'invalid acknowledgement ignored');
MaintenanceP13::acknowledge(1,$a['id']);
mc(MaintenanceP13::pending(1)===null&&MaintenanceP13::pending(2)!==null,'once per user');
$s=MaintenanceP13::start(120,'بعدی','امکان جدید',1,$now-7201);
mc(!MaintenanceP13::active($s)&&MaintenanceP13::pending(1)['id']!==$a['id'],'new release shown again');
foreach([0,-1,10081] as $m){$ok=false;try{MaintenanceP13::start($m,'x','x',1);}catch(RuntimeException){$ok=true;}mc($ok,'invalid duration '.$m);}
mc(str_contains(file_get_contents($root.'/app/bootstrap.php'),"'/Services/ReportingService.php'"),'shared bootstrap gate');
$src=file_get_contents($root.'/index.php');mc(str_contains($src,"if(\$action==='maintenance_start')")&&str_contains($src,'Auth::requireDeveloper();if($page'), 'developer-only start');
mc(str_contains($src,'csrf_verify();')&&str_contains($src,'maintenance_confirm'),'CSRF and explicit confirmation');


$tz=new DateTimeZone('Asia/Tehran');$target=(new DateTimeImmutable('@'.($now+7200)))->setTimezone($tz);
$deadline=MaintenanceP13::exactDeadline($target->format('Y-m-d'),$target->format('H:i'),$now);
mc($deadline>=($now+7140)&&$deadline<=($now+7200),'P14 exact Tehran time');
foreach([['2026-02-30','12:00'],[$target->format('Y-m-d'),'25:00']] as $bad){$ok=false;try{MaintenanceP13::exactDeadline($bad[0],$bad[1],$now);}catch(RuntimeException){$ok=true;}mc($ok,'P14 invalid deadline');}
$s=MaintenanceP13::start(1,'سه نمایش',"مورد اول\nمورد دوم",1,$now,$deadline,3);
mc($s['until']===$deadline,'P14 absolute server deadline');
$before=MaintenanceP13::read();
$html=MaintenanceP13::pageHtml($deadline,true);
mc(str_contains($html,'پیش‌نمایش')&&str_contains($html,'preview=true')&&str_contains($html,'به وقت تهران'),'P14 same public page preview');
mc(MaintenanceP13::read()===$before,'P14 preview does not mutate lock');
Database::$pdo->exec("UPDATE settings SET setting_value='0' WHERE setting_key='maintenance_until_p13'");
$a=MaintenanceP13::pending(1);
for($i=1;$i<=3;$i++){mc(MaintenanceP13::pending(1)!==null,'P14 pending notice '.$i);MaintenanceP13::acknowledge(1,$a['id']);}
mc(MaintenanceP13::pending(1)===null&&MaintenanceP13::pending(2)!==null,'P14 per-user display count');
$start=strpos($src,'<section class="dev14"');$end=strpos($src,'<?php else: ?><section class="empty-state">',$start);
mc($start!==false&&$end!==false,'P14 actual developer markup located');
function csrf_field():string{return '<input type="hidden" name="csrf" value="fixture">';}
$h=['db_ok'=>true,'migration'=>'017_operating_expenses','paths'=>['storage/logs'=>true],'accounting'=>['expense ledger'=>true],'extensions'=>['ZipArchive'=>true],'fonts'=>['Vazirmatn 400'=>false],'counts'=>['companies'=>18,'operating_expenses'=>1],'db_error'=>null];
ob_start();eval('?>'.substr($src,$start,$end-$start));$body=ob_get_clean();
$chrome=trim((string)shell_exec('command -v google-chrome || command -v chromium || true'));
if($chrome==='')throw new RuntimeException('Browser required for P14');
$css=file_get_contents($root.'/assets/transfer619.css');$app=file_get_contents($root.'/assets/app.css');
$tail=<<<'HTML'
<script>
window.addEventListener('load',()=>{
 try{
 const f=document.getElementById('dev14Form'),items=document.getElementById('dev14Items');
 if(items.children.length!==3)throw Error('initial rows');
 document.getElementById('dev14Suggested').click();
 if(items.children.length!==8)throw Error('suggested rows');
 document.getElementById('dev14Add').click();
 if(items.children.length!==9)throw Error('add row');
 items.lastElementChild.querySelector('button').click();
 if(items.children.length!==8)throw Error('remove row');
 const c=f.elements.maintenance_confirm,r=c.getBoundingClientRect();
 if(r.width<16||r.width>20||r.height>20)throw Error('checkbox size '+r.width);
 if(f.scrollWidth>f.clientWidth+2)throw Error('form overflow');
 f.elements.maintenance_mode[1].click();
 if(document.getElementById('dev14Exact').hidden)throw Error('exact mode hidden');
 document.getElementById('dev14PreviewPopup').click();
 if(!document.getElementById('dev14PopupDialog').open||document.getElementById('dev14PopupList').children.length!==8)throw Error('popup preview');
 document.querySelector('#dev14PopupDialog [data-close-preview]').click();
 if(document.getElementById('dev14PopupDialog').open)throw Error('close popup');
 document.body.dataset.dev14='PASS';
 }catch(e){document.body.dataset.dev14='FAIL '+String(e);}
});
</script>
HTML;
foreach(['390,844','1440,900'] as $view){
 $file=tempnam(sys_get_temp_dir(),'dev14').'.html';file_put_contents($file,'<!doctype html><html dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$app.$css.'</style></head><body>'.$body.$tail.'</body></html>');
 $out=[];exec(escapeshellarg($chrome).' --headless --no-sandbox --disable-dev-shm-usage --window-size='.$view.' --virtual-time-budget=4000 --dump-dom '.escapeshellarg('file://'.$file).' 2>/dev/null',$out,$status);$dom=implode("\n",$out);preg_match('/data-dev14="[^"]*"/',$dom,$marker);echo ($marker[0]??'no marker').PHP_EOL;unlink($file);mc($status===0&&str_contains($dom,'data-dev14="PASS"'),'P14 developer browser '.$view);
}


// P19 controls use the actual shared service with an isolated database.
$now=time();$before=MaintenanceP13::read();$args=['mode'=>'test','title'=>'Test','items'=>['One','Two'],'show_count'=>2];
$preview=MaintenanceP13::prepareMcp($args,$now);
mc($preview['until']===$now+60&&MaintenanceP13::read()===$before,'P19 preview no mutation');
$state=MaintenanceP13::start(1,$preview['title'],implode("\n",$preview['items']),1,$now,$preview['until'],2);
mc(MaintenanceP13::describe($state,$now)['remaining_seconds']===60,'P19 status');
$rejected=false;try{MaintenanceP13::start(1,'Duplicate','Item',1,$now);}catch(RuntimeException){$rejected=true;}mc($rejected,'P19 no overwrite of active maintenance');
$rejected=false;try{MaintenanceP13::stop(str_repeat('a',24),1);}catch(RuntimeException){$rejected=true;}mc($rejected&&MaintenanceP13::active(MaintenanceP13::read()),'P19 stale stop rejected');
mc(MaintenanceP13::stop($state['announcement']['id'],1)['stopped']&&!MaintenanceP13::active(MaintenanceP13::read()),'P19 confirmed stop');
mc(!MaintenanceP13::stop($state['announcement']['id'],1)['stopped'],'P19 stop is idempotent');
foreach([['show_count'=>0],['items'=>["line\nbreak"]],['mode'=>'exact'],['date'=>'2026-01-01']] as $bad){$rejected=false;try{MaintenanceP13::prepareMcp(array_replace($args,$bad),$now);}catch(RuntimeException){$rejected=true;}mc($rejected,'P19 reject invalid maintenance');}
$mcp=file_get_contents($root.'/mcp.php');mc(str_contains($mcp,"'admin:maintenance'")&&str_contains($mcp,"'admin:update'")&&str_contains($mcp,"Maintenance: operational tools are disabled"),'P19 scope and operational gate');
