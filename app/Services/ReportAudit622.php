<?php
declare(strict_types=1);
/** Authoritative USD reconciliation. No amounts here are rounded to display cents. */
final class ReportAudit622 {
    public static function evaluate(array $draft): array {
        $grand=$draft['source_member_win_total']??null;
        $raw=$draft['source_member_win_raw_rows_total']??null;
        $current=0;
        foreach(($draft['rows']??[]) as $row) $current+=(int)round(((float)($row['member_win']??0))*1000000);
        $grandMicro=$grand===null?null:(int)round((float)$grand*1000000);
        $rawMicro=$raw===null?null:(int)round((float)$raw*1000000);
        $delta=0;
        if($grandMicro!==null && $rawMicro!==null && abs($grandMicro-$rawMicro)>1) $delta=$rawMicro-$grandMicro;
        elseif($grandMicro!==null && $rawMicro!==null) $delta=$current-$rawMicro;
        elseif($grandMicro!==null) $delta=$current-$grandMicro;
        return [
            'is_mismatch'=>abs($delta)>1,
            'difference'=>number_format($delta/1000000,6,'.',''),
            'preview_total'=>number_format($current/1000000,6,'.',''),
            'source_grand_total'=>$grandMicro===null?null:number_format($grandMicro/1000000,6,'.',''),
            'source_raw_total'=>$rawMicro===null?null:number_format($rawMicro/1000000,6,'.',''),
        ];
    }
    public static function validate(array &$draft,string $reason): void {
        $audit=self::evaluate($draft);
        $reason=trim($reason);
        if($audit['is_mismatch'] && (function_exists('mb_strlen')?mb_strlen($reason):strlen($reason))<8) throw new RuntimeException('برای ثبت گزارش دارای اختلاف تراز دلاری، توضیح علت با حداقل ۸ نویسه الزامی است.');
        if((function_exists('mb_strlen')?mb_strlen($reason):strlen($reason))>2000) throw new RuntimeException('توضیح مغایرت بیش از حد طولانی است.');
        $draft['usd_audit']=$audit;
        $draft['usd_audit_reason']=$audit['is_mismatch']?$reason:'';
    }
    /** Called inside the same PDO transaction as report finalization. */
    public static function persist(PDO $pdo,int $id,array $draft,int $userId): void {
        $audit=self::evaluate($draft);
        if(!$audit['is_mismatch']){
            // If an archived Excel source is unavailable, do not erase an earlier verified exception.
            if($audit['source_grand_total']===null && $audit['source_raw_total']===null)return;
            $pdo->prepare('DELETE FROM daily_report_usd_audits WHERE daily_report_id=?')->execute([$id]);
            return;
        }
        $reason=trim((string)($draft['usd_audit_reason']??''));
        if((function_exists('mb_strlen')?mb_strlen($reason):strlen($reason))<8)throw new RuntimeException('ثبت گزارش دارای مغایرت بدون توضیح معتبر امکان‌پذیر نیست.');
        $pdo->prepare('INSERT INTO daily_report_usd_audits (daily_report_id,source_grand_total,source_raw_total,preview_total,discrepancy,reason,confirmed_by,confirmed_at) VALUES (?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE source_grand_total=VALUES(source_grand_total),source_raw_total=VALUES(source_raw_total),preview_total=VALUES(preview_total),discrepancy=VALUES(discrepancy),reason=VALUES(reason),confirmed_by=VALUES(confirmed_by),confirmed_at=NOW()')
            ->execute([$id,$audit['source_grand_total'],$audit['source_raw_total'],$audit['preview_total'],$audit['difference'],$reason,$userId]);
    }
}
