<?php
declare(strict_types=1);
final class DeveloperBackup633 {
    private static function dir(): string { return base_path('storage/backups'); }
    public static function list(): array {
        $out=[];foreach(glob(self::dir().'/soltan-db-*.sql.gz')?:[] as $p){if(!is_file($p))continue;$out[]=['name'=>basename($p),'size'=>filesize($p),'time'=>filemtime($p)];}
        usort($out,fn($a,$b)=>$b['time']<=>$a['time']);return $out;
    }
    private static function settingsPath(int $uid=0):string { return self::dir().($uid>0?'/.telegram-admin-'.$uid.'.json':'/.telegram-settings.json'); }
    public static function settings(int $uid=0):array { $p=self::settingsPath($uid);$s=is_file($p)?json_decode((string)file_get_contents($p),true):[];return is_array($s)?$s:[]; }
    public static function saveSettings(string $token,string $chat,int $uid=0):void {
        if($token!=='' && !preg_match('/^\d{5,15}:[A-Za-z0-9_-]{25,}$/D',$token))throw new RuntimeException('فرمت Bot Token معتبر نیست.');
        if($chat!=='' && !preg_match('/^(-?\d{5,20}|@[A-Za-z0-9_]{5,})$/D',$chat))throw new RuntimeException('Chat ID نامعتبر است.');
        self::prepare();$old=self::settings($uid);if($token==='')$token=(string)($old['token']??'');
        $p=self::settingsPath($uid);$tmp=$p.'.tmp';if(file_put_contents($tmp,json_encode(['token'=>$token,'chat'=>$chat]),LOCK_EX)===false)throw new RuntimeException('ثبت تنظیمات ناموفق بود.');chmod($tmp,0600);if(!rename($tmp,$p))throw new RuntimeException('ثبت تنظیمات ناموفق بود.');
    }
    private static function prepare(): void {
        $d=self::dir();if(!is_dir($d)&&!mkdir($d,0700,true))throw new RuntimeException('ساخت مسیر بکاپ ناموفق بود.');
        if(!is_file($d.'/.htaccess'))file_put_contents($d.'/.htaccess',"Require all denied\nDeny from all\n");
        if(!is_file($d.'/index.html'))file_put_contents($d.'/index.html','');
    }
    public static function backup():array {
        self::prepare();$lock=fopen(self::dir().'/.backup.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('بکاپ دیگری در حال اجرا است.');
        $tmp=null;$gz=null;
        try {
            $pdo=Database::connection();$tables=$pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);$name='soltan-db-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql.gz';$tmp=self::dir().'/.'.$name.'.part';
            $gz=gzopen($tmp,'wb6');if(!$gz)throw new RuntimeException('نوشتن بکاپ ناموفق بود.');
            $write=static function(string $s)use(&$gz):void {$n=gzwrite($gz,$s);if($n!==strlen($s))throw new RuntimeException('فضای بکاپ کافی نیست یا نوشتن ناقص انجام شد.');};
            $write("-- SoltanHesab logical MySQL backup UTC ".gmdate('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            // Consistent snapshot for transactional InnoDB tables. MyISAM/DDL changes need maintenance window.
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            foreach($tables as $t){$table=(string)$t[0];$kind=strtoupper((string)($t[1]??'BASE TABLE'));$q='`'.str_replace('`','``',$table).'`';
                if($kind!=='BASE TABLE')continue; $schema=$pdo->query('SHOW CREATE TABLE '.$q)->fetch(PDO::FETCH_NUM);$write("\nDROP TABLE IF EXISTS $q;\n".$schema[1].";\n");
                $stmt=$pdo->query('SELECT * FROM '.$q);$count=0;
                while($row=$stmt->fetch(PDO::FETCH_ASSOC)){$cols=array_map(static fn($k)=>'`'.str_replace('`','``',$k).'`',array_keys($row));$vals=array_map(static fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row));$write('INSERT INTO '.$q.' ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n");++$count;}
                $stmt->closeCursor();
            }
            $pdo->commit();$write("\nSET FOREIGN_KEY_CHECKS=1;\n");$closed=gzclose($gz);$gz=null;if(!$closed)throw new RuntimeException('نهایی‌سازی فایل بکاپ ناموفق بود.');
            $dest=self::dir().'/'.$name;if(!rename($tmp,$dest))throw new RuntimeException('ذخیره بکاپ ناموفق بود.');chmod($dest,0600);
            Logger::system('backup.created',['file'=>$name,'bytes'=>filesize($dest)]);self::retain(14);return ['name'=>$name,'size'=>filesize($dest)];
        }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();Logger::error('backup.failed',['type'=>get_class($e),'error'=>$e->getMessage()]);throw $e;}
        finally {if($gz)gzclose($gz);if($tmp&&is_file($tmp))@unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function retain(int $days=14):void {self::prepare();foreach(self::list() as $v){if($v['time']<time()-$days*86400)@unlink(self::dir().'/'.$v['name']);}}
    public static function rotateLogs(int $limit=5242880,int $days=30):void {
        $d=base_path('storage/logs');foreach(['audit','system','error'] as $channel){$p=$d.'/'.$channel.'.log';if(!is_file($p)||filesize($p)<$limit)continue;
            $h=fopen($p,'c+');if(!$h)continue;try{if(!flock($h,LOCK_EX|LOCK_NB))continue;$size=fstat($h)['size'];if($size<$limit)continue;
                rewind($h);$out=$p.'.'.gmdate('Ymd-His').'.'.bin2hex(random_bytes(3)).'.gz';$g=gzopen($out,'wb6');if(!$g)continue;$ok=true;while(!feof($h)){$buf=fread($h,65536);if($buf===false){$ok=false;break;}if($buf==='')continue;$written=gzwrite($g,$buf);if($written!==strlen($buf)){$ok=false;break;}}$closed=gzclose($g);if(!$ok||!$closed){@unlink($out);continue;}if(!ftruncate($h,0))continue;fflush($h);
            }finally{flock($h,LOCK_UN);fclose($h);}}
        foreach(glob($d.'/*.log.*.gz')?:[] as $p){if(is_file($p)&&filemtime($p)<time()-$days*86400)@unlink($p);}
    }
    public static function sendTelegram(string $name,int $uid=0):void {
        if(!preg_match('/^soltan-db-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.sql\.gz$/D',$name))throw new RuntimeException('نام فایل نامعتبر است.');
        $s=self::settings($uid);if(empty($s['token'])||empty($s['chat']))throw new RuntimeException('تنظیمات تلگرام کامل نیست.');
        if(!function_exists('curl_init')||!class_exists('CURLFile'))throw new RuntimeException('افزونه PHP cURL لازم است.');
        $file=self::dir().'/'.$name;if(!is_file($file))throw new RuntimeException('فایل بکاپ یافت نشد.');
        $ch=curl_init('https://api.telegram.org/bot'.$s['token'].'/sendDocument');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['chat_id'=>$s['chat'],'document'=>new CURLFile($file,'application/gzip',$name)],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);
        $reply=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$data=is_string($reply)?json_decode($reply,true):null;
        if($status!==200||!is_array($data)||empty($data['ok'])){Logger::error('backup.telegram_failed',['http_status'=>$status,'network_error'=>substr($err,0,160)]);throw new RuntimeException('ارسال تلگرام ناموفق بود؛ بکاپ محلی محفوظ است.');}
        Logger::system('backup.telegram_sent',['file'=>$name]);
    }
    public static function download(string $name):void {
        if(!preg_match('/^soltan-db-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.sql\.gz$/D',$name))throw new RuntimeException('نام فایل نامعتبر است.');
        $p=self::dir().'/'.$name;if(!is_file($p))throw new RuntimeException('بکاپ موجود نیست.');
        header('Content-Type: application/gzip');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($p));header('Cache-Control: no-store');readfile($p);exit;
    }
}
