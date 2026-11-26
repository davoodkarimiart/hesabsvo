<?php
declare(strict_types=1);
$root=dirname(__DIR__);$src=file_get_contents($root.'/update_center.php');
$start=strpos($src,'const UPDATE_CLIENT=');$end=strpos($src,'$err=', $start);
if($start===false||$end===false)throw new RuntimeException('update functions not found');
eval(substr($src,$start,$end-$start));
function bridgeCheck($value,string $label):void{if(!$value)throw new RuntimeException('FAIL '.$label);echo '[OK] '.$label.PHP_EOL;}
bridgeCheck(UPDATE_CLIENT==='1.3','updater 1.3');
bridgeCheck(updateAllowedPath(UPDATE_EXPENSE_MIGRATION)&&!updateAllowedPath('database/migrations/evil.sql')&&!updateAllowedPath('../config/config.php'),'migration allowlist');
bridgeCheck(updateMayCreate(UPDATE_EXPENSE_MIGRATION)&&!updateMayCreate('index.php')&&!updateMayCreate('oauth.php'),'new file allowlist');
bridgeCheck(count(updateMigrationStatements('CREATE TABLE IF NOT EXISTS operating_expenses (id INT);CREATE TABLE IF NOT EXISTS operating_expense_revisions (id INT);'))===2,'approved additive statements');
foreach(['DROP TABLE users;','CREATE TABLE IF NOT EXISTS operating_expenses (id INT);DELETE FROM users;','CREATE TABLE IF NOT EXISTS operating_expenses (id INT);CREATE TABLE IF NOT EXISTS operating_expense_revisions (id INT);DROP TABLE users;'] as $bad){$rejected=false;try{updateMigrationStatements($bad);}catch(RuntimeException){$rejected=true;}bridgeCheck($rejected,'reject unexpected SQL');}
$m=json_decode(file_get_contents($root.'/updates/channel.json'),true,512,JSON_THROW_ON_ERROR);
foreach($m['files'] as $f)bridgeCheck(hash_file('sha256',$root.'/'.$f['path'])===$f['sha256'],'manifest '.$f['path']);
bridgeCheck(strpos($src,'DeveloperBackup633::backup()')<strpos($src,'foreach($parts as $statement)$pdo->exec'),'backup before DDL');
bridgeCheck(str_contains($src,"Auth::requireDeveloper()")&&str_contains($src,'csrf_verify()'),'developer and CSRF gates');
