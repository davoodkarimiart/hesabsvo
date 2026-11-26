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
 if(!document.getElementById('dev14PopupDialog').open||document.getElementById('dev14PopupList').children.length!==8)throw Error('popup preview');const dialog=document.getElementById('dev14PopupDialog'),dr=dialog.getBoundingClientRect();if(dr.width>innerWidth||dr.height>innerHeight||dialog.scrollWidth>dialog.clientWidth+2)throw Error('popup viewport');
 document.querySelector('#dev14PopupDialog [data-close-preview]').click();
 if(document.getElementById('dev14PopupDialog').open)throw Error('close popup');
 document.getElementById('p23Sample').click();if(document.getElementById('dev14PopupList').children.length!==15)throw Error('15 item sample');document.querySelector('#dev14PopupDialog [data-close-preview]').click();
 document.body.dataset.dev14='PASS';
 }catch(e){document.body.dataset.dev14='FAIL '+String(e);}
});
</script>
HTML;
foreach(['320,700','390,844','1440,900'] as $view){
 $file=tempnam(sys_get_temp_dir(),'dev14').'.html';file_put_contents($file,'<!doctype html><html dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$app.$css.'</style></head><body>'.$body.$tail.'</body></html>');
 $out=[];exec(escapeshellarg($chrome).' --headless --no-sandbox --disable-dev-shm-usage --window-size='.$view.' --virtual-time-budget=4000 --dump-dom '.escapeshellarg('file://'.$file).' 2>/dev/null',$out,$status);$dom=implode("\n",$out);preg_match('/data-dev14="[^"]*"/',$dom,$marker);echo ($marker[0]??'no marker').PHP_EOL;unlink($file);mc($status===0&&str_contains($dom,'data-dev14="PASS"'),'P14 developer browser '.$view);
}


// P19 controls use the actual shared service with an isolated database.
$now=time();$before=MaintenanceP13::read();$args=['mode'=>'test','title'=>'Test','items'=>['One','Two'],'show_count'=>2];
$preview=MaintenanceP13::prepareMcp($args,$now);
mc($preview['until']===$now+60&&MaintenanceP13::read()===$before,'P19 preview no mutation');
$state=MaintenanceP13::start(1,$preview['title'],implode("\n",$preview['items']),1,$now,$preview['until'],2);
mc(MaintenanceP13::describe($state,$now)['remaining_seconds']===60,'P19 status');
$owner=['id'=>1,'role'=>'developer'];$marker=['maintenance_owner_p23'=>['id'=>$state['announcement']['id'],'uid'=>1]];
mc(MaintenanceP13::ownerSessionMatches($state,$marker,$owner,$now),'P23 initiating developer session allowed');
mc(!MaintenanceP13::ownerSessionMatches($state,[],$owner,$now),'P23 same account separate browser blocked');
mc(!MaintenanceP13::ownerSessionMatches($state,$marker,['id'=>2,'role'=>'developer'],$now),'P23 other developer blocked');
mc(!MaintenanceP13::ownerSessionMatches($state,$marker,['id'=>1,'role'=>'admin'],$now),'P23 ordinary admin blocked');
mc(!MaintenanceP13::ownerSessionMatches($state,$marker,null,$now),'P23 invalid or revoked login blocked');
mc(!MaintenanceP13::ownerSessionMatches($state,['maintenance_owner_p23'=>['id'=>str_repeat('a',24),'uid'=>1]],$owner,$now),'P23 stale marker blocked');
mc(!MaintenanceP13::ownerSessionMatches($state,$marker,$owner,$now+60),'P23 marker expires with lock');
$rejected=false;try{MaintenanceP13::start(1,'Duplicate','Item',1,$now);}catch(RuntimeException){$rejected=true;}mc($rejected,'P19 no overwrite of active maintenance');
$rejected=false;try{MaintenanceP13::stop(str_repeat('a',24),1);}catch(RuntimeException){$rejected=true;}mc($rejected&&MaintenanceP13::active(MaintenanceP13::read()),'P19 stale stop rejected');
mc(MaintenanceP13::stop($state['announcement']['id'],1)['stopped']&&!MaintenanceP13::active(MaintenanceP13::read()),'P19 confirmed stop');
mc(!MaintenanceP13::stop($state['announcement']['id'],1)['stopped'],'P19 stop is idempotent');
$session=['_security_version'=>7];$sid='test-browser-a-p24';$other='test-browser-b-p24';
MaintenanceP13::rememberBrowser($owner,$session,$sid);
MaintenanceP13::rememberBrowser($owner,$session,$other);
MaintenanceP13::rememberBrowser(['id'=>2,'role'=>'developer'],$session,'different-user-browser');
MaintenanceP13::rememberBrowser(['id'=>1,'role'=>'admin'],$session,'ordinary-admin-browser');
$p24=MaintenanceP13::start(1,'P24','one',1,$now,null,3,[],7);
$binding=json_decode((string)Database::$pdo->query("SELECT setting_value FROM settings WHERE setting_key='maintenance_mcp_owner_p24'")->fetchColumn(),true);
mc(MaintenanceP13::mcpOwnerMatches($p24,$binding,$session,$owner,$other,$now),'P24 last browser of MCP account allowed');
mc(!MaintenanceP13::mcpOwnerMatches($p24,$binding,$session,$owner,$sid,$now),'P24 older same-account browser blocked');
mc(!MaintenanceP13::mcpOwnerMatches($p24,$binding,$session,['id'=>2,'role'=>'developer'],$other,$now),'P24 different developer blocked');
mc(!MaintenanceP13::mcpOwnerMatches($p24,$binding,$session,['id'=>1,'role'=>'admin'],$other,$now),'P24 admin blocked');
mc(!MaintenanceP13::mcpOwnerMatches($p24,$binding,$session,null,$other,$now),'P24 unauthenticated browser blocked');
mc(!MaintenanceP13::mcpOwnerMatches($p24,$binding,['_security_version'=>8],$owner,$other,$now),'P24 changed security version blocked');
mc(!MaintenanceP13::mcpOwnerMatches($p24,$binding,$session,$owner,$other,$now+60),'P24 expired lock blocked');
mc(!str_contains(json_encode(MaintenanceP13::describe($p24,$now)),(string)$binding['fingerprint']),'P24 binding absent from MCP response');
mc(MaintenanceP13::publicOAuthDiscovery('oauth.php','GET','resource')&&MaintenanceP13::publicOAuthDiscovery('oauth.php','GET','metadata'),'P24 read-only OAuth discovery available');
foreach([['oauth.php','POST','resource'],['oauth.php','GET','authorize'],['oauth.php','POST','register'],['oauth.php','POST','consent'],['index.php','GET','metadata']] as $bad)mc(!MaintenanceP13::publicOAuthDiscovery(...$bad),'P24 no discovery bypass '.$bad[2]);
MaintenanceP13::stop($p24['announcement']['id'],1);
$web=MaintenanceP13::start(1,'web','one',1,$now);
$cleared=json_decode((string)Database::$pdo->query("SELECT setting_value FROM settings WHERE setting_key='maintenance_mcp_owner_p24'")->fetchColumn(),true);
mc($cleared===[]&&!MaintenanceP13::mcpOwnerMatches($web,$binding,$session,$owner,$other,$now),'P24 web start clears MCP binding');
MaintenanceP13::stop($web['announcement']['id'],1);


foreach([['show_count'=>0],['items'=>["line\nbreak"]],['mode'=>'exact'],['date'=>'2026-01-01']] as $bad){$rejected=false;try{MaintenanceP13::prepareMcp(array_replace($args,$bad),$now);}catch(RuntimeException){$rejected=true;}mc($rejected,'P19 reject invalid maintenance');}
$mcp=file_get_contents($root.'/mcp.php');mc(str_contains($mcp,"'admin:maintenance'")&&str_contains($mcp,"'admin:update'")&&str_contains($mcp,"Maintenance: operational tools are disabled"),'P19 scope and operational gate');


$before=MaintenanceP13::read();
$a20=AnnouncementP20::publish('اطلاعیه','مورد اول'."\n".'مورد دوم','suggestion',2,7,0,1);
mc(MaintenanceP13::read()===$before,'P20 standalone notice does not lock site');
mc(AnnouncementP20::pending(1)['id']===$a20['id'],'P20 notice pending');
AnnouncementP20::acknowledge(1,$a20['id']);mc(AnnouncementP20::pending(1)!==null,'P20 first acknowledgement');
AnnouncementP20::acknowledge(1,$a20['id']);mc(AnnouncementP20::pending(1)===null&&AnnouncementP20::pending(2)!==null,'P20 independent per-user counts');
AnnouncementP20::cancel($a20['id'],1);mc(AnnouncementP20::pending(2)===null,'P20 cancellation');

$meta=PopupP21::meta(['subtitle'=>'به‌روزرسانی','version'=>'P21','hours'=>'۹ ساعت','patches'=>'۱۷ پچ','range'=>'P5 تا P21','delay_seconds'=>15]);
mc($meta['delay_seconds']===15&&PopupP21::items(['عنوان | توضیح','ساده'])===[['عنوان','توضیح'],['ساده','']],'P21 structured announcement');
$a=AnnouncementP20::publish('P21','عنوان | توضیح','update',1,3,0,1,$meta);
mc($a['presentation']===$meta&&AnnouncementP20::pending(1)['presentation']===$meta,'P21 structured data persists');
mc(str_contains(MaintenanceP13::pageHtml(time()+60,true),'Vazirmatn,IRANSansLocal')&&str_contains(MaintenanceP13::pageHtml(time()+60,true),'color-scheme:light'),'P21 maintenance typography and light theme');
$bad=false;try{PopupP21::meta(['delay_seconds'=>5]);}catch(RuntimeException){$bad=true;}mc($bad,'P21 reject early dismiss duration');

$structured=PopupP21::mcpItems([['title'=>'عنوان','description'=>'توضیح'],'قدیمی']);mc($structured===['عنوان | توضیح','قدیمی'],'P22 MCP structured and legacy items');
$preview=MaintenanceP13::prepareMcp(['mode'=>'test','title'=>'P22','items'=>array_fill(0,24,['title'=>'عنوان','description'=>'توضیح']),'show_count'=>1]);mc(count($preview['item_details'])===24&&$preview['item_details'][0]['description']==='توضیح','P22 long structured preview');
$maintenanceHtml=MaintenanceP13::pageHtml(time()+60,true);
mc(str_contains($maintenanceHtml,'<span>به‌روزرسانی</span>')&&!str_contains($maintenanceHtml,'id="retry"')&&str_contains($maintenanceHtml,'زمان تقریبی بازگشایی:'),'P21 reference maintenance content');
$popupStart=strpos($src,'<dialog id="maintenanceAnnouncement"');
$popupEnd=strpos($src,'</dialog><script data-p20-page>',$popupStart);
$popupEnd=strpos($src,'</script>',$popupEnd)+9;
mc($popupStart!==false&&$popupEnd>9,'P21 actual popup fixture');
$announcement=['id'=>str_repeat('b',24),'title'=>'سلطان حساب به‌روز شد','items'=>array_fill(0,24,'عنوان | توضیح برای آزمون اسکرول داخلی')];$p20Kind='update';$p20Label='به‌روزرسانی بزرگ سلطان حساب';$p20Action='maintenance_ack';$p21=PopupP21::meta(['version'=>'0.6.53-P21','hours'=>'۹ ساعت','patches'=>'۱۷ پچ','range'=>'P5 تا P21','delay_seconds'=>10]);
ob_start();eval('?>'.substr($src,$popupStart,$popupEnd-$popupStart));$popupHtml=ob_get_clean();
$probe=<<<'HTML'
<script>
const button=document.getElementById('p20Acknowledge'),dialog=document.getElementById('maintenanceAnnouncement');
const rect=dialog.getBoundingClientRect(),scroll=dialog.querySelector('.p20-popup-scroll'),timerLabel=dialog.querySelector('.p22-timer');
const head=dialog.querySelector('.p21-popup-head'),footer=dialog.querySelector('.p20-popup-footer'),badgeStyle=getComputedStyle(button.querySelector('.p21-count')),closer=dialog.querySelector('.p23-close');scroll.focus();const focused=getComputedStyle(scroll);const details=[button.disabled,dialog.open,rect.width,innerWidth,rect.height,innerHeight,dialog.scrollWidth,dialog.clientWidth,head.offsetHeight,scroll.clientHeight,focused.boxShadow,focused.borderTopWidth,badgeStyle.backgroundColor];const initial=button.disabled&&dialog.open&&rect.width<=innerWidth&&rect.height<=innerHeight&&dialog.scrollWidth<=dialog.clientWidth+2&&scroll.scrollHeight>scroll.clientHeight&&focused.outlineStyle==='none'&&focused.boxShadow==='none'&&focused.borderTopWidth==='0px'&&badgeStyle.backgroundColor==='rgb(255, 255, 255)'&&scroll.clientHeight>head.offsetHeight&&button.getBoundingClientRect().width<rect.width*.8&&footer.getBoundingClientRect().bottom<=rect.bottom+2&&closer.disabled&&timerLabel.textContent.includes('10');
setTimeout(()=>{document.body.dataset.p21=initial&&!button.disabled&&!closer.disabled&&button.querySelector('.p21-count').hidden&&timerLabel.textContent.includes('فعال')?'PASS':'FAIL '+details.join(',')+' end:'+button.disabled+','+button.querySelector('.p21-count').hidden},11000);
</script>
HTML;
foreach(['390,844','1440,900'] as $view){
 foreach(['popup'=>'<!doctype html><html dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$app.$css.'</style></head><body>'.$popupHtml.$probe.'</body></html>','maintenance'=>str_replace('</body>','<script>const r=document.querySelector("main").getBoundingClientRect();document.body.dataset.p21=Math.abs(r.width-innerWidth)<=2&&Math.abs(r.left)<=2&&Math.abs(r.top)<=2&&r.height>=innerHeight-2&&document.documentElement.scrollWidth<=innerWidth+2&&Number(document.getElementById("ss").textContent)>=0&&!!document.querySelector(".rail-bottom")&&parseFloat(getComputedStyle(document.querySelector("h1")).fontSize)>=24?"PASS":"FAIL";</script></body>',$maintenanceHtml)] as $kind=>$fixture){
  $tmp=tempnam(sys_get_temp_dir(),'p21');$file=$tmp.'.html';rename($tmp,$file);file_put_contents($file,$fixture);$out=[];exec(escapeshellarg($chrome).' --headless --no-sandbox --disable-dev-shm-usage --window-size='.$view.' --virtual-time-budget=12500 --dump-dom '.escapeshellarg('file://'.$file).' 2>/dev/null',$out,$status);unlink($file);$dom=implode("\n",$out);preg_match('/data-p21="[^"]*"/',$dom,$marker);echo ($marker[0]??'no marker').PHP_EOL;mc($status===0&&str_contains($dom,'data-p21="PASS"'),'P21 '.$kind.' browser '.$view);
 }
}
