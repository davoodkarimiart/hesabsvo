<?php
/** Developer-only update center. Release files are strictly allowlisted and hash verified. */
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireDeveloper();
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Pragma: no-cache');header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow');
$stateDir=__DIR__.'/storage/updates';
$stateFile=$stateDir.'/current.json';
$state=['release'=>'0.6.53-p2'];
if(is_file($stateFile)){$stored=json_decode((string)file_get_contents($stateFile),true);if(is_array($stored)&&isset($stored['release']))$state=$stored;}
const UPDATE_CLIENT='1.4';
const UPDATE_MAX_FILES=40;
const UPDATE_EXPENSE_MIGRATION='database/migrations/017_operating_expenses.sql';
const UPDATE_RECOVERY_COMMIT='afb9c7b8662ab1727a31e6a65661d2c7d3cee602';
function updateFetch(string $url):string {
 if(!str_starts_with($url,'https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/')&&!str_starts_with($url,'https://api.github.com/repos/davoodkarimiart/hesabsvo/git/ref/heads/main?'))throw new RuntimeException('Invalid update URL');
 $c=curl_init($url);
 if($c===false)throw new RuntimeException('cURL unavailable');
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'SoltanHesab-UpdateClient/'.UPDATE_CLIENT,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache','Pragma: no-cache']]);
 $r=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if(!is_string($r)||$status!==200||strlen($r)>1500000)throw new RuntimeException('Download failed (HTTP '.(int)$status.')');
 return $r;
}
function updatePriority(string $s):int {
 if(!preg_match('/^0\.6\.(\d+)-p(\d+)$/D',$s,$m))return 0;
 return (int)$m[1]*100000+(int)$m[2];
}
function updateAllowedPath(string $p):bool {
 if(in_array($p,['oauth.php','cron_soltan_backup.php'],true))return false;
 if(in_array($p,['mcp.php','app/Logger.php','app/bootstrap.php'],true))return true;
 return $p===UPDATE_EXPENSE_MIGRATION||preg_match('~^(?:assets/[a-zA-Z0-9_.-]+\.(?:js|css)|[a-zA-Z0-9_]+\.php|app/Services/[a-zA-Z0-9_]+\.php|app/Support/ReportXlsx68\.php)$~D',$p)===1;
}
function updateMayCreate(string $p):bool {
 return in_array($p,[UPDATE_EXPENSE_MIGRATION,'expense_page.php','app/Services/ExpenseService.php'],true);
}
function updateMigrationEntry(?array $m):?array {
 foreach(($m['files']??[]) as $f)if(($f['path']??'')===UPDATE_EXPENSE_MIGRATION)return $f;
 return null;
}
function updateMigrationStatements(string $sql):array {
 // This client deliberately admits only the two additive, retry-safe expense tables.
 $parts=array_values(array_filter(array_map('trim',explode(';',$sql)),static fn($x)=>$x!==''));
 if(count($parts)!==2)throw new RuntimeException('Unexpected expense migration statements');
 foreach(['operating_expenses','operating_expense_revisions'] as $i=>$table){
  if(!preg_match('/^CREATE TABLE IF NOT EXISTS '.preg_quote($table,'/').'\s*\(/i',$parts[$i]))throw new RuntimeException('Unexpected expense migration table');
 }
 return $parts;
}
function updateExpenseTables(PDO $pdo):bool {
 return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND table_name IN ('operating_expenses','operating_expense_revisions')")->fetchColumn()===2;
}
function updateMigrationApplied():bool {
 $pdo=Database::connection();$st=$pdo->prepare('SELECT COUNT(*) FROM migrations WHERE version=?');$st->execute(['017_operating_expenses']);
 $marked=(int)$st->fetchColumn()>0;
 if($marked&&!updateExpenseTables($pdo))throw new RuntimeException('Migration ثبت شده اما جدول‌ها ناقص‌اند؛ نصب متوقف شد.');
 return $marked;
}
function updateRunExpenseMigration(string $bytes,string $hash):string {
 if(!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Migration checksum mismatch');
 $parts=updateMigrationStatements($bytes);
 if(updateMigrationApplied())return 'Migration قبلاً اجرا شده است.';
 require_once __DIR__.'/app/Services/DeveloperBackup633.php';
 $backup=DeveloperBackup633::backup();
 if(empty($backup['name'])||empty($backup['size']))throw new RuntimeException('Database backup failed');
 $pdo=Database::connection();
 // MySQL DDL implicitly commits. Do not drop tables on failure; retries are additive.
 foreach($parts as $statement)$pdo->exec($statement);
 if(!updateExpenseTables($pdo))throw new RuntimeException('ساخت جدول‌های هزینه کامل نشد.');
 $pdo->prepare('INSERT INTO migrations(version,applied_at) VALUES(?,NOW())')->execute(['017_operating_expenses']);
 Logger::audit('developer.migration_installed',['version'=>'017_operating_expenses','sha256'=>$hash,'backup'=>$backup['name']]);
 return 'Migration هزینه‌ها اجرا شد. بکاپ: '.$backup['name'];
}
function updateManifestAt(string $sha):array {
 if(!preg_match('/^[a-f0-9]{40}$/D',$sha))throw new RuntimeException('Invalid commit SHA');
 $m=json_decode(updateFetch('https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/'.$sha.'/updates/channel.json'),true,16,JSON_THROW_ON_ERROR);
 if(!is_array($m)||($m['product']??'')!=='soltanhesab'||!isset($m['files'])||!is_array($m['files'])||count($m['files'])<1||count($m['files'])>UPDATE_MAX_FILES||updatePriority((string)($m['release']??''))===0)throw new RuntimeException('Invalid release manifest');
 $seen=[];
 foreach($m['files'] as $f){
  $p=$f['path']??null;$h=$f['sha256']??null;
  if(!is_string($p)||!updateAllowedPath($p)||!is_string($h)||!preg_match('/^[a-f0-9]{64}$/D',$h)||isset($seen[$p]))throw new RuntimeException('Invalid update file');
  $seen[$p]=true;
 }
 if(isset($m['min_client'])&&(!is_string($m['min_client'])||version_compare(UPDATE_CLIENT,$m['min_client'],'<')))throw new RuntimeException('ابتدا خود آپدیتر را ارتقا بده.');
 $m['_commit']=$sha;return $m;
}
function updateManifest():array {
 $live=null;$reason='';
 try{$ref=json_decode(updateFetch('https://api.github.com/repos/davoodkarimiart/hesabsvo/git/ref/heads/main?check='.bin2hex(random_bytes(8))),true,16,JSON_THROW_ON_ERROR);$sha=(string)($ref['object']['sha']??'');if(($ref['ref']??'')!=='refs/heads/main'||($ref['object']['type']??'')!=='commit')throw new RuntimeException('Unexpected GitHub ref');$live=updateManifestAt($sha);}catch(Throwable $e){$reason=$e->getMessage();}
 if($live!==null&&updatePriority($live['release'])>=updatePriority('0.6.53-p7')){$live['_source']='GitHub API';return $live;}
 $m=updateManifestAt(UPDATE_RECOVERY_COMMIT);$m['_source']='نسخه ثابت تأییدشده';$m['_warning']='دریافت کانال زنده تأیید نشد؛ فقط نسخه ثابت قابل نصب است. '.($reason!==''?$reason:'پاسخ قدیمی GitHub');return $m;
}
$err='';$msg='';$m=null;
try{
 $m=updateManifest();
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  csrf_verify();
  $action=(string)($_POST['action']??'');
  if(!in_array($action,['install','migration'],true)||!hash_equals((string)$m['release'],(string)($_POST['release']??''))||!hash_equals((string)$m['_commit'],(string)($_POST['commit']??''))||updatePriority($m['release'])<=updatePriority((string)$state['release']))throw new RuntimeException('Invalid install request');
  if(!is_dir($stateDir)&&!mkdir($stateDir,0700,true))throw new RuntimeException('Update storage is not writable');
  $lock=fopen($stateDir.'/update.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another update is running');
  try{
   clearstatcache(true,$stateFile);
   if(is_file($stateFile)){$fresh=json_decode((string)file_get_contents($stateFile),true,16,JSON_THROW_ON_ERROR);if(!is_array($fresh)||!isset($fresh['release'])||updatePriority($m['release'])<=updatePriority((string)$fresh['release']))throw new RuntimeException('Release already installed or invalid state');$state=$fresh;}
   if($action==='migration'){
    $entry=updateMigrationEntry($m);if(!$entry)throw new RuntimeException('این انتشار Migration ندارد.');
    $bytes=updateFetch('https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/'.$m['_commit'].'/'.$entry['path']);
    $msg=updateRunExpenseMigration($bytes,$entry['sha256']);
   }else{
   $staged=[];$newFiles=[];
   foreach($m['files'] as $f){
    $p=$f['path'];$target=__DIR__.'/'.$p;
    if(is_link($target))throw new RuntimeException('Symbolic link rejected: '.$p);
    if(is_file($target)){if(realpath($target)!==realpath(__DIR__).'/'.$p||!is_writable($target))throw new RuntimeException('Target is not writable: '.$p);}
    else{if(file_exists($target)||!updateMayCreate($p)||realpath(dirname($target))!==(dirname($p)==='.'?realpath(__DIR__):realpath(__DIR__).'/'.dirname($p))||!is_writable(dirname($target)))throw new RuntimeException('New target not allowed: '.$p);$newFiles[$p]=true;}
    $bytes=updateFetch('https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/'.$m['_commit'].'/'.$p);
    if(!hash_equals($f['sha256'],hash('sha256',$bytes)))throw new RuntimeException('Checksum mismatch: '.$p);
    $staged[$p]=$bytes;
   }
   $dir=$stateDir.'/rollback_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
   if(!mkdir($dir,0700,true))throw new RuntimeException('Rollback directory cannot be created');
   foreach($staged as $p=>$b){if(isset($newFiles[$p]))continue;$to=$dir.'/'.$p;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);if(!copy(__DIR__.'/'.$p,$to))throw new RuntimeException('Backup failed');}
   $changed=[];$oldState=is_file($stateFile)?file_get_contents($stateFile):null;$stateTmp=null;
   try{
    $migration=updateMigrationEntry($m);if($migration)updateRunExpenseMigration($staged[$migration['path']],$migration['sha256']);
    if(isset($staged['update_center.php'])){$self=$staged['update_center.php'];unset($staged['update_center.php']);$staged['update_center.php']=$self;}
    foreach($staged as $p=>$b){$to=__DIR__.'/'.$p;$tmp=$to.'.pending_'.bin2hex(random_bytes(5));if(file_put_contents($tmp,$b,LOCK_EX)!==strlen($b))throw new RuntimeException('Write failed');chmod($tmp,isset($newFiles[$p])?0644:(fileperms($to)&0777));if(!rename($tmp,$to))throw new RuntimeException('Rename failed');$changed[]=$p;if(function_exists('opcache_invalidate'))@opcache_invalidate($to,true);}
    $state=['release'=>$m['release'],'updated_at'=>date(DATE_ATOM),'rollback_dir'=>basename($dir)];
    $stateTmp=$stateFile.'.pending_'.bin2hex(random_bytes(5));$stateBytes=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);if(file_put_contents($stateTmp,$stateBytes,LOCK_EX)!==strlen($stateBytes)||!rename($stateTmp,$stateFile))throw new RuntimeException('State write failed');$stateTmp=null;
    Logger::audit('developer.update_installed',['release'=>$m['release'],'files'=>array_keys($staged),'commit'=>$m['_commit']]);
    $msg='به‌روزرسانی با موفقیت نصب شد.';
   }catch(Throwable $e){if($stateTmp!==null)@unlink($stateTmp);foreach($changed as $p){if(isset($newFiles[$p]))@unlink(__DIR__.'/'.$p);else @copy($dir.'/'.$p,__DIR__.'/'.$p);if(function_exists('opcache_invalidate'))@opcache_invalidate(__DIR__.'/'.$p,true);}if(is_string($oldState))@file_put_contents($stateFile,$oldState,LOCK_EX);elseif($oldState===null)@unlink($stateFile);throw $e;}
   }
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}catch(Throwable $e){$err=$e->getMessage();Logger::error('developer.update_failed',['release'=>$m['release']??null,'error'=>$err]);}
$current=(string)$state['release'];$latest=(string)($m['release']??'');$available=$m&&updatePriority($latest)>updatePriority($current);$migrationEntry=updateMigrationEntry($m);$migrationDone=false;$migrationError='';if($migrationEntry){try{$migrationDone=updateMigrationApplied();}catch(Throwable $e){$migrationError=$e->getMessage();}}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مرکز به‌روزرسانی سلطان حساب</title><style>
*{box-sizing:border-box}body{font:14px Tahoma,Arial,sans-serif;background:#f2f3fa;color:#172033;margin:0;padding:20px;line-height:1.9}.box{max-width:680px;margin:7vh auto;background:#fff;padding:24px;border-radius:18px;box-shadow:0 8px 30px #17203312}h1{font-size:20px;margin:0 0 8px}.muted{font-size:12px;color:#64748b}.info{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0}.info>div{border:1px solid #e4e7ef;background:#f8f9fc;border-radius:10px;padding:10px;min-width:0}.info b{display:block;font-size:16px;direction:ltr;text-align:right;color:#172033}.note{border:1px solid #e4e7ef;border-radius:10px;padding:12px;margin:12px 0}.good{color:#166534;background:#ecfdf3}.warn{color:#854d0e;background:#fff7df}.err{color:#991b1b;background:#fff1f2}.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:16px 0}button,a.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 17px;min-height:42px;border-radius:9px;border:0;font:inherit;text-decoration:none;cursor:pointer}button,.primary{background:#4515c9;color:#fff}.secondary{background:#eef0f6;color:#172033}form{margin:0}.meta{font-size:11px;color:#64748b;overflow-wrap:anywhere}code{direction:ltr;unicode-bidi:isolate}small{display:block;margin-top:14px;color:#64748b}@media(max-width:480px){body{padding:10px}.box{margin:2vh auto;padding:15px}.info{gap:6px}.info b{font-size:14px}.actions>*{flex:1}.actions form button{width:100%}}
</style><link rel="stylesheet" href="assets/transfer619.css?v=667"></head><body class="dev15-standalone dev15-update"><main class="box"><nav class="dev15-nav" aria-label="ناوبری مدیریت"><a href="index.php?page=health"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg><span>سیستم</span></a><a href="update_center.php" aria-current="page"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 1 2 5m-2-1v4h4M12 7v5l3 2"/></svg><span>آپدیت</span></a><a href="developer_diagnostics.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h5"/></svg><span>لاگ‌ها</span></a><a href="developer_backups.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v6H4zM4 13h16v6H4zM7 8h1m-1 8h1"/></svg><span>بکاپ</span></a><a href="mcp_connections.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5H5v4m10-4h4v4M5 15v4h4m10-4v4h-4M9 12h6m-3-3v6"/></svg><span>MCP</span></a></nav><h1>مرکز به‌روزرسانی سلطان حساب</h1><div class="muted">فقط مخصوص Developer · نسخه آپدیتر <?=e(UPDATE_CLIENT)?></div>
<div class="info"><div>نسخه نصب‌شده<b><?=e($current)?></b></div><div>آخرین نسخه دریافت‌شده<b><?=e($latest?:'نامشخص')?></b></div></div>
<?php if($m):?><div class="meta dev15-release-meta"><div>منبع <bdi dir="ltr"><?=e($m['_source'])?></bdi></div><div>شناسه انتشار <code dir="ltr"><?=e(substr($m['_commit'],0,12))?></code></div><div>آخرین بررسی <time dir="ltr"><?=e(date('Y-m-d H:i:s'))?></time></div></div><div class="note"><b>تغییرات</b><ul class="dev15-release-notes"><?php foreach(preg_split('/[؛\n]+/u',(string)($m['notes']??''),-1,PREG_SPLIT_NO_EMPTY) as $note):?><li><?=e(trim($note))?></li><?php endforeach;?></ul><small>تعداد فایل‌ها: <?=count($m['files'])?></small></div><?php endif;?>
<?php if(!empty($m['_warning'])):?><div class="note warn"><?=e($m['_warning'])?></div><?php endif;?>
<?php if($err):?><div class="note err"><?=e($err)?></div><?php endif;?>
<?php if($msg):?><div class="note good"><?=e($msg)?></div><?php endif;?>
<?php if($available):?><div class="note good">نسخه جدید آماده نصب است.</div><?php elseif($m&&!$err&&empty($m['_warning'])):?><div class="note good">نسخه جدیدتری در این کانال وجود ندارد.</div><?php else:?><div class="note warn">وضعیت آخرین نسخه به‌صورت زنده تأیید نشده است.</div><?php endif;?>
<div class="note"><b>Migration دیتابیس</b><p><?=e($migrationError!==''?$migrationError:($migrationEntry?($migrationDone?'انجام شده؛ آماده نصب فایل‌ها':'آماده اجرا همراه بکاپ دیتابیس'):'در انتشار فعلی Migration وجود ندارد.'))?></p><?php if($available&&$migrationEntry&&!$migrationDone&&$migrationError===''):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="migration"><input type="hidden" name="release" value="<?=e($latest)?>"><input type="hidden" name="commit" value="<?=e($m['_commit'])?>"><button type="submit" onclick="return confirm('از دیتابیس بکاپ گرفته و Migration اجرا شود؟')">بکاپ و اجرای Migration</button></form><?php endif;?></div>
<div class="actions"><?php if($available):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="install"><input type="hidden" name="release" value="<?=e($latest)?>"><input type="hidden" name="commit" value="<?=e($m['_commit'])?>"><button type="submit" onclick="return confirm('از فایل‌ها بکاپ گرفته شود و نسخه جدید نصب شود؟')">بکاپ و نصب آپدیت</button></form><?php endif;?><a class="btn secondary" href="update_center.php?check=<?=e(bin2hex(random_bytes(6)))?>">بررسی دوباره</a><a class="btn secondary" href="index.php?page=health">بازگشت</a></div>
<details class="dev15-guide"><summary>راهنمای امنیت نصب</summary><small>فایل‌ها از یک commit ثابت دریافت و با SHA-256 کنترل می‌شوند. پیش از نصب بکاپ فایل‌ها ذخیره می‌شود. اگر انتشار دارای Migration مجاز باشد، ابتدا بکاپ دیتابیس گرفته می‌شود و سپس Migration قبل از نصب فایل‌ها اجرا می‌شود. خطای Migration مانع نصب خواهد شد؛ جدول‌های جدید در اجرای ناقص حذف نمی‌شوند.</small></details></main><nav class="bottomnav p16-dock p17-dev-dock" aria-label="ناوبری اصلی"><a href="index.php?page=dashboard"><i class="p16-dock-icon"><svg viewBox="0 0 24 24"><path d="M4 11.5 12 5l8 6.5V20h-5v-5H9v5H4Z"/></svg></i><span>خانه</span></a><a href="index.php?page=entry"><i class="p16-dock-icon"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></i><span>ورود اطلاعات</span></a><a href="index.php?page=customers"><i class="p16-dock-icon"><svg viewBox="0 0 24 24"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8-1a2.5 2.5 0 1 0 0-5M3 19c0-3 2-5 5-5s5 2 5 5m1-5c3 0 5 2 5 5"/></svg></i><span>مشتری‌ها</span></a><a href="index.php?page=analytics"><i class="p16-dock-icon"><svg viewBox="0 0 24 24"><path d="M5 19V9m7 10V5m7 14v-7"/></svg></i><span>گزارش‌ها</span></a><a href="index.php?page=transfers"><i class="p16-dock-icon"><svg viewBox="0 0 24 24"><path d="M4 7h15m0 0-4-4m4 4-4 4M20 17H5m0 0 4-4m-4 4 4 4"/></svg></i><span>ترانسفر</span></a><a href="index.php?page=companies"><i class="p16-dock-icon"><svg viewBox="0 0 24 24"><path d="M4 20V8l8-4 8 4v12M8 20v-7h8v7"/></svg></i><span>شرکت‌ها</span></a></nav></body></html>
