<?php

declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$isCli=PHP_SAPI==='cli';
if(!$isCli)Auth::requireDeveloper();
$error='';$success='';
$run=function() use (&$success,$isCli):void{
    $done=Migrator::runPending();
    $pdo=Database::connection();
    $pdo->prepare("INSERT INTO app_meta(meta_key,meta_value) VALUES('app_version','0.4.0') ON DUPLICATE KEY UPDATE meta_value='0.4.0'")->execute();
    Logger::system('upgrade.0_4_0.completed',['migrations'=>$done,'via'=>PHP_SAPI]);
    if(!$isCli&&Auth::check())Logger::audit('upgrade.0_4_0.completed',['migrations'=>$done]);
    $success=$done?'Migrationهای جدید اجرا شدند: '.implode(', ',$done):'Migration جدیدی باقی نمانده بود؛ نسخه 0.4.0 آماده است.';
};
if($isCli){try{$run();echo "[OK] $success\nLatest: ".Migrator::latest()."\n";exit(0);}catch(Throwable $e){Logger::error('upgrade.0_4_0.failed',['error'=>$e->getMessage(),'via'=>'cli']);fwrite(STDERR,"[ERROR] {$e->getMessage()}\n");exit(1);}}
if(is_post()){try{csrf_verify();$run();}catch(Throwable $e){Logger::error('upgrade.0_4_0.failed',['error'=>$e->getMessage(),'via'=>'web']);$error=$e->getMessage();}}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ارتقا 0.4.0</title><link rel="stylesheet" href="../assets/app.css?v=040"></head><body><main class="install-shell"><section class="card install-card"><div class="brandline"><div class="logo">س</div><div><h1>ارتقا به 0.4.0</h1><p>Customer + Reports + Exports + Settings</p></div></div><?php if($success):?><div class="notice ok"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="notice bad"><?=e($error)?></div><?php endif;?><form method="post" class="stack"><?=csrf_field()?><button class="primary">اجرای Migration</button></form><a class="btn ghost" href="../index.php?page=dashboard">بازگشت</a><div class="tiny">Terminal: php upgrade/v0.4.0.php</div></section></main></body></html>
