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
