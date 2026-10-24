<?php

declare(strict_types=1);

/** v0.6.29: login throttling, session revocation, password self-service, one-time recovery codes. */
final class Auth {
    private static ?bool $verified = null;
    public static function user(): ?array { return self::check() ? ($_SESSION['user'] ?? null) : null; }
    public static function check(): bool {
        if (self::$verified !== null) return self::$verified;
        $u = $_SESSION['user'] ?? null;
        if (!is_array($u) || !isset($u['id']) || !isset($_SESSION['_security_version'])) return self::$verified = false;
        try {
            $q=Database::connection()->prepare('SELECT active,role,security_version FROM users WHERE id=? LIMIT 1');
            $q->execute([(int)$u['id']]); $db=$q->fetch(PDO::FETCH_ASSOC);
            $ok = is_array($db) && (int)$db['active']===1 && (int)$db['security_version']===(int)$_SESSION['_security_version'] && $db['role']===$u['role'];
            if (!$ok) unset($_SESSION['user'],$_SESSION['_security_version']);
            return self::$verified = $ok;
        } catch (Throwable $e) {
            Logger::error('auth.session_validation_failed',['type'=>get_class($e)]);
            return self::$verified = false;
        }
    }
    public static function requireLogin(): void { if (!self::check()) redirect('index.php?page=login'); }
    public static function requireDeveloper(): void {
        self::requireLogin();
        if ((self::user()['role'] ?? '') !== 'developer') {http_response_code(403);exit('دسترسی Developer لازم است.');}
    }
    public static function attempt(string $username,string $password): bool {
        $username=self::cleanUsername($username);
        $pdo=Database::connection(); self::guardAttempts($pdo,$username);
        $q=$pdo->prepare('SELECT id,username,password_hash,role,active,security_version FROM users WHERE username=? LIMIT 1');
        $q->execute([$username]); $u=$q->fetch(PDO::FETCH_ASSOC);
        $ok=is_array($u) && (int)$u['active']===1 && password_verify($password,(string)$u['password_hash']);
        self::recordAttempt($pdo,$username,$ok);
        if (!$ok) {Logger::audit('auth.failed',['username'=>$username]);return false;}
        session_regenerate_id(true);
        $_SESSION['_csrf']=bin2hex(random_bytes(24));
        $_SESSION['user']=['id'=>(int)$u['id'],'username'=>$u['username'],'role'=>$u['role']];
        $_SESSION['_security_version']=(int)$u['security_version'];
        self::$verified=true;
        $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);
        Logger::audit('auth.login',['username'=>$username]);return true;
    }
    public static function logout(): void {
        if (isset($_SESSION['user'])) Logger::audit('auth.logout',['user_id'=>$_SESSION['user']['id']]);
        $_SESSION=[];
        if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
        session_destroy();self::$verified=false;
    }
    private static function cleanUsername(string $username): string {return substr(trim($username),0,100);}
    private static function guardAttempts(PDO $pdo,string $username): void {
        // Username-wide limit stops attackers rotating IPs; global IP limit stops username spraying.
        $ip=ip_hash();
        // Cleanup belongs in a scheduled maintenance job, not the hot login path.
        $q=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE username=? AND success=0 AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)');
        $q->execute([$username]);$account=(int)$q->fetchColumn();
        $q=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND success=0 AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)');
        $q->execute([$ip]);$network=(int)$q->fetchColumn();
        // Burst controls also count attempts with rotated user names, including successful logins.
        // Account threshold remains for attacks using many IP addresses against one account.
        $q=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND attempted_at >= (NOW() - INTERVAL 60 SECOND)');
        $q->execute([$ip]);$burst=(int)$q->fetchColumn();
        $q=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)');
        $q->execute([$ip]);$volume=(int)$q->fetchColumn();
        if($account>=8||$network>=25||$burst>=10||$volume>=40){Logger::audit('auth.rate_limited',['account_hash'=>hash('sha256',$username),'reason'=>$burst>=10?'burst':($volume>=40?'volume':'failures')]);throw new RuntimeException('تلاش‌های ورود بیش از حد مجاز است. ۱۵ دقیقه بعد دوباره امتحان کنید.');}
    }
    private static function recordAttempt(PDO $pdo,string $username,bool $ok): void {
        $q=$pdo->prepare('INSERT INTO login_attempts(username,ip_hash,success,attempted_at) VALUES(?,?,?,NOW())');
        $q->execute([$username,ip_hash(),$ok?1:0]);
    }
    public static function validateNewPassword(string $password): void {
        $len=strlen($password);
        if($len<12||$len>72)throw new RuntimeException('رمز جدید باید حداقل ۱۲ و حداکثر ۷۲ بایت باشد؛ از یک عبارت طولانی و منحصربه‌فرد استفاده کنید.');
    }
    private static function verifiedPassword(PDO $pdo,int $id,string $password): array {
        $q=$pdo->prepare('SELECT id,password_hash,username,security_version FROM users WHERE id=? AND active=1 LIMIT 1');
        $q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($u))throw new RuntimeException('حساب کاربری فعال پیدا نشد.');
        self::guardAttempts($pdo,(string)$u['username']);
        $ok=password_verify($password,(string)$u['password_hash']);
        self::recordAttempt($pdo,(string)$u['username'],$ok);
        if(!$ok)throw new RuntimeException('رمز فعلی صحیح نیست.');
        return $u;
    }
    public static function changePassword(int $id,string $old,string $new,string $confirm): void {
        self::requireLogin(); if((int)(self::user()['id']??0)!==$id)throw new RuntimeException('دسترسی نامعتبر است.');
        if(!hash_equals($new,$confirm))throw new RuntimeException('تکرار رمز جدید مطابقت ندارد.');
        self::validateNewPassword($new);$pdo=Database::connection();$u=self::verifiedPassword($pdo,$id,$old);
        if(password_verify($new,$u['password_hash']))throw new RuntimeException('رمز جدید باید با رمز فعلی متفاوت باشد.');
        $q=$pdo->prepare('UPDATE users SET password_hash=?,security_version=security_version+1 WHERE id=?');
        $q->execute([password_hash($new,PASSWORD_DEFAULT),$id]);
        $_SESSION['_security_version']=(int)$u['security_version']+1;
        $_SESSION['_csrf']=bin2hex(random_bytes(24));session_regenerate_id(true);
        Logger::audit('auth.password_changed',['user_id'=>$id]);
    }
    public static function generateRecoveryCodes(int $id,string $password): array {
        self::requireLogin();if((int)(self::user()['id']??0)!==$id)throw new RuntimeException('دسترسی نامعتبر است.');
        $pdo=Database::connection();self::verifiedPassword($pdo,$id,$password);
        $codes=[];$pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM user_recovery_codes WHERE user_id=?')->execute([$id]);
            $q=$pdo->prepare('INSERT INTO user_recovery_codes(user_id,code_hash,created_at) VALUES(?,?,NOW())');
            for($i=0;$i<8;$i++){$s=strtoupper(bin2hex(random_bytes(16)));$codes[]=implode('-',str_split($s,8));$q->execute([$id,self::recoveryHash($s)]);}
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        Logger::audit('auth.recovery_codes_regenerated',['user_id'=>$id,'count'=>count($codes)]);
        return $codes;
    }
    private static function recoveryHash(string $value): string {return hash_hmac('sha256',$value,app_secret());}
    public static function recoverWithCode(string $username,string $code,string $new,string $confirm): bool {
        $username=self::cleanUsername($username);
        if(!hash_equals($new,$confirm))throw new RuntimeException('تکرار رمز مطابقت ندارد.');
        self::validateNewPassword($new);$pdo=Database::connection();self::guardAttempts($pdo,$username);
        $value=strtoupper(preg_replace('/[\s\-]/u','',$code)??'');
        $pdo->beginTransaction();$ok=false;$id=0;
        try{
            $q=$pdo->prepare('SELECT id FROM users WHERE username=? AND active=1 LIMIT 1 FOR UPDATE');$q->execute([$username]);$id=(int)($q->fetchColumn()?:0);
            if($id>0&&preg_match('/^[A-F0-9]{32}$/D',$value)){
                $q=$pdo->prepare('SELECT id FROM user_recovery_codes WHERE user_id=? AND code_hash=? LIMIT 1 FOR UPDATE');
                $q->execute([$id,self::recoveryHash($value)]);$ok=(bool)$q->fetchColumn();
            }
            if($ok){
                $pdo->prepare('UPDATE users SET password_hash=?,security_version=security_version+1 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$id]);
                // All previously generated codes are revoked after recovery.
                $pdo->prepare('DELETE FROM user_recovery_codes WHERE user_id=?')->execute([$id]);
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        self::recordAttempt($pdo,$username,$ok);
        Logger::audit($ok?'auth.recovered':'auth.recovery_failed',['username_hash'=>hash('sha256',$username)]);
        return $ok;
    }
}
