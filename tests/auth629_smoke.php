<?php
/** DB-independent regression test for authentication rules and PHP code shape. */
declare(strict_types=1);
require dirname(__DIR__) . '/app/Auth.php';
function ensure(bool $value,string $label): void {if(!$value)throw new RuntimeException('FAIL '.$label);echo "[OK] $label\n";}
foreach(['short','test1234567',''] as $p){try{Auth::validateNewPassword($p);throw new RuntimeException('short password accepted');}catch(RuntimeException $e){ensure(str_contains($e->getMessage(),'حداقل'),'short password rejected');}}
Auth::validateNewPassword('Correct Horse Battery Staple 2026!');
ensure(true,'valid passphrase accepted');
try{Auth::validateNewPassword(str_repeat('x',73));throw new RuntimeException('long password accepted');}catch(RuntimeException $e){ensure(str_contains($e->getMessage(),'حداکثر'),'bcrypt max bytes enforced');}
$source=file_get_contents(dirname(__DIR__).'/app/Auth.php');
foreach(['security_version','session_regenerate_id(true)','hash_hmac','FOR UPDATE','DELETE FROM user_recovery_codes','guardAttempts','password_verify','$network>=25','$account>=8'] as $term)ensure(str_contains($source,$term),'auth guard: '.$term);
$idx=file_get_contents(dirname(__DIR__).'/index.php');
foreach(['page===\'recover\'','change_my_password','generate_my_recovery_codes','account-security','_recovery_codes_once','security629.css'] as $term)ensure(str_contains($idx,$term),'UI wiring: '.$term);
$ddl=file_get_contents(dirname(__DIR__).'/database/migrations/016_auth_hardening.sql');
foreach(['security_version','user_recovery_codes','idx_login_attempt_ip_time'] as $term)ensure(str_contains($ddl,$term),'migration: '.$term);
