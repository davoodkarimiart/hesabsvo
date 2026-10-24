<?php

declare(strict_types=1);
/** Optional Cloudflare Turnstile protection. Enforced on both login and password-recovery POST. */
final class LoginGuard630 {
    public static function siteKey(): string { return trim((string)config_get('security.turnstile_site_key','')); }
    private static function secretKey(): string { return trim((string)config_get('security.turnstile_secret_key','')); }
    public static function enabled(): bool { return self::siteKey()!=='' && self::secretKey()!==''; }
    public static function verify(string $token,string $action): void {
        $site=self::siteKey();$secret=self::secretKey();
        if ($site==='' && $secret==='') return; // Explicit opt-in only; rate limits always run regardless.
        if ($site==='' || $secret==='') throw new RuntimeException('تنظیم محافظ ضدربات روی سرور ناقص است؛ مدیر سایت باید کلیدها را تنظیم کند.');
        if ($token==='' || strlen($token)>2048) throw new RuntimeException('تأیید امنیتی انجام نشد. لطفاً چالش ضدربات را تکمیل کنید.');
        $payload=['secret'=>$secret,'response'=>$token,'remoteip'=>request_ip()];
        $url='https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $body=http_build_query($payload,'','&',PHP_QUERY_RFC3986);
        $response=false;
        try {
            if (function_exists('curl_init')) {
                $handle=curl_init($url);
                if (!$handle) throw new RuntimeException('curl init');
                curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>true]);
                $response=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);curl_close($handle);
                if($status!==200)$response=false;
            } else {
                $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>$body,'timeout'=>6,'ignore_errors'=>false]]);
                $response=@file_get_contents($url,false,$ctx);
            }
        } catch(Throwable $e) { $response=false; }
        $data=is_string($response)?json_decode($response,true):null;
        $actualHost=strtolower(trim((string)($_SERVER['HTTP_HOST']??'')));
        $actualHost=explode(':',$actualHost)[0];
        $success=is_array($data) && ($data['success']??false)===true && ($data['action']??'')===$action;
        if ($success && $actualHost!=='') $success=hash_equals($actualHost,strtolower((string)($data['hostname']??'')));
        if (!$success) {
            Logger::audit('auth.bot_challenge_failed',['action'=>$action,'host'=>$actualHost]);
            throw new RuntimeException('تأیید امنیتی موفق نبود. چالش را تازه‌سازی و دوباره تلاش کنید.');
        }
    }
}
