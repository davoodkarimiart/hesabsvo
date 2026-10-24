<?php
declare(strict_types=1);
// Standalone test: no real MySQL or Cloudflare request is made.
$cfg=[];
function config_get(string $key,mixed $default=null): mixed {global $cfg;return $cfg[$key]??$default;}
function request_ip():string{return '127.0.0.1';}
class Logger {public static array $events=[];public static function audit(string $msg,array $data=[]):void{self::$events[]=$msg;}}
require dirname(__DIR__).'/app/Support/LoginGuard630.php';
function need(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException('FAIL: '.$msg);echo "[OK] $msg\n";}
need(!LoginGuard630::enabled(),'Turnstile opt-in starts disabled');
LoginGuard630::verify('','login');need(true,'unconfigured Turnstile leaves server rate limits in control');
$cfg['security.turnstile_site_key']='public';
try{LoginGuard630::verify('','login');throw new RuntimeException('partial config accepted');}catch(RuntimeException $e){need(str_contains($e->getMessage(),'ناقص'),'partial config fails closed');}
$cfg['security.turnstile_secret_key']='secret';
need(LoginGuard630::enabled(),'both keys enable challenge');
try{LoginGuard630::verify('','login');throw new RuntimeException('missing token accepted');}catch(RuntimeException $e){need(str_contains($e->getMessage(),'چالش'),'missing token blocked');}
try{LoginGuard630::verify(str_repeat('a',2049),'recovery');throw new RuntimeException('oversize accepted');}catch(RuntimeException $e){need(str_contains($e->getMessage(),'چالش'),'oversized token blocked');}
$idx=file_get_contents(dirname(__DIR__).'/index.php');$auth=file_get_contents(dirname(__DIR__).'/app/Auth.php');
foreach(['data-settings-tab="security"','id="s630Accounting"','id="s630Security"','LoginGuard630::verify','cf-turnstile-response','assets/settings630.js'] as $t)need(str_contains($idx,$t),'UI and challenge wired: '.$t);
foreach(['$burst>=10','$volume>=40','INTERVAL 60 SECOND','INTERVAL 15 MINUTE'] as $t)need(str_contains($auth,$t),'backend limits: '.$t);
