<?php
declare(strict_types=1);
$root=dirname(__DIR__);$src=file_get_contents($root.'/update_center.php');
$start=strpos($src,'const UPDATE_CLIENT=');$end=strpos($src,"if(defined('SOLTAN_UPDATE_LIBRARY_ONLY')", $start);
if($start===false||$end===false)throw new RuntimeException('update functions not found');
eval(substr($src,$start,$end-$start));
function bridgeCheck($value,string $label):void{if(!$value)throw new RuntimeException('FAIL '.$label);echo '[OK] '.$label.PHP_EOL;}
bridgeCheck(UPDATE_CLIENT==='1.5'&&UPDATE_MAX_FILES===40,'updater 1.5 capacity');
bridgeCheck(updateAllowedPath(UPDATE_EXPENSE_MIGRATION)&&!updateAllowedPath('database/migrations/evil.sql')&&!updateAllowedPath('../config/config.php'),'migration allowlist');
bridgeCheck(updateAllowedPath('mcp.php')&&updateAllowedPath('developer_diagnostics.php')&&updateAllowedPath('developer_backups.php')&&updateAllowedPath('app/Logger.php')&&updateAllowedPath('app/bootstrap.php')&&updateAllowedPath('oauth.php')&&!updateAllowedPath('cron_soltan_backup.php')&&!updateAllowedPath('config/config.php'),'P14 controlled developer paths');
bridgeCheck(!updateMayCreate('mcp.php')&&!updateMayCreate('app/bootstrap.php'),'existing security-sensitive files only');
bridgeCheck(updateMayCreate(UPDATE_EXPENSE_MIGRATION)&&!updateMayCreate('index.php')&&!updateMayCreate('oauth.php'),'new file allowlist');
bridgeCheck(count(updateMigrationStatements('CREATE TABLE IF NOT EXISTS operating_expenses (id INT);CREATE TABLE IF NOT EXISTS operating_expense_revisions (id INT);'))===2,'approved additive statements');
foreach(['DROP TABLE users;','CREATE TABLE IF NOT EXISTS operating_expenses (id INT);DELETE FROM users;','CREATE TABLE IF NOT EXISTS operating_expenses (id INT);CREATE TABLE IF NOT EXISTS operating_expense_revisions (id INT);DROP TABLE users;'] as $bad){$rejected=false;try{updateMigrationStatements($bad);}catch(RuntimeException){$rejected=true;}bridgeCheck($rejected,'reject unexpected SQL');}
$m=json_decode(file_get_contents($root.'/updates/channel.json'),true,512,JSON_THROW_ON_ERROR);
foreach($m['files'] as $f)bridgeCheck(hash_file('sha256',$root.'/'.$f['path'])===$f['sha256'],'manifest '.$f['path']);
bridgeCheck(strpos($src,'DeveloperBackup633::backup()')<strpos($src,'foreach($parts as $statement)$pdo->exec'),'backup before DDL');
bridgeCheck(str_contains($src,"Auth::requireDeveloper()")&&str_contains($src,'csrf_verify()'),'developer and CSRF gates');

bridgeCheck(str_contains($src,'function updateApply(')&&str_contains($src,'updateApply($m')&&str_contains($src,'function updateInstallRemote('),'P19 shared update engine');
bridgeCheck(str_contains($src,"Live GitHub release verification failed")&&str_contains($src,'Checksum mismatch')&&str_contains($src,'Database backup failed'),'P19 pinned update with database backup');
function config_get($key,$default=null){return 'https://example.invalid';}
require $root.'/mcp_oauth_lib.php';
bridgeCheck(mo_scopes('admin:maintenance admin:update','developer')===['admin:maintenance','admin:update'],'P19 developer explicit scopes');
foreach(['admin','user'] as $role){$rejected=false;try{mo_scopes('admin:maintenance admin:update',$role);}catch(RuntimeException){$rejected=true;}bridgeCheck($rejected,'P19 scope denied '.$role);}
bridgeCheck(!str_contains(mo_default_scopes('developer'),'admin:update'),'P19 no implicit write elevation');
