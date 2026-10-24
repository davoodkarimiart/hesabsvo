<?php
/** Functional no-MySQL test for Auth on a fake PDO driver. No production data. */
declare(strict_types=1);
ini_set('session.use_cookies','0');ob_start();session_start();$_SESSION=[];
class FakePdo629 extends PDO {
  public array $users=[],$attempts=[],$codes=[];
  public bool $transaction=false;
  public function __construct(){ $this->users[1]=['id'=>1,'username'=>'tester','password_hash'=>password_hash('Old-Strong-Pass!123',PASSWORD_DEFAULT),'role'=>'admin','active'=>1,'security_version'=>0]; }
  public function prepare(string $query,array $options=[]):PDOStatement|false{return new FakeStatement629($this,$query);}
  public function beginTransaction():bool{$this->transaction=true;return true;}
  public function inTransaction():bool{return $this->transaction;}
  public function commit():bool{$this->transaction=false;return true;}
  public function rollBack():bool{$this->transaction=false;return true;}
}
class FakeStatement629 extends PDOStatement {
  private array $params=[];private mixed $result=null;
  public function __construct(private FakePdo629 $db, private string $sql){}
  public function execute(?array $params=null):bool{
    $p=$params??[];$this->params=$p;$s=$this->sql;
    if(str_contains($s,'COUNT(*) FROM login_attempts')){
      $this->result=count(array_filter($this->db->attempts,fn($a)=>(!str_contains($s,'success=0') || !$a['success']) && $a[str_contains($s,'username=?')?'username':'ip_hash']===$p[0]));
    }elseif(str_contains($s,'FROM users WHERE username=?')){
      $this->result=null;foreach($this->db->users as $u)if($u['username']===$p[0] && (!str_contains($s,'active=1')||$u['active']))$this->result=$u;
      if(str_starts_with($s,'SELECT id FROM users'))$this->result=$this->result['id']??false;
    }elseif(str_contains($s,'FROM users WHERE id=?')){$this->result=$this->db->users[$p[0]]??false;
    }elseif(str_contains($s,'INSERT INTO login_attempts')){$this->db->attempts[]=['username'=>$p[0],'ip_hash'=>$p[1],'success'=>$p[2]];
    }elseif(str_contains($s,'UPDATE users SET password_hash=')){$this->db->users[$p[1]]['password_hash']=$p[0];$this->db->users[$p[1]]['security_version']++;
    }elseif(str_contains($s,'DELETE FROM user_recovery_codes')){$this->db->codes=array_values(array_filter($this->db->codes,fn($x)=>$x['uid']!==$p[0]));
    }elseif(str_contains($s,'INSERT INTO user_recovery_codes')){$this->db->codes[]=['id'=>count($this->db->codes)+1,'uid'=>$p[0],'hash'=>$p[1]];
    }elseif(str_contains($s,'FROM user_recovery_codes')){$this->result=false;foreach($this->db->codes as $code)if($code['uid']===$p[0]&&$code['hash']===$p[1]){$this->result=$code['id'];break;}
    }
    return true;
  }
  public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->result ?: false;}
  public function fetchColumn(int $column=0):mixed{return is_array($this->result)?reset($this->result):($this->result??false);}
}
class Database{public static FakePdo629 $db;public static function connection():PDO{return self::$db;}}
function base_path(string $p=''):string{return __DIR__;}
function ip_hash():string{return str_repeat('a',64);}
function app_secret():string{return str_repeat('x',64);}
class Logger{public static function audit(string $event,array $a=[]):void{}public static function error(string $event,array $a=[]):void{}}
function redirect(string $p):never{throw new RuntimeException('redirect: '.$p);}
require dirname(__DIR__).'/app/Auth.php';
function ok629($yes,$msg){if(!$yes)throw new RuntimeException('FAIL '.$msg);echo "[OK] $msg\n";}
Database::$db=new FakePdo629();
$pdo=Database::$db;
ok629(!Auth::attempt('tester','wrong'),'bad login rejected');
ok629(Auth::attempt('tester','Old-Strong-Pass!123'),'valid login');
ok629(Auth::check() && Auth::user()['role']==='admin','session state authenticated');
Auth::changePassword(1,'Old-Strong-Pass!123','New-Secure-Passphrase-2026!','New-Secure-Passphrase-2026!');
ok629($pdo->users[1]['security_version']===1,'password change increments session version');
ok629($_SESSION['_security_version']===1,'current session stays valid');
ok629(!password_verify('Old-Strong-Pass!123',$pdo->users[1]['password_hash']),'old password revoked');
$codes=Auth::generateRecoveryCodes(1,'New-Secure-Passphrase-2026!');
ok629(count($codes)===8&&count(array_unique($codes))===8&&count($pdo->codes)===8,'eight unique recovery codes');
ok629(!Auth::recoverWithCode('tester','WRONG-CODE-STRING','Third-Secure-Passphrase-2026!','Third-Secure-Passphrase-2026!'),'wrong recovery code fails');
ok629(count($pdo->codes)===8,'wrong code does not erase valid codes');
ok629(Auth::recoverWithCode('tester',$codes[0],'Third-Secure-Passphrase-2026!','Third-Secure-Passphrase-2026!'),'valid code recovers password');
ok629(count($pdo->codes)===0&&$pdo->users[1]['security_version']===2,'all recovery codes and prior sessions revoked');
$rp=new ReflectionProperty(Auth::class,'verified');$rp->setValue(null);
ok629(!Auth::check(),'prior session invalidated after recovery');
$_SESSION=[];
ok629(!Auth::recoverWithCode('tester',$codes[0],'Fourth-Secure-Passphrase-2026!','Fourth-Secure-Passphrase-2026!'),'recovery code cannot be reused');
$pdo->attempts=[];for($i=0;$i<8;$i++)ok629(!Auth::attempt('tester','incorrect'.$i),'bad attempt '.($i+1));
try{Auth::attempt('tester','Third-Secure-Passphrase-2026!');throw new RuntimeException('threshold bypassed');}catch(RuntimeException $e){ok629(str_contains($e->getMessage(),'۱۵ دقیقه'),'eight failures block even valid credential');}

// v0.6.30: even a bot rotating fake usernames is rate-limited by all-attempt per-IP burst cap.
$pdo->attempts=[];
for($i=0;$i<10;$i++)ok629(!Auth::attempt('no-such-user-'.$i,'invalid!'),'rotating user '.$i.' recorded');
try {Auth::attempt('eleventh-user','invalid!');throw new RuntimeException('IP burst limit bypassed');}
catch(RuntimeException $e){ok629(str_contains($e->getMessage(),'۱۵ دقیقه'),'10 tries in burst block rotating usernames');}
// Successful logins are also counted; otherwise bot could rotate known credentials.
$pdo->attempts=[];
for($i=0;$i<10;$i++)ok629(Auth::attempt('tester','Third-Secure-Passphrase-2026!'),'valid IP try '.$i);
try {Auth::attempt('tester','Third-Secure-Passphrase-2026!');throw new RuntimeException('successful-attempt cap bypassed');}
catch(RuntimeException $e){ok629(str_contains($e->getMessage(),'۱۵ دقیقه'),'10 successful requests also trip burst cap');}
