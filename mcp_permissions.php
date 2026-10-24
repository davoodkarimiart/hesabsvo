<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/mcp_oauth_lib.php';
Auth::requireDeveloper();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$esc=static fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$expect=['read:companies'=>'list_companies, list_archived_companies','read:panels'=>'list_panels','read:customers'=>'list_customers, get_customer_accounts, get_customer_ledger, list_unlinked_accounts','read:reports'=>'list_daily_reports, get_daily_report, get_daily_report_rows, list_transfers, preview_transfer','write:companies'=>'create/update company and panel, restore_company','write:customers'=>'create/update customer, link_customer_accounts','write:transfers'=>'save_transfer, void_transfer','admin:diagnostics'=>'backup_status, diagnostics_health, diagnostics_logs','admin:backup'=>'create_backup, send_backup_telegram'];
$users=[];
try {
 $db=Database::connection();
 $users=$db->query('SELECT id,username,role,active,security_version FROM users')->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {http_response_code(503); exit('دریافت فهرست کاربران ممکن نشد.');}
$usersById=[]; foreach($users as $u)$usersById[(int)$u['id']]=$u;
$data=mo_data();
$credentials=__DIR__.'/storage/mcp/credentials.json';
$raw=is_file($credentials)?@file_get_contents($credentials):false;
$registry=is_string($raw)?json_decode($raw,true):[];
if(!is_array($registry))$registry=[];
$now=time();$group=[];
foreach(($registry['entries']??[]) as $grant){
 if(!is_array($grant))continue;
 $uid=(int)($grant['user_id']??0);$u=$usersById[$uid]??null;
 if(!$u||((int)($grant['expires_at']??0))<=$now||!(int)$u['active']||((int)$u['security_version']!==(int)($grant['security_version']??-1)))continue;
 $client=(string)($grant['client_id']??'');
 $key=hash('sha256',$client.'|'.$uid);
 $current=$group[$key]??['name'=>(string)($data['clients'][$client]['name']??'کلاینت ناشناس'),'user'=>(string)$u['username'],'role'=>(string)$u['role'],'scopes'=>[],'expiration'=>0,'count'=>0];
 foreach(($grant['scopes']??[]) as $s)if(is_string($s)&&isset($expect[$s]))$current['scopes'][$s]=true;
 $current['expiration']=max($current['expiration'],(int)$grant['expires_at']);$current['count']++;
 $group[$key]=$current;
}
// These are active access grants on disk. They do not prove what a specific client has cached.
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تشخیص مجوز MCP | سلطان حساب</title><style>
body{margin:0;background:#f3f6fa;color:#172334;font:15px system-ui,Tahoma,sans-serif;line-height:1.9}.wrap{max-width:1050px;margin:30px auto;padding:16px}.card{background:#fff;border:1px solid #dee6ee;border-radius:14px;padding:20px;margin:15px 0;box-shadow:0 4px 18px #1232  }h1{font-size:23px}h2{font-size:18px}.btn{background:#175bb9;color:white;border-radius:8px;padding:9px 15px;text-decoration:none;display:inline-block}table{width:100%;border-collapse:collapse}td,th{text-align:right;vertical-align:top;border-bottom:1px solid #e3e9ee;padding:9px}code{direction:ltr;display:inline-block;background:#f3f6fa;padding:2px 5px;border-radius:4px;overflow-wrap:anywhere}.muted{color:#58677a}.ok{color:#0a7544}.warn{color:#9b5b08}.sc{direction:ltr;text-align:left;unicode-bidi:plaintext} </style></head><body><main class="wrap"><a class="btn" href="mcp_connections.php">بازگشت به اتصالات</a><h1>عیب‌یابی مجوزها و ابزارهای MCP</h1><p class="muted">نسخه <?= $esc(trim((string)@file_get_contents(__DIR__.'/VERSION'))) ?>. فقط Developer. این صفحه هیچ توکن یا کد محرمانه‌ای را نمایش نمی‌دهد.</p>
<section class="card"><h2>ابزارهای موجود در سورس سرور</h2><table><thead><tr><th>مجوز</th><th>ابزار مجاز</th></tr></thead><tbody><?php foreach($expect as $s=>$tool): ?><tr><td><code><?= $esc($s) ?></code></td><td><code><?= $esc($tool) ?></code></td></tr><?php endforeach; ?></tbody></table><p>فهرست واقعی <code>tools/list</code> بر اساس توکن درخواست فیلتر می‌شود. موجود بودن ابزار در این جدول، به معنی مجاز بودن اتصال ChatGPT نیست.</p></section>
<section class="card"><h2>توکن‌های دسترسی فعال و مجوزهایشان</h2><p class="muted">تنها توکن‌های هنوز منقضی‌نشده نشان داده شده‌اند. توکن‌های کوتاه‌مدت پس از تمدید ممکن است عوض شوند.</p><div style="overflow:auto"><table><thead><tr><th>کلاینت</th><th>کاربر</th><th>مجوزهای فعال</th><th>انقضا</th></tr></thead><tbody><?php foreach($group as $g):?><tr><td><?= $esc($g['name']) ?></td><td><?= $esc($g['user']) ?> (<?= $esc($g['role']) ?>)</td><td class="sc"><?php foreach(array_keys($g['scopes']) as $s):?><code><?= $esc($s) ?></code> <?php endforeach; ?></td><td><?= $esc(date('Y-m-d H:i',$g['expiration'])) ?></td></tr><?php endforeach; ?><?php if(!$group):?><tr><td colspan="4">هیچ توکن دسترسی فعال در فایل ذخیره‌سازی پیدا نشد.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><h2>چرا ChatGPT ابزار جدید را نمی‌بیند؟</h2><p>تعداد ابزارها به نقش کاربر و scopeهای همان توکن بستگی دارد؛ مثلاً <code>admin:backup</code> فقط ابزارهای بکاپ را اضافه می‌کند و به‌تنهایی دسترسی گزارش یا نوشتن نمی‌دهد. مجوزهای تازه باید در <b>درخواست OAuth</b> کلاینت گنجانده و توسط کاربر تأیید شوند؛ نباید بدون رضایت به توکن قدیمی اضافه شوند.</p><p>این صفحه وضعیت داخل ChatGPT را Refresh نمی‌کند. اگر پس از اتصال مجدد همچنان دو مجوز قدیمی صادر می‌شوند، لازم است درخواست مجوز کلاینت و صفحه تأیید OAuth بررسی شود. فقط قطع‌وصل مکرر راه‌حل نیست.</p><p class="warn">تغییر مجوزها یا عملیات مالی از طریق این صفحه انجام نمی‌شود.</p></section>
</main></body></html>
