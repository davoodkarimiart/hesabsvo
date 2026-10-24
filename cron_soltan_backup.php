<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require __DIR__.'/app/bootstrap.php';require_once __DIR__.'/app/Services/DeveloperBackup633.php';
try {DeveloperBackup633::rotateLogs();$r=DeveloperBackup633::backup();echo 'Backup created: '.$r['name']."\n";
 if(DeveloperBackup633::settings()['token']??false){try{DeveloperBackup633::sendTelegram($r['name']);echo "Telegram delivered\n";}catch(Throwable $e){fwrite(STDERR,"Telegram failed; backup kept locally\n");}}
}catch(Throwable $e){fwrite(STDERR,"Backup failed: ".get_class($e)."\n");exit(1);}
