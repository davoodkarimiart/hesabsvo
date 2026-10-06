<?php

declare(strict_types=1);

final class AccountingService {
    public static function infrastructureStatus(): array {
        $tables=['daily_reports','daily_report_rows','report_revisions'];$pdo=Database::connection();$ok=[];
        foreach($tables as $t){$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$st->execute([$t]);$ok[$t]=(bool)$st->fetchColumn();}
        return $ok;
    }

    public static function semanticContract(): array {
        return [
            'loss'=>'member_win < 0 => gross = abs(member_win)*rate; commission = gross*pct/100; received = gross-commission',
            'win'=>'member_win > 0 => paid = member_win*rate; commission = 0',
            'net'=>'site_net = received - paid',
        ];
    }

    public static function companiesForEntry(): array {
        return Database::connection()->query("SELECT c.id,c.name,(SELECT COUNT(*) FROM accounts a JOIN panels p ON p.id=a.panel_id WHERE p.company_id=c.id AND a.active=1) account_count FROM companies c WHERE c.active=1 ORDER BY c.name")->fetchAll();
    }

    public static function panelsForCompany(int $companyId): array {
        $st=Database::connection()->prepare("SELECT id,name,display_name_mode FROM panels WHERE company_id=? AND active=1 ORDER BY name");
        $st->execute([$companyId]); return $st->fetchAll();
    }

    public static function recentReports(int $limit=10): array {
        $limit=max(1,min(50,$limit));
        return Database::connection()->query("SELECT r.*,c.name company_name,p.name panel_name FROM daily_reports r JOIN companies c ON c.id=r.company_id LEFT JOIN panels p ON p.id=r.panel_id WHERE r.status='final' ORDER BY r.report_date DESC,r.id DESC LIMIT {$limit}")->fetchAll();
    }

    public static function buildDraft(int $companyId,string $dateInput,string $generalRate,string $specialRate,string $lossPercent,array $file,?array $parsedRows,bool $allowDuplicate=false): array {
        self::validateUpload($file);
        $company=self::company($companyId);
        if(!$company) throw new RuntimeException('شرکت گزارش معتبر نیست.');
        $reportDate=self::normalizeBusinessDate($dateInput);
        $generalInput=self::decimal($generalRate,'نرخ عمومی',true);
        $specialInput=self::decimal($specialRate,'نرخ ویژه',true);
        $general=SettingsService::rateToCanonical($generalInput);
        $special=SettingsService::rateToCanonical($specialInput);
        $pct=self::decimal($lossPercent,'درصد مشتری',false);
        if($pct<0 || $pct>100) throw new RuntimeException('درصد مشتری باید بین ۰ تا ۱۰۰ باشد.');

        $tmp=(string)($file['tmp_name']??'');
        $rows=$parsedRows ?: XlsxReader::rows($tmp,(string)($file['name']??''));
        $objects=XlsxReader::objects($rows,['Username','Member Win']);
        $raw=[];$seen=[];
        foreach($objects as $o){
            $username=trim(self::field($o,['Username','User Name','UserName','Login','Member Username']));
            if($username==='' || preg_match('/^(grand\s*total|total)$/iu',$username)) continue;
            if(isset($seen[$username])) throw new RuntimeException('Username تکراری در Report_WL: '.$username);
            $seen[$username]=true;
            $mw=self::decimal(self::field($o,['Member Win','MemberWin','Member win']),'Member Win برای '.$username,false);
            $raw[]=['username'=>$username,'member_win'=>$mw,'source_payload'=>$o];
        }
        if(!$raw) throw new RuntimeException('Report_WL هیچ ردیف کاربری قابل استفاده‌ای ندارد.');

        $matches=self::accountMap(array_column($raw,'username'));
        $draftRows=[];$foreign=[];$unknown=[];
        foreach($raw as $r){
            $username=$r['username'];$all=$matches[$username]??[];
            $same=array_values(array_filter($all,fn($x)=>(int)$x['company_id']===$companyId));
            if(count($same)>1) throw new RuntimeException('Username «'.$username.'» در بیش از یک پنل همین شرکت وجود دارد و منبع ردیف مبهم است. ابتدا AccountSettingsها را اصلاح کن.');
            if(!$same && $all){$foreign[]=['username'=>$username,'companies'=>array_values(array_unique(array_column($all,'company_name')))];continue;}
            if($same){
                $a=$same[0];$rate=$a['rate_type']==='special'?$special:$general;
                $name=$a['display_name_mode']==='original'?$a['original_name']:($a['display_name']?:$a['original_name']);
                $draftRows[]=self::draftRow($username,$r['member_win'],$rate,$pct,[
                    'account_id'=>(int)$a['id'],'panel_id'=>(int)$a['panel_id'],'panel_name'=>$a['panel_name'],
                    'name'=>$name?:$username,'original_name'=>$a['original_name']?:$username,'display_name'=>$a['display_name']?:$a['original_name'],
                    'rate_type'=>$a['rate_type'],'row_origin'=>'import','register_manual'=>false,
                ]);
            }else{
                $unknown[]=$username;
                $draftRows[]=self::draftRow($username,$r['member_win'],$general,$pct,[
                    'account_id'=>null,'panel_id'=>null,'panel_name'=>null,'name'=>$username,'original_name'=>$username,'display_name'=>$username,
                    'rate_type'=>'general','row_origin'=>'temporary','register_manual'=>false,
                ]);
            }
        }
        if($foreign){
            $parts=[];foreach(array_slice($foreign,0,8) as $f)$parts[]=$f['username'].' → '.implode(' / ',$f['companies']);
            throw new RuntimeException('گزارش با شرکت انتخاب‌شده همخوان نیست. این Usernameها متعلق به شرکت دیگری هستند: '.implode('، ',$parts));
        }
        $knownPanelIds=array_values(array_unique(array_map(fn($r)=>(int)$r['panel_id'],array_filter($draftRows,fn($r)=>!empty($r['account_id'])&&!empty($r['panel_id'])))));
        if(count($knownPanelIds)>1) throw new RuntimeException('این Report_WL مربوط به بیش از یک پنل است. هر گزارش روزانه باید فقط متعلق به یک پنل باشد. فایل را تفکیک یا AccountSettingsها را اصلاح کن.');
        $inferredPanelId=$knownPanelIds[0]??null;
        $panelName=null;
        if($inferredPanelId){foreach(self::panelsForCompany($companyId) as $p){if((int)$p['id']===$inferredPanelId){$panelName=$p['name'];break;}}}
        foreach($draftRows as &$dr){
            if(!empty($dr['account_id'])){$dr['unknown_confirmed']=true;continue;}
            if($inferredPanelId){$dr['panel_id']=$inferredPanelId;$dr['panel_name']=$panelName;}
            $dr['unknown_confirmed']=false;
        }unset($dr);

        $sourceHash=is_file($tmp)?(hash_file('sha256',$tmp)?:str_repeat('0',64)):hash('sha256',json_encode($raw));
        $sourceStoragePath=self::storeSourceUpload($tmp,self::safeName((string)($file['name']??'Report_WL.xlsx')),$sourceHash);
        $fingerprint=self::fingerprint($companyId,$reportDate,$raw);
        if(self::duplicateExists($fingerprint,null)) throw new RuntimeException('همین Report_WL قبلاً ثبت شده است. گزارش قبلی را ویرایش یا در صورت اشتباه حذف کن؛ ثبت تکراری مجاز نیست.');
        $totals=self::totals($draftRows);
        $draft=[
            'company_id'=>$companyId,'company_name'=>$company['name'],'report_date'=>$reportDate,'report_date_input'=>$dateInput,
            'source_filename'=>self::safeName((string)($file['name']??'Report_WL')),'source_storage_path'=>$sourceStoragePath,'source_hash'=>$sourceHash,'duplicate_fingerprint'=>$fingerprint,
            'general_rate'=>$general,'special_rate'=>$special,'general_rate_input'=>$generalInput,'special_rate_input'=>$specialInput,'loss_percent'=>$pct,'allow_duplicate'=>false,
            'panel_id'=>$inferredPanelId,'panel_name'=>$panelName,'unknown_usernames'=>$unknown,'rows'=>$draftRows,'totals'=>$totals,'editing_report_id'=>null,'created_at'=>time(),
        ];
        Logger::system('report.preview_built',['company_id'=>$companyId,'rows'=>count($draftRows),'unknown'=>count($unknown),'file'=>$draft['source_filename']]);
        return $draft;
    }

    public static function normalizeClientDraft(array $sessionDraft,string $json,bool $requireResolved=true): array {
        $input=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($input) || !isset($input['rows']) || !is_array($input['rows'])) throw new RuntimeException('اطلاعات پیش‌نمایش معتبر نیست.');
        if(count($input['rows'])<1 || count($input['rows'])>5000) throw new RuntimeException('تعداد ردیف‌های پیش‌نمایش نامعتبر است.');
        $companyId=(int)$sessionDraft['company_id'];$panels=[];foreach(self::panelsForCompany($companyId) as $p)$panels[(int)$p['id']]=$p;
        $out=[];$seen=[];
        foreach($input['rows'] as $i=>$r){
            if(!is_array($r))continue;
            $username=trim((string)($r['username']??''));if($username==='')throw new RuntimeException('Username ردیف '.($i+1).' خالی است.');
            if(isset($seen[$username]))throw new RuntimeException('Username تکراری در پیش‌نمایش: '.$username);$seen[$username]=true;
            $accountId=isset($r['account_id'])&&$r['account_id']!==''?(int)$r['account_id']:null;
            $account=$accountId?self::accountForCompany($accountId,$companyId):null;
            if($accountId && !$account)throw new RuntimeException('Account ردیف '.$username.' دیگر معتبر یا متعلق به این شرکت نیست.');
            $panelId=isset($r['panel_id'])&&$r['panel_id']!==''?(int)$r['panel_id']:($account?(int)$account['panel_id']:null);
            $registerManual=!empty($r['register_manual']);
            $unknownConfirmed=$account?true:!empty($r['unknown_confirmed']);
            if($requireResolved && !$account && !$unknownConfirmed)throw new RuntimeException('Username جدید «'.$username.'» هنوز تعیین تکلیف نشده است. قبل از ثبت نهایی مشخص کن موقت بماند یا در AccountSettings ثبت شود.');
            if($requireResolved && !$account && (!$panelId || !isset($panels[$panelId])))throw new RuntimeException('برای Username جدید «'.$username.'» پنل مقصد را مشخص کن.');
            if($registerManual && (!$panelId || !isset($panels[$panelId])))throw new RuntimeException('برای ثبت کنترل‌شده Username جدید «'.$username.'» یک پنل معتبر انتخاب کن.');
            $name=trim((string)($r['name']??''));if($name==='')$name=$username;
            $mw=self::decimal($r['member_win']??0,'مبلغ دلاری '.$username,false);
            $rate=self::decimal($r['rate']??0,'نرخ '.$username,true);
            $pct=self::decimal($r['loss_percent']??0,'درصد '.$username,false);if($pct<0||$pct>100)throw new RuntimeException('درصد '.$username.' باید بین ۰ تا ۱۰۰ باشد.');
            $rateType=in_array(($r['rate_type']??''),['general','special'],true)?$r['rate_type']:($account['rate_type']??'general');
            $origin=$account?'import':($registerManual?'manual':'temporary');
            $out[]=self::draftRow($username,$mw,$rate,$pct,[
                'account_id'=>$accountId,'panel_id'=>$panelId,'panel_name'=>$panelId&&isset($panels[$panelId])?$panels[$panelId]['name']:($account['panel_name']??null),
                'name'=>$name,'original_name'=>$account['original_name']??$name,'display_name'=>$account['display_name']??$name,
                'rate_type'=>$rateType,'row_origin'=>$origin,'register_manual'=>$registerManual,'unknown_confirmed'=>$unknownConfirmed,
            ]);
        }
        $panelIds=array_values(array_unique(array_map(fn($r)=>(int)$r['panel_id'],array_filter($out,fn($r)=>!empty($r['panel_id'])))));if($requireResolved&&count($panelIds)!==1)throw new RuntimeException('همه ردیف‌های گزارش باید متعلق به یک پنل مشخص باشند.');if(count($panelIds)===1)$sessionDraft['panel_id']=$panelIds[0];$sessionDraft['rows']=$out;$sessionDraft['totals']=self::totals($out);$sessionDraft['unknown_usernames']=array_values(array_map(fn($r)=>$r['username'],array_filter($out,fn($r)=>empty($r['account_id']))));$sessionDraft['duplicate_fingerprint']=self::fingerprint($companyId,(string)$sessionDraft['report_date'],array_map(fn($r)=>['username'=>$r['username'],'member_win'=>$r['member_win']],$out));return $sessionDraft;
    }

    public static function finalize(array $draft,int $userId): int {
        $companyId=(int)$draft['company_id'];if(!self::company($companyId))throw new RuntimeException('شرکت گزارش دیگر معتبر نیست.');
        $editingId=(int)($draft['editing_report_id']??0) ?: null;
        if(self::duplicateExists((string)$draft['duplicate_fingerprint'],$editingId)) throw new RuntimeException('همین گزارش قبلاً ثبت شده است. به‌جای ثبت دوباره، گزارش قبلی را ویرایش کن.');
        $panelId=(int)($draft['panel_id']??0);if(!$panelId)throw new RuntimeException('پنل گزارش مشخص نیست. Usernameهای ناشناخته را تعیین تکلیف کن.');
        $overlap=self::overlapUsernames($companyId,$panelId,(string)$draft['report_date'],array_column($draft['rows'],'username'),$editingId);
        if($overlap)throw new RuntimeException('برای همین پنل و همین روز، این Usernameها قبلاً در گزارش دیگری ثبت شده‌اند: '.implode('، ',array_slice($overlap,0,12)).(count($overlap)>12?' …':'').'. گزارش‌های یک پنل در یک روز نباید Username مشترک داشته باشند.');
        $pdo=Database::connection();self::ensureSourceStorageColumn($pdo);$pdo->beginTransaction();
        try{
            $rows=$draft['rows'];
            foreach($rows as &$r){
                if(!$r['account_id'] && !empty($r['register_manual'])){
                    $panelId=(int)$r['panel_id'];
                    $st=$pdo->prepare('SELECT company_id FROM panels WHERE id=? AND active=1');$st->execute([$panelId]);if((int)$st->fetchColumn()!==$companyId)throw new RuntimeException('پنل ثبت دستی معتبر نیست.');
                    $exists=$pdo->prepare('SELECT id FROM accounts WHERE panel_id=? AND source_username=?');$exists->execute([$panelId,$r['username']]);$id=$exists->fetchColumn();
                    if(!$id){
                        $ins=$pdo->prepare("INSERT INTO accounts(panel_id,source_username,source_uuid,first_name,last_name,original_name,display_name,rate_type,active,manual,source_payload_json,first_seen_at,last_seen_at) VALUES(?,?,NULL,NULL,NULL,?,?,?,1,1,NULL,NOW(),NOW())");
                        $ins->execute([$panelId,$r['username'],$r['original_name']?:$r['name'],$r['display_name']?:$r['name'],$r['rate_type']]);$id=$pdo->lastInsertId();
                        Logger::audit('account.manual_created_from_report',['account_id'=>(int)$id,'panel_id'=>$panelId,'username'=>$r['username']]);
                    }
                    $r['account_id']=(int)$id;$r['row_origin']='manual';
                }
            }unset($r);
            $tot=self::totals($rows);
            if($editingId){
                $old=self::reportWithRows($editingId);if(!$old || (int)$old['company_id']!==$companyId)throw new RuntimeException('گزارش برای ویرایش پیدا نشد.');
                $rev=(int)$pdo->query('SELECT COALESCE(MAX(revision_no),0)+1 FROM report_revisions WHERE daily_report_id='.(int)$editingId)->fetchColumn();
                $pdo->prepare('INSERT INTO report_revisions(daily_report_id,revision_no,before_json,after_json,changed_by) VALUES(?,?,?,?,?)')->execute([$editingId,$rev,json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode(['draft'=>$draft,'totals'=>$tot],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$userId]);
                $pdo->prepare("UPDATE daily_reports SET panel_id=?,report_date=?,source_filename=?,source_storage_path=COALESCE(?,source_storage_path),source_hash=?,duplicate_fingerprint=?,status='final',total_received=?,total_paid=?,total_commission=?,site_net=?,site_status=?,finalized_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$panelId,$draft['report_date'],$draft['source_filename'],$draft['source_storage_path']??null,$draft['source_hash'],$draft['duplicate_fingerprint'],$tot['received'],$tot['paid'],$tot['commission'],$tot['net'],$tot['status'],$editingId]);
                $pdo->prepare('DELETE FROM daily_report_rows WHERE daily_report_id=?')->execute([$editingId]);$reportId=$editingId;
            }else{
                $st=$pdo->prepare("INSERT INTO daily_reports(company_id,panel_id,report_date,source_filename,source_storage_path,source_hash,duplicate_fingerprint,status,total_received,total_paid,total_commission,site_net,site_status,created_by,finalized_at) VALUES(?,?,?,?,?,?,?,'final',?,?,?,?,?,?,NOW())");
                $st->execute([$companyId,$panelId,$draft['report_date'],$draft['source_filename'],$draft['source_storage_path']??null,$draft['source_hash'],$draft['duplicate_fingerprint'],$tot['received'],$tot['paid'],$tot['commission'],$tot['net'],$tot['status'],$userId]);$reportId=(int)$pdo->lastInsertId();
            }
            $ins=$pdo->prepare('INSERT INTO daily_report_rows(daily_report_id,account_id,panel_id,source_username,original_name_snapshot,display_name_snapshot,member_win,rate_type_snapshot,rate_value_snapshot,loss_percent_snapshot,commission,received,paid,row_origin) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach($rows as $r)$ins->execute([$reportId,$r['account_id'],$r['panel_id'],$r['username'],$r['original_name'],$r['name'],$r['member_win'],$r['rate_type'],$r['rate'],$r['loss_percent'],$r['commission'],$r['received'],$r['paid'],$r['row_origin']]);
            $pdo->commit();
            Logger::audit($editingId?'report.updated':'report.finalized',['report_id'=>$reportId,'company_id'=>$companyId,'rows'=>count($rows),'totals'=>$tot]);
            Logger::system('report.finalized',['report_id'=>$reportId,'source'=>$draft['source_filename'],'rows'=>count($rows)]);
            return $reportId;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Logger::error('report.finalize_failed',['company_id'=>$companyId,'error'=>$e->getMessage()]);throw $e;}
    }


    public static function deleteReport(int $id,int $userId): void {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $st=$pdo->prepare("SELECT * FROM daily_reports WHERE id=? AND status='final' FOR UPDATE");$st->execute([$id]);$r=$st->fetch();if(!$r)throw new RuntimeException('گزارش نهایی برای حذف پیدا نشد.');
            $countSt=$pdo->prepare('SELECT COUNT(*) FROM daily_report_rows WHERE daily_report_id=?');$countSt->execute([$id]);$rowCount=(int)$countSt->fetchColumn();
            $pdo->prepare("UPDATE daily_reports SET status='deleted',deleted_by=?,deleted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$userId,$id]);
            $pdo->commit();Logger::audit('report.deleted',['report_id'=>$id,'company_id'=>(int)$r['company_id'],'panel_id'=>$r['panel_id']!==null?(int)$r['panel_id']:null,'report_date'=>$r['report_date'],'rows'=>$rowCount,'totals'=>['received'=>$r['total_received'],'paid'=>$r['total_paid'],'commission'=>$r['total_commission'],'net'=>$r['site_net']]]);Logger::system('report.deleted',['report_id'=>$id]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Logger::error('report.delete_failed',['report_id'=>$id,'error'=>$e->getMessage()]);throw $e;}
    }

    private static function overlapUsernames(int $companyId,int $panelId,string $date,array $usernames,?int $exclude): array {
        $usernames=array_values(array_unique(array_filter(array_map('strval',$usernames))));if(!$usernames)return [];
        $marks=implode(',',array_fill(0,count($usernames),'?'));$sql="SELECT DISTINCT rr.source_username FROM daily_report_rows rr JOIN daily_reports r ON r.id=rr.daily_report_id WHERE r.company_id=? AND r.panel_id=? AND r.report_date=? AND r.status='final' AND rr.source_username IN ($marks)";$params=[$companyId,$panelId,$date,...$usernames];if($exclude){$sql.=' AND r.id<>?';$params[]=$exclude;}$st=Database::connection()->prepare($sql);$st->execute($params);return array_values(array_map('strval',$st->fetchAll(PDO::FETCH_COLUMN)?:[]));
    }

    public static function reportWithRows(int $id): ?array {
        $pdo=Database::connection();
        $st=$pdo->prepare("SELECT r.*,c.name company_name,p.name panel_name FROM daily_reports r JOIN companies c ON c.id=r.company_id LEFT JOIN panels p ON p.id=r.panel_id WHERE r.id=? AND r.status='final'");$st->execute([$id]);$r=$st->fetch();if(!$r)return null;
        // v0.4.4: report rendering must never 500 merely because an optional Phase 4 helper table
        // has not been migrated yet. The final-report card can still auto-group by the report row names.
        $hasExclusions=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customer_autogroup_exclusions'")->fetchColumn();
        if($hasExclusions){
            $sql='SELECT rr.*,p.name panel_name,ca.customer_id,cu.display_name customer_name,CASE WHEN ex.account_id IS NULL THEN 0 ELSE 1 END autogroup_excluded FROM daily_report_rows rr LEFT JOIN panels p ON p.id=rr.panel_id LEFT JOIN customer_accounts ca ON ca.account_id=rr.account_id LEFT JOIN customers cu ON cu.id=ca.customer_id AND cu.active=1 LEFT JOIN customer_autogroup_exclusions ex ON ex.account_id=rr.account_id WHERE rr.daily_report_id=? ORDER BY rr.id';
        } else {
            $sql='SELECT rr.*,p.name panel_name,ca.customer_id,cu.display_name customer_name,0 AS autogroup_excluded FROM daily_report_rows rr LEFT JOIN panels p ON p.id=rr.panel_id LEFT JOIN customer_accounts ca ON ca.account_id=rr.account_id LEFT JOIN customers cu ON cu.id=ca.customer_id AND cu.active=1 WHERE rr.daily_report_id=? ORDER BY rr.id';
        }
        $q=$pdo->prepare($sql);$q->execute([$id]);$r['rows']=$q->fetchAll();return $r;
    }

    public static function editDraft(int $id): array {
        $r=self::reportWithRows($id);if(!$r)throw new RuntimeException('گزارش پیدا نشد.');$rows=[];$general=null;$special=null;$pct=null;
        foreach($r['rows'] as $x){
            if($x['rate_type_snapshot']==='special'&&$special===null)$special=(float)$x['rate_value_snapshot'];
            if($x['rate_type_snapshot']==='general'&&$general===null)$general=(float)$x['rate_value_snapshot'];
            if($pct===null)$pct=(float)$x['loss_percent_snapshot'];
            $rows[]=self::draftRow((string)$x['source_username'],(float)$x['member_win'],(float)$x['rate_value_snapshot'],(float)$x['loss_percent_snapshot'],[
                'account_id'=>$x['account_id']!==null?(int)$x['account_id']:null,'panel_id'=>$x['panel_id']!==null?(int)$x['panel_id']:null,'panel_name'=>$x['panel_name']??null,
                'name'=>$x['display_name_snapshot']?:($x['original_name_snapshot']?:$x['source_username']),'original_name'=>$x['original_name_snapshot']?:$x['source_username'],'display_name'=>$x['display_name_snapshot']?:$x['original_name_snapshot'],
                'rate_type'=>$x['rate_type_snapshot'],'row_origin'=>$x['row_origin'],'register_manual'=>false,'unknown_confirmed'=>true,
            ]);
        }
        return ['company_id'=>(int)$r['company_id'],'company_name'=>$r['company_name'],'panel_id'=>$r['panel_id']!==null?(int)$r['panel_id']:null,'panel_name'=>$r['panel_name']??null,'report_date'=>$r['report_date'],'report_date_input'=>$r['report_date'],'source_filename'=>$r['source_filename'],'source_storage_path'=>$r['source_storage_path']??null,'source_hash'=>$r['source_hash'],'duplicate_fingerprint'=>$r['duplicate_fingerprint'],'general_rate'=>$general??SettingsService::rateToCanonical(100),'special_rate'=>$special??SettingsService::rateToCanonical(200),'general_rate_input'=>SettingsService::rateFromCanonical($general??SettingsService::rateToCanonical(100)),'special_rate_input'=>SettingsService::rateFromCanonical($special??SettingsService::rateToCanonical(200)),'loss_percent'=>$pct??10,'allow_duplicate'=>true,'unknown_usernames'=>[],'rows'=>$rows,'totals'=>self::totals($rows),'editing_report_id'=>$id,'created_at'=>time()];
    }

    public static function calculateRow(float $memberWin,float $rate,float $pct): array {
        $commission=0.0;$received=0.0;$paid=0.0;
        if($memberWin<0){$gross=abs($memberWin)*$rate;$commission=$gross*$pct/100;$received=$gross-$commission;}
        elseif($memberWin>0){$paid=$memberWin*$rate;}
        return ['commission'=>self::roundMoney($commission),'received'=>self::roundMoney($received),'paid'=>self::roundMoney($paid)];
    }

    public static function totals(array $rows): array {
        $recv=$paid=$comm=0.0;foreach($rows as $r){$recv+=(float)$r['received'];$paid+=(float)$r['paid'];$comm+=(float)$r['commission'];}
        $recv=self::roundMoney($recv);$paid=self::roundMoney($paid);$comm=self::roundMoney($comm);$net=self::roundMoney($recv-$paid);
        return ['received'=>$recv,'paid'=>$paid,'commission'=>$comm,'net'=>$net,'status'=>$net>0?'win':($net<0?'loss':'settled')];
    }

    private static function draftRow(string $username,float $mw,float $rate,float $pct,array $meta): array {
        $c=self::calculateRow($mw,$rate,$pct);
        return array_merge(['username'=>$username,'member_win'=>self::roundMoney($mw),'rate'=>self::roundMoney($rate),'loss_percent'=>self::roundMoney($pct)],$meta,$c);
    }

    private static function accountMap(array $usernames): array {
        if(!$usernames)return [];$pdo=Database::connection();$marks=implode(',',array_fill(0,count($usernames),'?'));
        $st=$pdo->prepare("SELECT a.*,p.company_id,p.name panel_name,p.display_name_mode,c.name company_name FROM accounts a JOIN panels p ON p.id=a.panel_id JOIN companies c ON c.id=p.company_id WHERE a.active=1 AND a.source_username IN ($marks)");$st->execute(array_values($usernames));$map=[];foreach($st->fetchAll() as $a)$map[$a['source_username']][]=$a;return $map;
    }
    private static function accountForCompany(int $id,int $companyId): ?array {$st=Database::connection()->prepare('SELECT a.*,p.company_id,p.name panel_name,p.display_name_mode FROM accounts a JOIN panels p ON p.id=a.panel_id WHERE a.id=? AND p.company_id=?');$st->execute([$id,$companyId]);$a=$st->fetch();return $a?:null;}
    private static function company(int $id): ?array {$st=Database::connection()->prepare('SELECT * FROM companies WHERE id=? AND active=1');$st->execute([$id]);$x=$st->fetch();return $x?:null;}
    private static function duplicateExists(string $fp,?int $exclude): bool {$pdo=Database::connection();if($exclude){$st=$pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE duplicate_fingerprint=? AND status='final' AND id<>?");$st->execute([$fp,$exclude]);}else{$st=$pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE duplicate_fingerprint=? AND status='final'");$st->execute([$fp]);}return (int)$st->fetchColumn()>0;}
    private static function fingerprint(int $companyId,string $date,array $raw): string {$canon=$raw;usort($canon,fn($a,$b)=>strcmp($a['username'],$b['username']));$x=array_map(fn($r)=>[$r['username'],number_format((float)$r['member_win'],6,'.','')],$canon);return hash('sha256',json_encode([$companyId,$date,$x],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}

    private static function storeSourceUpload(string $tmp,string $name,string $hash): ?string {
        if($tmp===''||!is_file($tmp))return null;
        $dir=base_path('storage/report_sources');
        if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))return null;
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,['xls','xlsx'],true))$ext='xlsx';
        $rel='storage/report_sources/'.substr($hash,0,20).'-'.date('YmdHis').'.'.$ext;
        $dest=base_path($rel);
        if(!@copy($tmp,$dest))return null;
        @chmod($dest,0640);return $rel;
    }

    private static function validateUpload(array $file): void {if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('فایل Report_WL کامل Upload نشد.');if((int)($file['size']??0)>15*1024*1024)throw new RuntimeException('حجم فایل Report_WL بیش از ۱۵ مگابایت است.');$ext=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));if(!in_array($ext,['xls','xlsx'],true))throw new RuntimeException('فرمت Report_WL باید XLS یا XLSX باشد.');}
    private static function ensureSourceStorageColumn(PDO $pdo): void {$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'daily_reports\' AND column_name=\'source_storage_path\'');$st->execute();if(!(bool)$st->fetchColumn()){$pdo->exec('ALTER TABLE daily_reports ADD COLUMN source_storage_path VARCHAR(500) NULL AFTER source_filename');}}

    private static function safeName(string $name): string {$name=preg_replace('/[^\pL\pN._() -]+/u','_',basename($name))??'report.xlsx';return mb_substr($name,0,250);}
    private static function field(array $row,array $aliases): mixed {$norm=[];foreach($row as $k=>$v){$key=mb_strtolower(preg_replace('/[^\pL\pN]+/u','',(string)$k)??'','UTF-8');$norm[$key]=$v;}foreach($aliases as $a){$key=mb_strtolower(preg_replace('/[^\pL\pN]+/u','',$a)??'','UTF-8');if(array_key_exists($key,$norm))return $norm[$key];}return '';}
    private static function decimal(mixed $v,string $label,bool $positive): float {$s=trim((string)$v);$s=strtr($s,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',','=>'']);if($s===''||!is_numeric($s))throw new RuntimeException($label.' نامعتبر است.');$n=(float)$s;if($positive&&$n<=0)throw new RuntimeException($label.' باید بزرگ‌تر از صفر باشد.');return self::roundMoney($n);}
    private static function roundMoney(float $n): float {return round($n,6);}

    public static function normalizeBusinessDate(string $value): string {
        $v=trim(strtr($value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
        $v=str_replace(['.','-'],'/',$v);if(!preg_match('~^(\d{4})/(\d{1,2})/(\d{1,2})$~',$v,$m))throw new RuntimeException('تاریخ گزارش را به شکل ۱۴۰۵/۰۷/۱۳ یا 2026/10/05 وارد کن.');
        $y=(int)$m[1];$mo=(int)$m[2];$d=(int)$m[3];
        if($y>=1700){if(!checkdate($mo,$d,$y))throw new RuntimeException('تاریخ گزارش معتبر نیست.');return sprintf('%04d-%02d-%02d',$y,$mo,$d);}
        if($y<1200||$y>1600||$mo<1||$mo>12||$d<1||$d>31)throw new RuntimeException('تاریخ شمسی گزارش معتبر نیست.');
        [$gy,$gm,$gd]=self::jalaliToGregorian($y,$mo,$d);if(!checkdate($gm,$gd,$gy))throw new RuntimeException('تاریخ شمسی گزارش معتبر نیست.');return sprintf('%04d-%02d-%02d',$gy,$gm,$gd);
    }
    public static function todayFa(): string {
        [$jy,$jm,$jd]=self::gregorianToJalali((int)date('Y'),(int)date('n'),(int)date('j'));
        return sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
    }

    public static function displayBusinessDate(string $gregorian): string {
        if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$gregorian,$m))return $gregorian;
        [$jy,$jm,$jd]=self::gregorianToJalali((int)$m[1],(int)$m[2],(int)$m[3]);
        return sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
    }
    private static function gregorianToJalali(int $gy,int $gm,int $gd): array {
        $gdm=[0,31,59,90,120,151,181,212,243,273,304,334];
        if($gy>1600){$jy=979;$gy-=1600;}else{$jy=0;$gy-=621;}
        $gy2=$gm>2?$gy+1:$gy;
        $days=(365*$gy)+intdiv($gy2+3,4)-intdiv($gy2+99,100)+intdiv($gy2+399,400)-80+$gd+$gdm[$gm-1];
        $jy+=33*intdiv($days,12053);$days%=12053;$jy+=4*intdiv($days,1461);$days%=1461;
        if($days>365){$jy+=intdiv($days-1,365);$days=($days-1)%365;}
        if($days<186){$jm=1+intdiv($days,31);$jd=1+($days%31);}else{$jm=7+intdiv($days-186,30);$jd=1+(($days-186)%30);}
        return [$jy,$jm,$jd];
    }
    private static function jalaliToGregorian(int $jy,int $jm,int $jd): array {
        $jy+=1595;$days=-355668+(365*$jy)+intdiv($jy,33)*8+intdiv(($jy%33)+3,4)+$jd+($jm<7?($jm-1)*31:(($jm-7)*30)+186);
        $gy=400*intdiv($days,146097);$days%=146097;if($days>36524){$gy+=100*intdiv(--$days,36524);$days%=36524;if($days>=365)$days++;}$gy+=4*intdiv($days,1461);$days%=1461;if($days>365){$gy+=intdiv($days-1,365);$days=($days-1)%365;}$gd=$days+1;$sal=[0,31,(($gy%4===0&&$gy%100!==0)||$gy%400===0)?29:28,31,30,31,30,31,31,30,31,30,31];$gm=1;while($gm<=12&&$gd>$sal[$gm]){$gd-=$sal[$gm];$gm++;}return [$gy,$gm,$gd];
    }
}
