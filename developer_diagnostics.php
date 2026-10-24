<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireDeveloper();
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
$allowed=['error'=>'خطاها','system'=>'رویدادهای سیستم','audit'=>'سوابق فعالیت'];
$channel=(string)($_GET['channel']??'error');if(!isset($allowed[$channel]))$channel='error';
$search=mb_substr(trim((string)($_GET['q']??'')),0,120);
$from=(string)($_GET['from']??''); $to=(string)($_GET['to']??'');
if(!preg_match('/^\d{4}-\d\d-\d\d$/',$from))$from='';
if(!preg_match('/^\d{4}-\d\d-\d\d$/',$to))$to='';
$level=(string)($_GET['level']??''); $export=($_GET['export']??'')==='csv';
$path=base_path('storage/logs/'.$channel.'.log'); $events=[];$readLimit=4*1024*1024;$scanned=0;
if(is_file($path)&&($fh=@fopen($path,'rb'))){
 $size=(int)(filesize($path)?:0);$pos=max(0,$size-$readLimit);fseek($fh,$pos);if($pos>0)fgets($fh);
 while(($line=fgets($fh))!==false){if(strlen($line)>16384)continue;$event=json_decode($line,true);if(!is_array($event))continue;$scanned++;
  $ts=(string)($event['ts']??'');$day=substr($ts,0,10);
  if($from!==''&&$day<$from || $to!==''&&$day>$to)continue;
  if($search!==''&&!str_contains(mb_strtolower($line),mb_strtolower($search)))continue;
  if($level!==''&&!str_contains(mb_strtolower((string)($event['message']??'')),mb_strtolower($level)))continue;
  $events[]=$event;if(count($events)>300)array_shift($events);
 }fclose($fh);
}
$events=array_reverse($events);
if($export){
 header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="soltan-'.$channel.'-logs.csv"');
 $fp=fopen('php://output','wb');fwrite($fp,"\xEF\xBB\xBF");fputcsv($fp,['زمان','رویداد','شناسه درخواست','شناسه کاربر','مسیر','جزئیات']);
 foreach($events as $v){$ctx=json_encode($v['context']??[],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);$safe=static fn($x)=>preg_replace('/^[=+@\-\t\r]/u',"'",(string)$x);fputcsv($fp,array_map($safe,[$v['ts']??'',$v['message']??'',$v['request_id']??'',$v['user_id']??'',$v['route']??'',$ctx]));} fclose($fp);exit;
}
function diagnosticLink(array $changes):string{return '?'.http_build_query(array_merge($_GET,$changes));}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>عیب‌یابی | سلطان حساب</title>
<style>:root{font-family:Tahoma,Arial,sans-serif;color:#1d2939;background:#f5f7fb}*{box-sizing:border-box}body{margin:0}main{max-width:1100px;margin:auto;padding:20px 14px 70px}header{background:#fff;border-bottom:1px solid #e6eaf0;padding:18px}header div{max-width:1100px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}h1{font-size:23px;margin:0}h2{font-size:17px;margin:0 0 12px}p,small{color:#667085}a{color:#175cd3;text-decoration:none}nav{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0}nav a,.btn,button{padding:10px 13px;background:#fff;border:1px solid #d0d5dd;border-radius:10px;color:#344054;cursor:pointer}nav a.on,.btn.primary,button{background:#155eef;color:white;border-color:#155eef}form{background:white;padding:15px;border:1px solid #e6eaf0;border-radius:14px;display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end}label{font-size:12px;color:#475467;display:grid;gap:6px}input,select{font:inherit;width:100%;padding:10px;border:1px solid #ccd3df;border-radius:8px;min-width:0}.toolbar{display:flex;gap:14px;align-items:center;justify-content:space-between;margin:20px 0;flex-wrap:wrap}.entry{margin:12px 0;background:#fff;border:1px solid #e5e9ef;border-radius:13px;padding:15px;overflow-wrap:anywhere}.entry summary{cursor:pointer;display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;font-weight:bold}.entry small{font-size:12px;display:block;margin-top:12px;direction:ltr;text-align:right;overflow-wrap:anywhere}.entry pre{white-space:pre-wrap;direction:ltr;text-align:left;background:#f8fafc;border-radius:8px;padding:12px;font-size:12px;overflow:auto}.pill{background:#eef4ff;padding:5px 9px;border-radius:7px;color:#175cd3;font-size:12px}.note{font-size:12px}button{font:inherit}@media(max-width:740px){form{grid-template-columns:1fr 1fr}form label:first-child{grid-column:span 2}.entry summary{align-items:flex-start}header h1{font-size:19px}}</style></head><body>
<header><div><h1>عیب‌یابی و لاگ‌های سلطان حساب</h1><a class="btn" href="index.php?page=health">بازگشت به سلامت سیستم</a></div></header><main>
<p>مخصوص توسعه‌دهنده؛ جست‌وجو در حداکثر ۴ مگابایت انتهای هر فایل و نمایش ۳۰۰ نتیجه آخر. رکوردهای قدیمی ممکن است شناسه درخواست نداشته باشند.</p>
<nav><?php foreach($allowed as $key=>$label):?><a class="<?= $channel===$key?'on':'' ?>" href="<?=e(diagnosticLink(['channel'=>$key,'export'=>null]))?>"><?=e($label)?></a><?php endforeach;?></nav>
<form method="get"><input type="hidden" name="channel" value="<?=e($channel)?>"><label>جست‌وجو (رویداد، Request ID، کاربر)<input name="q" value="<?=e($search)?>" maxlength="120" placeholder="عبارت مورد نظر"></label><label>از تاریخ میلادی<input type="date" name="from" value="<?=e($from)?>"></label><label>تا تاریخ میلادی<input type="date" name="to" value="<?=e($to)?>"></label><label>نوع رویداد<input name="level" value="<?=e($level)?>" placeholder="مثلاً php.fatal"></label><button type="submit">جست‌وجو</button></form>
<div class="toolbar"><span><?=count($events)?> نتیجه از <?=number_format($scanned)?> رویداد خوانده‌شده</span><a class="btn" href="<?=e(diagnosticLink(['export'=>'csv']))?>">دریافت CSV نتایج</a></div>
<?php foreach($events as $event):?><details class="entry"><summary><span><?=e((string)($event['message']??'بدون عنوان'))?></span><span class="pill"><?=e((string)($event['request_id']??'شناسه قدیمی'))?></span></summary><small><?=e((string)($event['ts']??''))?> | کاربر: <?=e((string)($event['user_id']??'-'))?> | <?=e((string)($event['route']??''))?></small><pre><?=e(json_encode($event['context']??[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)?:'{}')?></pre></details><?php endforeach;?>
<?php if(!$events):?><p>رویدادی با فیلترهای انتخاب‌شده پیدا نشد.</p><?php endif;?><p class="note">برای بررسی دقیق خطای MySQL، زمان خطا را با محدودیت اتصال در cPanel و بخش MySQL Processes یا پشتیبانی هاست تطبیق بدهید. این پنل خودش محدودیت سرور را تغییر نمی‌دهد.</p>
</main></body></html>
