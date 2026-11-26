<?php
declare(strict_types=1);
$root=dirname(__DIR__);function check15($ok,$label){if(!$ok)throw new RuntimeException('FAIL '.$label);echo '[OK] '.$label.PHP_EOL;}
function e($x){return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf_field(){return '<input type="hidden" name="csrf" value="fixture">';}
function diagnosticLink(array $changes){return '?'.http_build_query($changes);}
const SOLTAN_MCP_RESOURCE='https://example.invalid/mcp.php';const UPDATE_CLIENT='1.4';
$css=file_get_contents($root.'/assets/transfer619.css');$app=file_get_contents($root.'/assets/app.css');
$chrome=trim((string)shell_exec('command -v google-chrome || command -v chromium || true'));check15($chrome!=='','browser present');
$channel='error';$allowed=['error'=>'خطاها','system'=>'سیستم','audit'=>'فعالیت'];$search=$from=$to=$level='';$scanned=1;$events=[['message'=>'reporting.view_failed','request_id'=>'5135af8be2ba8aff','ts'=>'2026-11-26T05:13:18+03:30','context'=>['type'=>'period_transfer']]];
$isDev=true;$msg=$error='';$hasToken=false;$suffix='';$s=['chat'=>''];$files=[['name'=>'soltan-db-20261126-012310-99b67a9b.sql.gz','size'=>241172]];$cron='cd /home/test/public_html && /usr/local/bin/php cron_soltan_backup.php';
$d=['clients'=>['fixture'=>['name'=>'ChatGPT','created_at'=>time()]]];$csrf='fixture';$esc=static fn($x)=>e($x);$expect=['read:companies'=>'list_companies, list_archived_companies','admin:diagnostics'=>'diagnostics_health, diagnostics_logs'];$group=[['name'=>'ChatGPT','user'=>'fixture','role'=>'developer','scopes'=>['read:companies'=>true,'admin:diagnostics'=>true],'expiration'=>time()+300]];
$current='0.6.53-p14';$latest='0.6.53-p15';$m=['_source'=>'GitHub API','_commit'=>str_repeat('a',40),'notes'=>'بهبود ظاهر؛ بهبود گزارش','files'=>[1]];$err=$migrationError='';$available=true;$migrationEntry=null;$migrationDone=true;
$tail=<<<'HTML'
<script>window.addEventListener('load',()=>{try{const w=document.documentElement.clientWidth;if(document.documentElement.scrollWidth>w+2)throw Error('page horizontal overflow '+document.documentElement.scrollWidth+' '+w);const nav=document.querySelector('.dev15-nav');if(!nav||nav.children.length!==5)throw Error('navigation');const dock=document.querySelector('.p17-dev-dock');if(!dock||dock.querySelectorAll('a').length!==6)throw Error('developer dock');const dr=dock.getBoundingClientRect();if(dr.left<0||dr.right>w+1||dr.width>681||dr.bottom>innerHeight+1)throw Error('developer dock bounds');for(const t of document.querySelectorAll('table')){if(!t.classList.contains('dev15-responsive-table'))throw Error('unadapted table');if(innerWidth<=740&&getComputedStyle(t.querySelector('tbody tr')).display!=='grid')throw Error('mobile table not stacked');for(const c of t.querySelectorAll('td[data-label]'))if(c.getBoundingClientRect().right>w+2)throw Error('cell overflow');}if(document.querySelector('.dev15-logs')){if(!document.querySelector('.dev15-event time, .dev15-event-meta time'))throw Error('missing log timestamp');const f=document.querySelector('.p16-log-filter'),r=f.getBoundingClientRect(),fields=[...f.querySelectorAll('input:not([type=hidden])')];for(const el of fields){const b=el.getBoundingClientRect();if(b.left<r.left||b.right>r.right+1)throw Error('filter bounds '+el.name)}for(let i=0;i<fields.length;i++)for(let j=i+1;j<fields.length;j++){const a=fields[i].getBoundingClientRect(),b=fields[j].getBoundingClientRect();if(Math.min(a.right,b.right)-Math.max(a.left,b.left)>1&&Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)>1)throw Error('filter overlap');}}document.body.dataset.p15='PASS'}catch(e){document.body.dataset.p15='FAIL '+String(e)}});</script>
HTML;
foreach(['developer_diagnostics.php','developer_backups.php','mcp_connections.php','mcp_permissions.php','update_center.php'] as $path){
 $src=file_get_contents($root.'/'.$path);$start=strpos($src,'<!doctype html>');check15($start!==false,'template '.$path);ob_start();eval('?>'.substr($src,$start));$html=ob_get_clean();$html=preg_replace('~<link rel="stylesheet" href="assets/transfer619.css[^"]*">~','<style>'.$css.'</style>',$html,1);$html=str_replace('</body>',$tail.'</body>',$html);
 foreach(['320,800','390,844','1440,900'] as $view){$tmpBase=tempnam(sys_get_temp_dir(),'p15');$tmp=$tmpBase.'.html';rename($tmpBase,$tmp);file_put_contents($tmp,$html);$out=[];exec(escapeshellarg($chrome).' --headless --no-sandbox --disable-dev-shm-usage --window-size='.$view.' --virtual-time-budget=3000 --dump-dom '.escapeshellarg('file://'.$tmp).' 2>/dev/null',$out,$status);unlink($tmp);$dom=implode("\n",$out);preg_match('/data-p15="[^"]*"/',$dom,$marker);echo ($marker[0]??('no marker '.substr($dom,0,240))).PHP_EOL;check15($status===0&&str_contains($dom,'data-p15="PASS"'),$path.' '.$view);}
}
$src=file_get_contents($root.'/index.php');check15(str_contains($src,'id="dev15FontPreview"')&&str_contains($src,'id="dev15Opacity"')&&str_contains($src,'data-theme-preview="theme_site_loss"'),'live settings preview anchors');check15(str_contains($src,'سهم مبلغ برد')&&str_contains($src,'پیش از هزینه و ترانسفر'),'dashboard accounting labels');check15(str_contains($src,'class="page-<?=e($page)?>"'),'scoped page styling');check15(!str_contains($src,'id="p16LoginIntro"')&&!str_contains($src,'id="p16GameStatus"')&&str_contains($src,'p16-dock'),'P16 login and navigation anchors');$mcp=file_get_contents($root.'/mcp.php');check15(str_contains($mcp,"['installed_release']")&&str_contains($mcp,"['expense_tables_ready']"),'MCP health metadata');


// Render the exact login and dock templates, using isolated non-production fixtures.
class LoginGuard630{public static function enabled(){return false;}}
$flash=null;$error='';$page='dashboard';
$loginStart=strpos($src,'<div id="loginScreen"');$loginEnd=strpos($src,"<?php elseif(\$page==='recover'): ?>",$loginStart);
check15($loginStart!==false&&$loginEnd!==false,'P16 login template');
ob_start();eval('?>'.substr($src,$loginStart,$loginEnd-$loginStart));$login=ob_get_clean();
$dockStart=strpos($src,'<nav class="bottomnav p16-dock"');$dockEnd=strpos($src,'</nav>',$dockStart)+6;
ob_start();eval('?>'.substr($src,$dockStart,$dockEnd-$dockStart));$dock=ob_get_clean();
$loginTest=<<<'HTML'
<script>window.addEventListener('load',()=>{try{
const panel=document.getElementById('golpoochLoginPanel'),hands=[...document.querySelectorAll('.golpooch-hand')],w=document.documentElement.clientWidth;
if(document.getElementById('p16LoginIntro')||hands.length!==2||getComputedStyle(panel).display!=='none')throw Error('initial hidden state');
golpoochAnswer='left';hands[1].click();if(getComputedStyle(panel).display!=='none')throw Error('wrong hand must remain silent');
hands[0].click();if(getComputedStyle(panel).display==='none')throw Error('correct hand');
const p=panel.getBoundingClientRect();if(p.left<0||p.right>w+1||p.top<0||p.bottom>innerHeight+1)throw Error('panel overflow');
document.getElementById('p16ShowPassword').click();if(document.getElementById('p16LoginPassword').type!=='text')throw Error('password toggle');
document.getElementById('p16ShowPassword').click();if(document.getElementById('p16LoginPassword').type!=='password')throw Error('password hide');
panel.querySelector('.golpooch-close').click();if(getComputedStyle(panel).display!=='none')throw Error('close login');
document.body.dataset.p16='PASS';
}catch(e){document.body.dataset.p16='FAIL '+String(e)}});</script>
HTML;
$dockTest=<<<'HTML'
<script>window.addEventListener('load',()=>{try{const dock=document.querySelector('.p16-dock'),r=dock.getBoundingClientRect(),w=document.documentElement.clientWidth,links=[...dock.querySelectorAll('a')];if(links.length!==6||dock.querySelectorAll('a.active').length!==1)throw Error('navigation count');if(r.left<0||r.right>w+1||r.width>681||Math.abs((r.left+r.right)/2-w/2)>3)throw Error('dock bounds '+JSON.stringify(r.toJSON()));for(const a of links){const b=a.getBoundingClientRect();if(b.width<40||b.height<44)throw Error('target size '+b.width+' '+b.height);}document.body.dataset.p16='PASS'}catch(e){document.body.dataset.p16='FAIL '+String(e)}});</script>
HTML;
foreach(['login'=>[$login,$loginTest,'page-login'],'dock'=>['<div class="app-shell">'.$dock.'</div>',$dockTest,'page-dashboard']] as $kind=>[$body,$js,$cls]){
foreach(['320,800','390,844','1440,900'] as $view){$tmpBase=tempnam(sys_get_temp_dir(),'p16');$tmp=$tmpBase.'.html';rename($tmpBase,$tmp);file_put_contents($tmp,'<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$app.$css.'</style></head><body class="'.$cls.'">'.$body.$js.'</body></html>');$out=[];exec(escapeshellarg($chrome).' --headless --no-sandbox --disable-dev-shm-usage --window-size='.$view.' --virtual-time-budget=2000 --dump-dom '.escapeshellarg('file://'.$tmp).' 2>/dev/null',$out,$status);unlink($tmp);$dom=implode("\n",$out);preg_match('/data-p16="[^"]*"/',$dom,$marker);echo ($marker[0]??'no marker').PHP_EOL;check15($status===0&&str_contains($dom,'data-p16="PASS"'),'P16 '.$kind.' '.$view);}
}
