<?php

declare(strict_types=1);

final class AccountSettingsService {
    public static function import(int $panelId,array $file,string $missingAction='keep',bool $forceOverlap=false,?array $parsedRows=null): array {
        $panel=CompanyService::panel($panelId); if(!$panel)throw new RuntimeException('پنل معتبر نیست.');
        self::validateUpload($file);
        $tmp=(string)$file['tmp_name'];
        $rows=$parsedRows ?: XlsxReader::rows($tmp,(string)($file['name']??''));
        // Username is mandatory. Name/UUID columns are resolved case-insensitively with aliases below.
        $objects=XlsxReader::objects($rows,['Username']);
        $incoming=[];
        foreach($objects as $o){
            $u=trim(self::field($o,['Username','User Name','UserName','Login','Member Username'])); if($u==='')continue;
            $first=trim(self::field($o,['First name','First Name','Firstname','FirstName','First']));
            $last=trim(self::field($o,['Last name','Last Name','Lastname','LastName','Last','Surname','Family name','Family Name']));
            $full=trim(self::field($o,['Full name','Full Name','FullName','Name','Member Name','Customer Name']));
            $uuid=trim(self::field($o,['UUID','Uuid','uuid','User ID','User Id','UserID','UserId','ID']));
            $original=trim($first.' '.$last);
            if($original==='')$original=$full;
            $incoming[$u]=['username'=>$u,'uuid'=>$uuid!==''?$uuid:null,'first'=>$first,'last'=>$last,'original'=>$original,'payload'=>$o];
        }
        if(!$incoming)throw new RuntimeException('هیچ Username قابل استفاده‌ای در AccountSettings پیدا نشد.');
        $overlap=self::foreignOverlap($panelId,array_keys($incoming));
        if($overlap && $overlap['ratio']>.5 && !$forceOverlap){
            self::recordBlocked($panelId,$file,$overlap);
            throw new RuntimeException('هشدار جدی: '.round($overlap['ratio']*100).'٪ Usernameهای این فایل با پنل «'.$overlap['company'].' / '.$overlap['panel'].'» همپوشانی دارند. اگر مطمئنی فایل برای همین پنل است، تیک «ادامه با وجود هشدار» را بزن و دوباره Import کن.');
        }
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $st=$pdo->prepare('SELECT * FROM accounts WHERE panel_id=?');$st->execute([$panelId]);$existing=[];foreach($st->fetchAll() as $a)$existing[$a['source_username']]=$a;
            $added=$changed=$reactivated=$nameRepaired=0;
            foreach($incoming as $u=>$a){
                $sourceOriginal=$a['original']!==''?$a['original']:$u;
                $autoDisplay=NameHelper::displayName($a['first'],$a['last'],$sourceOriginal);
                if($autoDisplay==='')$autoDisplay=$sourceOriginal;
                if(!isset($existing[$u])){
                    $ins=$pdo->prepare('INSERT INTO accounts(panel_id,source_username,source_uuid,first_name,last_name,original_name,display_name,rate_type,active,manual,source_payload_json,first_seen_at,last_seen_at) VALUES(?,?,?,?,?,?,?,\'general\',1,0,?,NOW(),NOW())');
                    $ins->execute([$panelId,$u,$a['uuid'],$a['first'],$a['last'],$sourceOriginal,$autoDisplay,json_encode($a['payload'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$added++;
                }else{
                    $old=$existing[$u];
                    $sourceChanged=((string)$old['original_name']!==$sourceOriginal)||((string)($old['source_uuid']??'')!==(string)($a['uuid']??''));
                    $wasInactive=(int)$old['active']===0;
                    $oldDisplay=trim((string)($old['display_name']??''));
                    $oldOriginal=trim((string)($old['original_name']??''));
                    $shouldRepairDisplay=$oldDisplay==='' || $oldDisplay===$u || ($oldOriginal===$u && $oldDisplay===$oldOriginal);
                    if($shouldRepairDisplay){
                        $up=$pdo->prepare('UPDATE accounts SET source_uuid=?,first_name=?,last_name=?,original_name=?,display_name=?,active=1,source_payload_json=?,last_seen_at=NOW() WHERE id=?');
                        $up->execute([$a['uuid'],$a['first'],$a['last'],$sourceOriginal,$autoDisplay,json_encode($a['payload'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$old['id']]);
                        if($autoDisplay!==$oldDisplay)$nameRepaired++;
                    }else{
                        // Keep a user's manual display-name override intact.
                        $up=$pdo->prepare('UPDATE accounts SET source_uuid=?,first_name=?,last_name=?,original_name=?,active=1,source_payload_json=?,last_seen_at=NOW() WHERE id=?');
                        $up->execute([$a['uuid'],$a['first'],$a['last'],$sourceOriginal,json_encode($a['payload'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$old['id']]);
                    }
                    if($sourceChanged)$changed++; if($wasInactive)$reactivated++;
                }
            }
            $incomingSet=array_fill_keys(array_keys($incoming),true);$missing=[];
            foreach($existing as $u=>$a)if((int)$a['active']===1&&!isset($incomingSet[$u]))$missing[]=$a;
            if($missingAction==='deactivate'&&$missing){$ids=array_column($missing,'id');$marks=implode(',',array_fill(0,count($ids),'?'));$pdo->prepare("UPDATE accounts SET active=0 WHERE id IN ($marks)")->execute($ids);}
            $hash=hash_file('sha256',$tmp)?:str_repeat('0',64);
            $stats=['total'=>count($incoming),'added'=>$added,'changed'=>$changed,'missing'=>count($missing),'reactivated'=>$reactivated,'name_repaired'=>$nameRepaired,'missing_action'=>$missingAction,'overlap'=>$overlap];
            $imp=$pdo->prepare("INSERT INTO account_settings_imports(panel_id,source_filename,source_hash,status,total_rows,added_rows,changed_rows,missing_rows,reactivated_rows,stats_json,imported_by) VALUES(?,?,?,'completed',?,?,?,?,?,?,?)");
            $imp->execute([$panelId,self::safeName((string)$file['name']),$hash,count($incoming),$added,$changed,count($missing),$reactivated,json_encode($stats,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),Auth::user()['id']??null]);
            $pdo->commit();
            Logger::audit('account_settings.synced',['panel_id'=>$panelId]+$stats);
            Logger::system('account_settings.import_completed',['panel_id'=>$panelId,'file'=>self::safeName((string)$file['name'])]+$stats);
            return $stats;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Logger::error('account_settings.import_failed',['panel_id'=>$panelId,'error'=>$e->getMessage()]);throw $e;}
    }

    public static function imports(int $panelId): array {$st=Database::connection()->prepare('SELECT * FROM account_settings_imports WHERE panel_id=? ORDER BY id DESC LIMIT 20');$st->execute([$panelId]);return $st->fetchAll();}

    public static function deleteImport(int $panelId,int $importId): void {
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT id,source_filename,status FROM account_settings_imports WHERE id=? AND panel_id=?');
        $st->execute([$importId,$panelId]);$row=$st->fetch();
        if(!$row)throw new RuntimeException('سابقه Sync پیدا نشد.');
        $pdo->prepare('DELETE FROM account_settings_imports WHERE id=? AND panel_id=?')->execute([$importId,$panelId]);
        Logger::audit('account_settings.import_history_deleted',['panel_id'=>$panelId,'import_id'=>$importId,'file'=>$row['source_filename'],'status'=>$row['status']]);
        Logger::system('account_settings.import_history_deleted',['panel_id'=>$panelId,'import_id'=>$importId]);
    }

    public static function deleteAccount(int $panelId,int $accountId): void {
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT id,source_username FROM accounts WHERE id=? AND panel_id=?');
        $st->execute([$accountId,$panelId]);$account=$st->fetch();
        if(!$account)throw new RuntimeException('Account پیدا نشد.');
        $refs=self::reportReferenceCount($accountId);
        if($refs>0)throw new RuntimeException('این Account در گزارش مالی استفاده شده و حذف کامل آن مجاز نیست. آن را غیرفعال کن تا تاریخچه مالی سالم بماند.');
        $pdo->prepare('DELETE FROM accounts WHERE id=? AND panel_id=?')->execute([$accountId,$panelId]);
        Logger::audit('account.deleted',['panel_id'=>$panelId,'account_id'=>$accountId,'username'=>$account['source_username']]);
        Logger::system('account.deleted',['panel_id'=>$panelId,'account_id'=>$accountId]);
    }

    public static function resetPanelSource(int $panelId): array {
        $panel=CompanyService::panel($panelId);
        if(!$panel)throw new RuntimeException('پنل معتبر نیست.');
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT id,source_username FROM accounts WHERE panel_id=?');$st->execute([$panelId]);$accounts=$st->fetchAll();
        $blocked=[];
        foreach($accounts as $a){if(self::reportReferenceCount((int)$a['id'])>0)$blocked[]=(string)$a['source_username'];}
        if($blocked){
            $sample=implode('، ',array_slice($blocked,0,5));
            throw new RuntimeException('پاکسازی کامل متوقف شد چون '.count($blocked).' Account در گزارش مالی استفاده شده‌اند'.($sample!==''?'؛ نمونه: '.$sample:'').'. داده مالی حذف نمی‌شود.');
        }
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT COUNT(*) FROM accounts WHERE panel_id=?');$q->execute([$panelId]);$countAccounts=(int)$q->fetchColumn();
            $q=$pdo->prepare('SELECT COUNT(*) FROM account_settings_imports WHERE panel_id=?');$q->execute([$panelId]);$countImports=(int)$q->fetchColumn();
            $pdo->prepare('DELETE FROM accounts WHERE panel_id=?')->execute([$panelId]);
            $pdo->prepare('DELETE FROM account_settings_imports WHERE panel_id=?')->execute([$panelId]);
            $pdo->commit();
            Logger::audit('account_settings.panel_source_reset',['panel_id'=>$panelId,'accounts_deleted'=>$countAccounts,'imports_deleted'=>$countImports]);
            Logger::system('account_settings.panel_source_reset',['panel_id'=>$panelId,'accounts_deleted'=>$countAccounts,'imports_deleted'=>$countImports]);
            return ['accounts_deleted'=>$countAccounts,'imports_deleted'=>$countImports];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function reportReferenceCount(int $accountId): int {
        // Only active/non-deleted reports block AccountSettings cleanup.
        // Deleted reports keep immutable row/name/rate snapshots, and the FK is ON DELETE SET NULL,
        // so removing an Account does not destroy financial history.
        try{
            $st=Database::connection()->prepare("SELECT COUNT(*) FROM daily_report_rows rr JOIN daily_reports r ON r.id=rr.daily_report_id WHERE rr.account_id=? AND COALESCE(r.status,'final')<>'deleted'");
            $st->execute([$accountId]);
            return (int)$st->fetchColumn();
        } catch(Throwable){return 0;}
    }

    private static function field(array $row,array $aliases): string {
        $map=[];
        foreach($row as $key=>$value)$map[self::normKey((string)$key)]=$value;
        foreach($aliases as $alias){$k=self::normKey($alias);if(array_key_exists($k,$map)&&$map[$k]!==null)return (string)$map[$k];}
        return '';
    }
    private static function normKey(string $value): string {
        $v=mb_strtolower(trim($value),'UTF-8');
        $v=preg_replace('/[\s_\-\.]+/u','',$v)??$v;
        return $v;
    }

    private static function foreignOverlap(int $targetPanelId,array $usernames): ?array {
        if(!$usernames)return null;$pdo=Database::connection();$marks=implode(',',array_fill(0,count($usernames),'?'));
        $params=$usernames;$params[]=$targetPanelId;
        $sql="SELECT p.id panel_id,p.name panel,c.name company,COUNT(*) hits FROM accounts a JOIN panels p ON p.id=a.panel_id JOIN companies c ON c.id=p.company_id WHERE a.source_username IN ($marks) AND p.id<>? GROUP BY p.id,p.name,c.name ORDER BY hits DESC LIMIT 1";
        $st=$pdo->prepare($sql);$st->execute($params);$x=$st->fetch();if(!$x)return null;$x['ratio']=(int)$x['hits']/max(1,count($usernames));return $x;
    }
    private static function validateUpload(array $f): void {if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('آپلود فایل ناموفق بود.');if((int)($f['size']??0)>15*1024*1024)throw new RuntimeException('فایل AccountSettings بیش از ۱۵MB است.');$ext=strtolower(pathinfo((string)($f['name']??''),PATHINFO_EXTENSION));if(!in_array($ext,['xlsx','xls'],true))throw new RuntimeException('فقط XLSX/XLS مجاز است.');}
    private static function safeName(string $n): string {return mb_substr(basename(str_replace('\\','/',$n)),0,250,'UTF-8');}
    private static function recordBlocked(int $panelId,array $file,array $overlap): void {try{$tmp=(string)$file['tmp_name'];$hash=is_file($tmp)?(hash_file('sha256',$tmp)?:str_repeat('0',64)):str_repeat('0',64);$st=Database::connection()->prepare("INSERT INTO account_settings_imports(panel_id,source_filename,source_hash,status,total_rows,stats_json,imported_by,error_message) VALUES(?,?,?,'blocked',0,?,?,?)");$st->execute([$panelId,self::safeName((string)$file['name']),$hash,json_encode(['overlap'=>$overlap],JSON_UNESCAPED_UNICODE),Auth::user()['id']??null,'probable_wrong_panel']);Logger::system('account_settings.import_blocked',['panel_id'=>$panelId,'overlap'=>$overlap]);}catch(Throwable){}
    }
}
