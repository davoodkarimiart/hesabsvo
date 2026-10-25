<?php
/** Developer-only update center. Release files are strictly allowlisted and hash verified. */
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireDeveloper();
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
$stateDir=__DIR__.'/storage/updates';
$stateFile=$stateDir.'/current.json';
$state=['release'=>'0.6.53-p2'];
if(is_file($stateFile)){$stored=json_decode((string)file_get_contents($stateFile),true);if(is_array($stored)&&isset($stored['release']))$state=$stored;}
function updateFetch(string $url):string {
 if(!str_starts_with($url,'https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/'))throw new RuntimeException('Invalid update URL');
 $c=curl_init($url);
 if($c===false)throw new RuntimeException('cURL unavailable');
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'SoltanHesab-UpdateClient/1.0']);
 $r=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if(!is_string($r)||$status!==200||strlen($r)>1500000)throw new RuntimeException('Download failed');
 return $r;
}
function updatePriority(string $s):int {
 if(!preg_match('/^0\.6\.(\d+)-p(\d+)$/D',$s,$m))return 0;
 return (int)$m[1]*100000+(int)$m[2];
}
function updateManifest():array {
 $m=json_decode(updateFetch('https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/main/updates/channel.json'),true,16,JSON_THROW_ON_ERROR);
 if(!is_array($m)||($m['product']??'')!=='soltanhesab'||!isset($m['files'])||!is_array($m['files'])||count($m['files'])<1||count($m['files'])>15||updatePriority((string)($m['release']??''))===0)throw new RuntimeException('Invalid release manifest');
 $seen=[];
 foreach($m['files'] as $f){
  $p=$f['path']??null;$h=$f['sha256']??null;
  if(!is_string($p)||!preg_match('~^(?:assets/[a-zA-Z0-9_.-]+\.(?:js|css)|[a-zA-Z0-9_]+\.php|app/Services/[a-zA-Z0-9_]+\.php)$~D',$p)||!is_string($h)||!preg_match('/^[a-f0-9]{64}$/D',$h)||isset($seen[$p])||in_array($p,['update_center.php','mcp.php','oauth.php','cron_soltan_backup.php'],true))throw new RuntimeException('Invalid update file');
  $seen[$p]=true;
 }
 return $m;
}
$err='';$msg='';$m=null;
try{
 $m=updateManifest();
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  csrf_verify();
  if(($_POST['action']??'')!=='install'||!hash_equals((string)$m['release'],(string)($_POST['release']??''))||updatePriority($m['release'])<=updatePriority((string)$state['release']))throw new RuntimeException('Invalid install request');
  if(!is_dir($stateDir)&&!mkdir($stateDir,0700,true))throw new RuntimeException('Update storage is not writable');
  $lock=fopen($stateDir.'/update.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another update is running');
  try{
   $staged=[];
   foreach($m['files'] as $f){
    $p=$f['path'];$target=__DIR__.'/'.$p;
    if(!is_file($target)||!is_writable($target))throw new RuntimeException('Target is not writable: '.$p);
    $bytes=updateFetch('https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/main/'.$p);
    if(!hash_equals($f['sha256'],hash('sha256',$bytes)))throw new RuntimeException('Checksum mismatch: '.$p);
    $staged[$p]=$bytes;
   }
   $dir=$stateDir.'/rollback_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
   if(!mkdir($dir,0700,true))throw new RuntimeException('Rollback directory cannot be created');
   foreach($staged as $p=>$b){$to=$dir.'/'.$p;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);if(!copy(__DIR__.'/'.$p,$to))throw new RuntimeException('Backup failed');}
   $changed=[];
   try{
    foreach($staged as $p=>$b){$to=__DIR__.'/'.$p;$tmp=$to.'.pending_'.bin2hex(random_bytes(5));if(file_put_contents($tmp,$b,LOCK_EX)!==strlen($b))throw new RuntimeException('Write failed');chmod($tmp,0644);if(!rename($tmp,$to))throw new RuntimeException('Rename failed');$changed[]=$p;}
    $state=['release'=>$m['release'],'updated_at'=>date(DATE_ATOM),'rollback_dir'=>basename($dir)];
    file_put_contents($stateFile,json_encode($state,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
    Logger::audit('developer.update_installed',['release'=>$m['release'],'files'=>array_keys($staged)]);
    $msg='به‌روزرسانی با موفقیت نصب شد.';
   }catch(Throwable $e){foreach($changed as $p)@copy($dir.'/'.$p,__DIR__.'/'.$p);throw $e;}
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}catch(Throwable $e){$err=$e->getMessage();}
$current=(string)$state['release'];$latest=(string)($m['release']??'');
?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>به‌روزرسانی سلطان حساب</title><style>body{font:15px system-ui;background:#f1f5f9;padding:25px;color:#172033}.box{max-width:650px;margin:7vh auto;background:white;padding:25px;border-radius:18px;box-shadow:0 8px 30px #0001}p{line-height:2}.good{color:green}.err{color:#b91c1c}button{padding:11px 20px;background:#2454c9;color:white;border:0;border-radius:8px}</style><main class="box"><h1>مرکز به‌روزرسانی Developer</h1><p>نسخه نصب‌شده: <b><?=htmlspecialchars($current,ENT_QUOTES)?></b></p><p>آخرین نسخه GitHub: <b><?=htmlspecialchars($latest,ENT_QUOTES)?></b></p><?php if($m):?><p><?=htmlspecialchars((string)($m['notes']??''),ENT_QUOTES)?></p><?php endif;?><?php if($err):?><p class="err"><?=htmlspecialchars($err,ENT_QUOTES)?></p><?php endif;?><?php if($msg):?><p class="good"><?=htmlspecialchars($msg,ENT_QUOTES)?></p><?php endif;?><?php if($m&&updatePriority($latest)>updatePriority($current)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="install"><input type="hidden" name="release" value="<?=htmlspecialchars($latest,ENT_QUOTES)?>"><button onclick="return confirm('بکاپ فایل‌ها گرفته شود و آپدیت نصب شود؟')">بکاپ و نصب آپدیت</button></form><?php else:?><p class="good">نسخه جدیدتری در این کانال وجود ندارد.</p><?php endif;?><p><a href="update_center.php">بررسی دوباره</a></p><small>تنها Developer با تأیید دستی می‌تواند آپدیت کند. هر فایل با SHA-256 بررسی و پیش از جایگزینی نسخه پشتیبان آن ذخیره می‌شود. این روش برای تغییرات دیتابیس مناسب نیست.</small></main></html>
