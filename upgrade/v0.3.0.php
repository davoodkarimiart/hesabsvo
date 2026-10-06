<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) Auth::requireDeveloper();
$error='';$success='';$applied=[];
$run=function() use (&$applied,&$success,$isCli): void {
    $applied=Migrator::runPending();
    $pdo=Database::connection();
    $pdo->prepare("INSERT INTO app_meta(meta_key,meta_value) VALUES('app_version','0.3.0') ON DUPLICATE KEY UPDATE meta_value='0.3.0'")->execute();
    Logger::system('upgrade.0_3_0.completed',['migrations'=>$applied,'via'=>PHP_SAPI]);
    if(!$isCli && Auth::check())Logger::audit('upgrade.0_3_0.completed',['migrations'=>$applied]);
    $success=$applied?'Migrationهای جدید اجرا شدند: '.implode(', ',$applied):'Migration جدیدی باقی نمانده بود؛ نسخه 0.3.0 آماده است.';
};
if($isCli){try{$run();echo "[OK] $success\n";exit(0);}catch(Throwable $e){Logger::error('upgrade.0_3_0.failed',['error'=>$e->getMessage(),'via'=>'cli']);fwrite(STDERR,"[ERROR] {$e->getMessage()}\n");exit(1);}}
if(is_post()){try{csrf_verify();$run();}catch(Throwable $e){Logger::error('upgrade.0_3_0.failed',['error'=>$e->getMessage(),'via'=>'web']);$error=$e->getMessage();}}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>ارتقا 0.3.0</title><link rel="stylesheet" href="../assets/app.css?v=030"></head><body><main class="install-shell"><section class="card install-card"><div class="brandline"><div class="logo">س</div><div><h1>ارتقا به 0.3.0</h1><p>Report_WL + Accounting Engine + Preview + Finalization</p></div></div><?php if($success):?><div class="notice ok"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="notice bad"><?=e($error)?></div><?php endif;?><form method="post" class="stack"><?=csrf_field()?><button class="primary">اجرای Migration</button></form><a class="btn ghost" href="../index.php?page=entry">ورود اطلاعات</a><div class="tiny">Terminal: php upgrade/v0.3.0.php</div></section></main></body></html>
