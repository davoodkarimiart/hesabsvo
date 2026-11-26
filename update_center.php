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
const UPDATE_CLIENT='1.2';
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
function updateManifestAt(string $sha):array {
 if(!preg_match('/^[a-f0-9]{40}$/D',$sha))throw new RuntimeException('Invalid commit SHA');
 $m=json_decode(updateFetch('https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/'.$sha.'/updates/channel.json'),true,16,JSON_THROW_ON_ERROR);
 if(!is_array($m)||($m['product']??'')!=='soltanhesab'||!isset($m['files'])||!is_array($m['files'])||count($m['files'])<1||count($m['files'])>15||updatePriority((string)($m['release']??''))===0)throw new RuntimeException('Invalid release manifest');
 $seen=[];
 foreach($m['files'] as $f){
  $p=$f['path']??null;$h=$f['sha256']??null;
  if(!is_string($p)||!preg_match('~^(?:assets/[a-zA-Z0-9_.-]+\.(?:js|css)|[a-zA-Z0-9_]+\.php|app/Services/[a-zA-Z0-9_]+\.php|app/Support/ReportXlsx68\.php)$~D',$p)||!is_string($h)||!preg_match('/^[a-f0-9]{64}$/D',$h)||isset($seen[$p])||in_array($p,['mcp.php','oauth.php','cron_soltan_backup.php'],true))throw new RuntimeException('Invalid update file');
  $seen[$p]=true;
 }
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
  if(($_POST['action']??'')!=='install'||!hash_equals((string)$m['release'],(string)($_POST['release']??''))||!hash_equals((string)$m['_commit'],(string)($_POST['commit']??''))||updatePriority($m['release'])<=updatePriority((string)$state['release']))throw new RuntimeException('Invalid install request');
  if(!is_dir($stateDir)&&!mkdir($stateDir,0700,true))throw new RuntimeException('Update storage is not writable');
  $lock=fopen($stateDir.'/update.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another update is running');
  try{
   clearstatcache(true,$stateFile);
   if(is_file($stateFile)){$fresh=json_decode((string)file_get_contents($stateFile),true,16,JSON_THROW_ON_ERROR);if(!is_array($fresh)||!isset($fresh['release'])||updatePriority($m['release'])<=updatePriority((string)$fresh['release']))throw new RuntimeException('Release already installed or invalid state');$state=$fresh;}
   $staged=[];
   foreach($m['files'] as $f){
    $p=$f['path'];$target=__DIR__.'/'.$p;
    if(!is_file($target)||is_link($target)||realpath($target)!==realpath(__DIR__).'/'.$p||!is_writable($target))throw new RuntimeException('Target is not writable: '.$p);
    $bytes=updateFetch('https://raw.githubusercontent.com/davoodkarimiart/hesabsvo/'.$m['_commit'].'/'.$p);
    if(!hash_equals($f['sha256'],hash('sha256',$bytes)))throw new RuntimeException('Checksum mismatch: '.$p);
    $staged[$p]=$bytes;
   }
   $dir=$stateDir.'/rollback_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
   if(!mkdir($dir,0700,true))throw new RuntimeException('Rollback directory cannot be created');
   foreach($staged as $p=>$b){$to=$dir.'/'.$p;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);if(!copy(__DIR__.'/'.$p,$to))throw new RuntimeException('Backup failed');}
   $changed=[];$oldState=is_file($stateFile)?file_get_contents($stateFile):null;$stateTmp=null;
   try{
    if(isset($staged['update_center.php'])){$self=$staged['update_center.php'];unset($staged['update_center.php']);$staged['update_center.php']=$self;}
    foreach($staged as $p=>$b){$to=__DIR__.'/'.$p;$tmp=$to.'.pending_'.bin2hex(random_bytes(5));if(file_put_contents($tmp,$b,LOCK_EX)!==strlen($b))throw new RuntimeException('Write failed');chmod($tmp,fileperms($to)&0777);if(!rename($tmp,$to))throw new RuntimeException('Rename failed');$changed[]=$p;if(function_exists('opcache_invalidate'))@opcache_invalidate($to,true);}
    $state=['release'=>$m['release'],'updated_at'=>date(DATE_ATOM),'rollback_dir'=>basename($dir)];
    $stateTmp=$stateFile.'.pending_'.bin2hex(random_bytes(5));$stateBytes=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);if(file_put_contents($stateTmp,$stateBytes,LOCK_EX)!==strlen($stateBytes)||!rename($stateTmp,$stateFile))throw new RuntimeException('State write failed');$stateTmp=null;
    Logger::audit('developer.update_installed',['release'=>$m['release'],'files'=>array_keys($staged)]);
    $msg='به‌روزرسانی با موفقیت نصب شد.';
   }catch(Throwable $e){if($stateTmp!==null)@unlink($stateTmp);foreach($changed as $p)@copy($dir.'/'.$p,__DIR__.'/'.$p);if(is_string($oldState))@file_put_contents($stateFile,$oldState,LOCK_EX);elseif($oldState===null)@unlink($stateFile);throw $e;}
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}catch(Throwable $e){$err=$e->getMessage();}
$current=(string)$state['release'];$latest=(string)($m['release']??'');$available=$m&&updatePriority($latest)>updatePriority($current);
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مرکز به‌روزرسانی سلطان حساب</title><style>
*{box-sizing:border-box}body{font:14px Tahoma,Arial,sans-serif;background:#f2f3fa;color:#172033;margin:0;padding:20px;line-height:1.9}.box{max-width:680px;margin:7vh auto;background:#fff;padding:24px;border-radius:18px;box-shadow:0 8px 30px #17203312}h1{font-size:20px;margin:0 0 8px}.muted{font-size:12px;color:#64748b}.info{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0}.info>div{border:1px solid #e4e7ef;background:#f8f9fc;border-radius:10px;padding:10px;min-width:0}.info b{display:block;font-size:16px;direction:ltr;text-align:right;color:#172033}.note{border:1px solid #e4e7ef;border-radius:10px;padding:12px;margin:12px 0}.good{color:#166534;background:#ecfdf3}.warn{color:#854d0e;background:#fff7df}.err{color:#991b1b;background:#fff1f2}.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:16px 0}button,a.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 17px;min-height:42px;border-radius:9px;border:0;font:inherit;text-decoration:none;cursor:pointer}button,.primary{background:#4515c9;color:#fff}.secondary{background:#eef0f6;color:#172033}form{margin:0}.meta{font-size:11px;color:#64748b;overflow-wrap:anywhere}code{direction:ltr;unicode-bidi:isolate}small{display:block;margin-top:14px;color:#64748b}@media(max-width:480px){body{padding:10px}.box{margin:2vh auto;padding:15px}.info{gap:6px}.info b{font-size:14px}.actions>*{flex:1}.actions form button{width:100%}}
</style></head><body><main class="box"><h1>مرکز به‌روزرسانی سلطان حساب</h1><div class="muted">فقط مخصوص Developer · نسخه آپدیتر <?=e(UPDATE_CLIENT)?></div>
<div class="info"><div>نسخه نصب‌شده<b><?=e($current)?></b></div><div>آخرین نسخه دریافت‌شده<b><?=e($latest?:'نامشخص')?></b></div></div>
<?php if($m):?><div class="meta">منبع: <?=e($m['_source'])?> · شناسه انتشار: <code><?=e(substr($m['_commit'],0,12))?></code> · بررسی: <?=e(date('Y-m-d H:i:s'))?></div><div class="note"><b>تغییرات</b><p><?=e((string)($m['notes']??''))?></p>تعداد فایل‌ها: <?=count($m['files'])?></div><?php endif;?>
<?php if(!empty($m['_warning'])):?><div class="note warn"><?=e($m['_warning'])?></div><?php endif;?>
<?php if($err):?><div class="note err"><?=e($err)?></div><?php endif;?>
<?php if($msg):?><div class="note good"><?=e($msg)?></div><?php endif;?>
<?php if($available):?><div class="note good">نسخه جدید آماده نصب است.</div><?php elseif($m&&!$err&&empty($m['_warning'])):?><div class="note good">نسخه جدیدتری در این کانال وجود ندارد.</div><?php else:?><div class="note warn">وضعیت آخرین نسخه به‌صورت زنده تأیید نشده است.</div><?php endif;?>
<div class="actions"><?php if($available):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="install"><input type="hidden" name="release" value="<?=e($latest)?>"><input type="hidden" name="commit" value="<?=e($m['_commit'])?>"><button type="submit" onclick="return confirm('از فایل‌ها بکاپ گرفته شود و نسخه جدید نصب شود؟')">بکاپ و نصب آپدیت</button></form><?php endif;?><a class="btn secondary" href="update_center.php?check=<?=e(bin2hex(random_bytes(6)))?>">بررسی دوباره</a><a class="btn secondary" href="index.php?page=health">بازگشت</a></div>
<small>فایل‌ها از یک commit ثابت دریافت و با SHA-256 کنترل می‌شوند. پیش از نصب بکاپ فایل‌ها ذخیره می‌شود. این آپدیتر برای Migration دیتابیس نیست.</small></main></body></html>
