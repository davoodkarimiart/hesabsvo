<?php

declare(strict_types=1);

/** Transfer settlement service, intentionally independent from per-customer Toman rates. */
final class TransferService {
    private static function normalNumber(mixed $value): string {
        $s = strtr(trim((string)$value), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '٬'=>'',','=>'','٫'=>'.',' '=>'',
        ]);
        return $s;
    }
    public static function decimal(mixed $value, string $field, int $decimals=6, bool $positive=false): float {
        $s = self::normalNumber($value);
        if (!preg_match('/^[+-]?(?:\d{1,14})(?:\.\d{1,6})?$/D', $s)) throw new RuntimeException($field.' نامعتبر است.');
        $n=(float)$s;
        if (!is_finite($n) || abs($n)>999999999999.0 || ($positive && $n<=0)) throw new RuntimeException($field.' خارج از محدوده مجاز است.');
        return round($n,$decimals);
    }
    private static function period(string $mode, string $start, string $end): array {
        if (!in_array($mode,['weekly','monthly','custom'],true)) throw new RuntimeException('نوع دوره نامعتبر است.');
        $a=AccountingService::normalizeBusinessDate($start);
        $b=AccountingService::normalizeBusinessDate($end);
        $d1=new DateTimeImmutable($a);$d2=new DateTimeImmutable($b);
        $days=(int)$d1->diff($d2)->format('%r%a');
        if ($days<0||$days>365) throw new RuntimeException('بازه باید از ۱ تا ۳۶۶ روز باشد.');
        if ($mode==='weekly' && $days!==6) throw new RuntimeException('دوره هفتگی دقیقاً ۷ روز است.');
        if ($mode==='monthly' && $a!==self::rollingMonthStart($d2)->format('Y-m-d')) throw new RuntimeException('دوره ماهانه باید یک ماه شناور تا تاریخ پایان انتخابی باشد.');
        return [$a,$b];
    }
    /** Rolling calendar month inclusive of the chosen end date; does not depend on weekday. */
    private static function rollingMonthStart(DateTimeImmutable $end): DateTimeImmutable {
        $previous=$end->modify('first day of this month')->modify('-1 month');
        $day=min((int)$end->format('d'),(int)$previous->format('t'));
        return $previous->setDate((int)$previous->format('Y'),(int)$previous->format('m'),$day)->modify('+1 day');
    }
    private static function isoDay(string $day): DateTimeImmutable {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day))throw new RuntimeException('تاریخ نامعتبر است.');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        if(!$d||$d->format('Y-m-d')!==$day)throw new RuntimeException('تاریخ نامعتبر است.');
        return $d;
    }
    /** Date overlap is blocked even when a day has no report rows. Editing excludes its own settlement. */
    private static function overlappingSettlements(PDO $pdo,int $companyId,string $start,string $end,?int $except=null): array {
        $sql="SELECT id,transfer_code,period_start,period_end FROM transfers WHERE company_id=? AND status='final' AND period_start<=? AND period_end>=?";
        $params=[$companyId,$end,$start];
        if($except){$sql.=' AND id<>?';$params[]=$except;}
        $sql.=' ORDER BY period_start,id';
        $st=$pdo->prepare($sql);$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function overlapInfo(int $companyId,string $start,string $end,?int $editing=null): array {
        $a=self::isoDay($start);$b=self::isoDay($end);
        $span=(int)$a->diff($b)->format('%r%a');
        if($span<0||$span>365)throw new RuntimeException('بازه باید حداکثر ۳۶۶ روز باشد.');
        if($companyId<1)throw new RuntimeException('شرکت را انتخاب کن.');
        self::company($companyId);
        $overlaps=self::overlappingSettlements(Database::connection(),$companyId,$start,$end,$editing);
        $occupied=[];$last=null;
        foreach($overlaps as $row){
            $from=max($start,$row['period_start']);$to=min($end,$row['period_end']);
            for($d=self::isoDay($from);$d<=self::isoDay($to);$d=$d->modify('+1 day'))$occupied[$d->format('Y-m-d')]=true;
            if($last===null||$last<$row['period_end'])$last=$row['period_end'];
        }
        $suggestion=$last!==null?self::isoDay($last)->modify('+1 day')->format('Y-m-d'):null;
        return ['overlap_days'=>count($occupied),'overlaps'=>$overlaps,'suggested_start'=>$suggestion!==null&&$suggestion<=$end?$suggestion:null,'free_days'=>$span+1-count($occupied)];
    }
    public static function calendar(int $companyId,string $start,string $end,?int $editing=null): array {
        $from=self::isoDay($start);$to=self::isoDay($end);
        $span=(int)$from->diff($to)->format('%r%a');
        if($span<0||$span>62)throw new RuntimeException('بازه تقویم باید حداکثر ۶۳ روز باشد.');
        if($companyId<1)throw new RuntimeException('برای مشاهده وضعیت روزها ابتدا شرکت را انتخاب کن.');
        self::company($companyId);
        $pdo=Database::connection();
        $occupied=self::overlappingSettlements($pdo,$companyId,$start,$end,$editing);
        $st=$pdo->prepare("SELECT DISTINCT report_date FROM daily_reports WHERE company_id=? AND status='final' AND deleted_at IS NULL AND report_date BETWEEN ? AND ?");
        $st->execute([$companyId,$start,$end]);$available=array_fill_keys($st->fetchAll(PDO::FETCH_COLUMN),true);
        $days=[];
        for($date=$from;$date<=$to;$date=$date->modify('+1 day')){
            $iso=$date->format('Y-m-d');$claim=null;
            foreach($occupied as $item){if($iso>=$item['period_start']&&$iso<=$item['period_end']){$claim=$item;break;}}
            $days[$iso]=$claim?['state'=>'settled','transfer_id'=>(int)$claim['id'],'transfer_code'=>(string)$claim['transfer_code'],'period_start'=>$claim['period_start'],'period_end'=>$claim['period_end']]:['state'=>isset($available[$iso])?'ready':'missing'];
        }
        return ['days'=>$days,'occupied'=>$occupied];
    }
    public static function company(int $companyId): array {
        $st=Database::connection()->prepare('SELECT id,name,active,company_role,transfer_percent FROM companies WHERE id=?');
        $st->execute([$companyId]);$r=$st->fetch();
        if (!$r) throw new RuntimeException('شرکت پیدا نشد.');
        return $r;
    }
    /** Full precision of Member Win (DECIMAL 24,6); sign is opposite site win/loss. */
    public static function source(int $companyId,string $mode,string $start,string $end,?int $editing=null): array {
        [$a,$b]=self::period($mode,$start,$end);
        $company=self::company($companyId);
        $pdo=Database::connection();
        $overlaps=self::overlappingSettlements($pdo,$companyId,$a,$b,$editing);
        if($overlaps){$one=$overlaps[0];throw new RuntimeException('بازه انتخابی با ترانسفر '.($one['transfer_code']??('#'.$one['id'])).' از '.$one['period_start'].' تا '.$one['period_end'].' هم‌پوشانی دارد. پس از پایان آخرین روز تسویه‌شده بازه را انتخاب کن.');}
        $st=$pdo->prepare("SELECT r.id,r.report_date,COALESCE(SUM(rr.member_win),0) member_total,COUNT(rr.id) detail_count
                             FROM daily_reports r LEFT JOIN daily_report_rows rr ON rr.daily_report_id=r.id
                             WHERE r.company_id=? AND r.status='final' AND r.deleted_at IS NULL
                             AND r.report_date BETWEEN ? AND ? GROUP BY r.id,r.report_date ORDER BY r.report_date,r.id");
        $st->execute([$companyId,$a,$b]);$reports=$st->fetchAll();
        if (!$reports) throw new RuntimeException('برای شرکت و بازه انتخابی گزارش نهایی پیدا نشد.');
        if (count($reports)>1000) throw new RuntimeException('تعداد گزارش‌ها بیش از حد مجاز است. بازه را کوچک‌تر انتخاب کن.');
        $ids=array_map(fn($x)=>(int)$x['id'],$reports);
        $claimed=self::claimedIds($ids,$editing);
        if ($claimed) throw new RuntimeException('برخی گزارش‌ها قبلاً تسویه شده‌اند: '.implode('، ',array_slice($claimed,0,10)).'. ابتدا ترانسفر قبلی را ابطال یا ویرایش کن.');
        $sum='0';
        // Using the database's DECIMAL SUM avoids premature per-row conversion to two-decimal display.
        $sumStmt=$pdo->prepare("SELECT COALESCE(SUM(rr.member_win),0) FROM daily_reports r JOIN daily_report_rows rr ON rr.daily_report_id=r.id WHERE r.company_id=? AND r.status='final' AND r.deleted_at IS NULL AND r.report_date BETWEEN ? AND ?");
        $sumStmt->execute([$companyId,$a,$b]);$sum=(string)$sumStmt->fetchColumn();
        $warn=$pdo->prepare("SELECT a.daily_report_id,r.report_date,a.discrepancy,a.reason FROM daily_report_usd_audits a JOIN daily_reports r ON r.id=a.daily_report_id WHERE r.company_id=? AND r.status='final' AND r.deleted_at IS NULL AND r.report_date BETWEEN ? AND ? ORDER BY r.report_date,a.daily_report_id");
        $warn->execute([$companyId,$a,$b]);
        return ['company'=>$company,'start'=>$a,'end'=>$b,'period_type'=>$mode,'reports'=>$reports,'report_count'=>count($reports),'member_win_total'=>$sum,'site_usd'=>number_format(-(float)$sum,6,'.',''),'audit_warnings'=>$warn->fetchAll(PDO::FETCH_ASSOC)];
    }
    private static function claimedIds(array $ids,?int $except=null): array {
        if (!$ids)return [];
        $pdo=Database::connection();
        $slots=implode(',',array_fill(0,count($ids),'?'));
        $sql="SELECT DISTINCT tri.daily_report_id FROM transfer_report_items tri JOIN transfers t ON t.id=tri.transfer_id WHERE t.status='final' AND tri.daily_report_id IN ($slots)";
        if ($except) $sql.=' AND t.id<>?';
        $st=$pdo->prepare($sql);$values=$ids;if($except)$values[]=$except;$st->execute($values);
        return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
    }
    public static function calculate(array $source,mixed $percent,mixed $rate,mixed $override,string $reason): array {
        $pct=self::decimal($percent,'درصد ترانسفر',3);
        if ($pct<0||$pct>100)throw new RuntimeException('درصد باید بین صفر و صد باشد.');
        $usdRate=self::decimal($rate,'نرخ روز دلار به تومان',6,true);
        $calculated=(float)$source['site_usd'];
        $custom=trim(self::normalNumber($override));
        $applied=$custom===''?$calculated:self::decimal($custom,'مبلغ دلاری اصلاح‌شده',6);
        if (abs($applied-$calculated)>0.0000004 && trim($reason)==='')throw new RuntimeException('برای اصلاح دستی مبلغ دلاری، دلیل را بنویس.');
        $shareExact=$applied*$pct/100;
        $share=round($shareExact,6);
        // Signed USD share is retained for traceability; settled Toman amount is a nonnegative magnitude.
        $toman=round(abs($shareExact*$usdRate),6);
        if(!is_finite($toman)||abs($toman)>999999999999999.0)throw new RuntimeException('مبلغ تسویه خارج از محدوده مجاز است.');
        return [
            'site_usd_calculated'=>number_format($calculated,6,'.',''),
            'site_usd_applied'=>number_format($applied,6,'.',''),
            'transfer_percent'=>number_format($pct,3,'.',''),
            'usd_share'=>number_format($share,6,'.',''),
            'usd_rate_toman'=>number_format($usdRate,6,'.',''),
            'total_toman'=>number_format($toman,6,'.',''),
            // Positive source Member Win => pay counterparty; negative => receive from counterparty.
            'direction'=>abs($shareExact)<0.0000005?'none':((float)$source['member_win_total']>0?'to_master':((float)$source['member_win_total']<0?'from_master':($shareExact<0?'to_master':'from_master'))),
            'override_reason'=>abs($applied-$calculated)>0.0000004?trim($reason):null,
        ];
    }
    public static function preview(array $input,?int $editing=null): array {
        $companyId=(int)($input['company_id']??0);
        $mode=(string)($input['period_type']??'weekly');
        $source=self::source($companyId,$mode,(string)($input['period_start']??''),(string)($input['period_end']??''),$editing);
        $percent=(string)($input['transfer_percent']??$source['company']['transfer_percent']??'');
        $rate=(string)($input['usd_rate_toman']??'');
        if ($rate==='')return ['source'=>$source,'company'=>$source['company'],'calculation'=>null];
        $calc=self::calculate($source,$percent,$rate,(string)($input['site_usd_override']??''),(string)($input['override_reason']??''));
        return ['source'=>$source,'company'=>$source['company'],'calculation'=>$calc];
    }
    public static function listingPaginated(string $q='',int $companyId=0,string $status='',string $start='',string $end='',int $page=1,int $perPage=25): array {
        $where=[];$params=[];
        if($q!==''){$where[]='(t.transfer_code LIKE ? OR t.company_name_snapshot LIKE ? OR t.notes LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
        if($companyId>0){$where[]='t.company_id=?';$params[]=$companyId;}
        if(in_array($status,['final','void'],true)){$where[]='t.status=?';$params[]=$status;}
        if($start!==''){$from=self::isoDay($start)->format('Y-m-d');$where[]='t.period_end>=?';$params[]=$from;}
        if($end!==''){$to=self::isoDay($end)->format('Y-m-d');$where[]='t.period_start<=?';$params[]=$to;}
        if($start!==''&&$end!==''&&$start>$end)throw new RuntimeException('تاریخ شروع فیلتر نباید بعد از پایان باشد.');
        $filter=$where?' WHERE '.implode(' AND ',$where):'';
        $pdo=Database::connection();
        $count=$pdo->prepare('SELECT COUNT(*) FROM transfers t'.$filter);$count->execute($params);$total=(int)$count->fetchColumn();
        $perPage=max(5,min(100,$perPage));$pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($pages,$page));
        $sql='SELECT t.* FROM transfers t'.$filter.' ORDER BY t.id DESC LIMIT ? OFFSET ?';
        $st=$pdo->prepare($sql);$i=1;foreach($params as $param)$st->bindValue($i++,$param,PDO::PARAM_STR);
        $st->bindValue($i++,$perPage,PDO::PARAM_INT);$st->bindValue($i,($page-1)*$perPage,PDO::PARAM_INT);$st->execute();
        return ['rows'=>$st->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }
    public static function listing(string $q='',int $companyId=0,string $status=''): array {
        return self::listingPaginated($q,$companyId,$status)['rows'];
    }
    public static function get(int $id): ?array {
        $pdo=Database::connection();$st=$pdo->prepare('SELECT * FROM transfers WHERE id=?');$st->execute([$id]);$item=$st->fetch();if(!$item)return null;
        $r=$pdo->prepare('SELECT daily_report_id,report_date,member_win_snapshot FROM transfer_report_items WHERE transfer_id=? ORDER BY report_date,daily_report_id');$r->execute([$id]);$item['reports']=$r->fetchAll();return $item;
    }
    private static function logRevision(PDO $pdo,int $id,string $action,?array $before,?array $after,string $reason,int $uid): void {
        $st=$pdo->prepare('INSERT INTO transfer_revisions(transfer_id,action_type,before_json,after_json,reason,changed_by) VALUES (?,?,?,?,?,?)');
        $st->execute([$id,$action,$before?json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$after?json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$reason?:null,$uid]);
    }
    private static function lockCompany(PDO $pdo,int $companyId): void {
        $lock='transfer_company_'.$companyId;
        $st=$pdo->prepare('SELECT GET_LOCK(?,10)');$st->execute([$lock]);if((int)$st->fetchColumn()!==1)throw new RuntimeException('ترانسفر شرکت در حال ثبت است. دوباره تلاش کن.');
    }
    private static function unlockCompany(PDO $pdo,int $companyId): void {
        $st=$pdo->prepare('SELECT RELEASE_LOCK(?)');$st->execute(['transfer_company_'.$companyId]);
    }
    public static function save(array $input,int $uid,?int $editing=null): int {
        $companyId=(int)($input['company_id']??0);
        if($companyId<1)throw new RuntimeException('شرکت را انتخاب کن.');
        $pdo=Database::connection();self::lockCompany($pdo,$companyId);
        try{
            $pdo->beginTransaction();
            $before=null;
            if($editing){
                $before=self::get($editing);
                if(!$before||$before['status']!=='final')throw new RuntimeException('ترانسفر انتخابی قابل ویرایش نیست.');
                if((int)$before['company_id']!==$companyId)throw new RuntimeException('شرکت ترانسفر ثبت‌شده را نمی‌توان تغییر داد؛ ترانسفر قبلی را ابطال کن.');
                if(trim((string)($input['edit_reason']??''))==='')throw new RuntimeException('دلیل ویرایش ترانسفر نهایی را وارد کن.');
            }
            $source=self::source($companyId,(string)($input['period_type']??''),(string)($input['period_start']??''),(string)($input['period_end']??''),$editing);
            $calc=self::calculate($source,$input['transfer_percent']??'', $input['usd_rate_toman']??'', $input['site_usd_override']??'',(string)($input['override_reason']??''));
            $c=$source['company'];
            $values=[ $companyId,(string)$c['name'],(string)($c['company_role']??'unset'),$source['period_type'],$source['start'],$source['end'],$source['member_win_total'],$calc['site_usd_calculated'],$calc['site_usd_applied'],$calc['override_reason'],$calc['transfer_percent'],$calc['usd_share'],$calc['usd_rate_toman'],$calc['total_toman'],$calc['direction'],$source['report_count'],trim((string)($input['notes']??'')) ];
            if($editing){
                $sql="UPDATE transfers SET company_name_snapshot=?,company_role_snapshot=?,period_type=?,period_start=?,period_end=?,member_win_total=?,site_usd_calculated=?,site_usd_applied=?,override_reason=?,transfer_percent=?,usd_share=?,usd_rate_toman=?,total_toman=?,direction=?,report_count=?,notes=?,updated_by=?,revision_no=revision_no+1 WHERE id=? AND status='final'";
                $st=$pdo->prepare($sql);$st->execute(array_merge(array_slice($values,1),[$uid,$editing]));
                $pdo->prepare('DELETE FROM transfer_report_items WHERE transfer_id=?')->execute([$editing]);$id=$editing;
            } else {
                $cols='company_id,company_name_snapshot,company_role_snapshot,period_type,period_start,period_end,member_win_total,site_usd_calculated,site_usd_applied,override_reason,transfer_percent,usd_share,usd_rate_toman,total_toman,direction,report_count,notes,created_by';
                $st=$pdo->prepare('INSERT INTO transfers ('.$cols.') VALUES ('.implode(',',array_fill(0,18,'?')).')');$st->execute(array_merge($values,[$uid]));$id=(int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE transfers SET transfer_code=? WHERE id=?')->execute(['TR-'.date('Ymd').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),$id]);
            }
            $detail=$pdo->prepare('INSERT INTO transfer_report_items(transfer_id,daily_report_id,report_date,member_win_snapshot) VALUES(?,?,?,?)');
            foreach($source['reports'] as $r){$detail->execute([$id,$r['id'],$r['report_date'],$r['member_total']]);}
            $after=self::get($id);
            self::logRevision($pdo,$id,$editing?'updated':'created',$before,$after,(string)($input['edit_reason']??''),$uid);
            $pdo->commit();Logger::audit('transfer.'.($editing?'updated':'created'),['id'=>$id,'company_id'=>$companyId,'report_count'=>$source['report_count']]);
            return $id;
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        finally{self::unlockCompany($pdo,$companyId);}
    }
    public static function void(int $id,string $reason,int $uid): void {
        $reason=trim($reason);if(mb_strlen($reason)<3)throw new RuntimeException('دلیل ابطال را بنویس.');
        $pdo=Database::connection();$before=self::get($id);if(!$before)throw new RuntimeException('ترانسفر وجود ندارد.');
        self::lockCompany($pdo,(int)$before['company_id']);
        try{$pdo->beginTransaction();
            $st=$pdo->prepare("UPDATE transfers SET status='void',voided_by=?,void_reason=?,voided_at=NOW(),revision_no=revision_no+1 WHERE id=? AND status='final'");
            $st->execute([$uid,$reason,$id]);if($st->rowCount()!==1)throw new RuntimeException('ترانسفر قبلاً ابطال شده است.');
            self::logRevision($pdo,$id,'voided',$before,self::get($id),$reason,$uid);
            $pdo->commit();Logger::audit('transfer.voided',['id'=>$id,'reason'=>$reason]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        finally{self::unlockCompany($pdo,(int)$before['company_id']);}
    }
}
