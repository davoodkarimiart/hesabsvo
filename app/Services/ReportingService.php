<?php

declare(strict_types=1);

final class ReportingService {
    public static function filters(): array {
        $pdo=Database::connection();
        $panels=$pdo->query("SELECT p.id,p.name,p.company_id,c.name company_name FROM panels p JOIN companies c ON c.id=p.company_id WHERE p.active=1 AND c.active=1 ORDER BY c.name,p.name")->fetchAll();
        return ['companies'=>AccountingService::companiesForEntry(),'panels'=>$panels,'customers'=>CustomerService::list('',true)];
    }

    public static function normalizeFilters(array $f): array {
        $f['type']=in_array(($f['type']??'cumulative'),['cumulative','daily','monthly','multi','detail','customer_detail','period_transfer','transfer_history'],true)?$f['type']:'daily';
        $f['company_id']=(int)($f['company_id']??0);$f['panel_id']=(int)($f['panel_id']??0);$f['customer_id']=(int)($f['customer_id']??0);if($f['type']!=='customer_detail')$f['customer_id']=0;if($f['type']==='customer_detail'&&$f['customer_id']>0){$f['company_id']=0;$f['panel_id']=0;}
        $f['from']=trim((string)($f['from']??''));$f['to']=trim((string)($f['to']??''));
        $f['date_mode']=($f['date_mode']??'single')==='range'?'range':'single';
        $f['date']=trim((string)($f['date']??''));
        if($f['date_mode']==='single'){$f['from']=$f['date'];$f['to']=$f['date'];}
        else $f['date']='';
        if(in_array($f['type'],['period_transfer','transfer_history'],true)){
            $f['panel_id']=0;$f['customer_id']=0;
            $f['q']=trim((string)($f['q']??''));$f['status']=(string)($f['status']??'');$f['direction']=(string)($f['direction']??'');
        }
        if($f['type']==='cumulative'){
            $f['panel_id']=0;
        }

        if($f['company_id']>0 && $f['panel_id']>0){
            $st=Database::connection()->prepare('SELECT COUNT(*) FROM panels WHERE id=? AND company_id=? AND active=1');$st->execute([$f['panel_id'],$f['company_id']]);
            if(!(int)$st->fetchColumn())$f['panel_id']=0; // stale/foreign panel selection is reset, never accepted silently
        }
        return $f;
    }

    public static function report(array $input): array {
        $f=self::normalizeFilters($input);$type=$f['type'];
        if($type==='period_transfer')return self::periodTransfer($f);
        if($type==='transfer_history')return self::transferHistory($f);
        if($type==='customer_detail'&&$f['customer_id']<=0)throw new RuntimeException('برای ریز پرداخت مشتری، یک مشتری را انتخاب کن.');$pdo=Database::connection();$where=["r.status='final'"];$p=[];
        if($f['company_id']>0){$where[]='r.company_id=?';$p[]=$f['company_id'];}
        if($f['panel_id']>0){
            // report.panel_id is historical convenience only; row ownership is authoritative.
            $where[]='EXISTS(SELECT 1 FROM daily_report_rows px WHERE px.daily_report_id=r.id AND px.panel_id=?)';$p[]=$f['panel_id'];
        }
        if($f['from']!==''){$where[]='r.report_date>=?';$p[]=self::date($f['from']);}
        if($f['to']!==''){$where[]='r.report_date<=?';$p[]=self::date($f['to']);}
        if($f['customer_id']>0){$where[]='EXISTS(SELECT 1 FROM daily_report_rows x JOIN customer_accounts ca ON ca.account_id=x.account_id WHERE x.daily_report_id=r.id AND ca.customer_id=?)';$p[]=$f['customer_id'];}
        $w=implode(' AND ',$where);

        if($type==='monthly'||$type==='multi'){
            $sql="SELECT r.id,r.report_date,c.name company_name,CAST(r.total_received AS DECIMAL(24,6)) received,CAST(r.total_paid AS DECIMAL(24,6)) paid,CAST(r.total_commission AS DECIMAL(24,6)) commission,(CAST(r.total_received AS DECIMAL(24,6))-CAST(r.total_paid AS DECIMAL(24,6))) net FROM daily_reports r JOIN companies c ON c.id=r.company_id WHERE $w ORDER BY r.report_date DESC,r.id DESC LIMIT 5000";
        } elseif(in_array($type,['detail','customer_detail'],true)){
            $whereDetail=$w;$pd=$p;
            if($type==='customer_detail'&&$f['customer_id']>0){$whereDetail.=' AND EXISTS(SELECT 1 FROM customer_accounts ca2 WHERE ca2.account_id=rr.account_id AND ca2.customer_id=?)';$pd[]=$f['customer_id'];}
            if($f['from']===''&&$f['to']==='')$whereDetail.=" AND r.id IN (SELECT z.id FROM (SELECT id FROM daily_reports WHERE status='final' ORDER BY report_date DESC,id DESC LIMIT 10) z)";
            $sql="SELECT r.id,r.report_date,c.name company_name,COALESCE(pn.name,(SELECT p2.name FROM daily_report_rows xr JOIN panels p2 ON p2.id=xr.panel_id WHERE xr.daily_report_id=r.id AND xr.panel_id IS NOT NULL GROUP BY xr.panel_id,p2.name ORDER BY xr.panel_id LIMIT 1)) panel_name,rr.source_username,COALESCE(NULLIF(rr.display_name_snapshot,''),NULLIF(rr.original_name_snapshot,''),rr.source_username) name,rr.member_win,rr.commission,rr.received,rr.paid FROM daily_reports r JOIN daily_report_rows rr ON rr.daily_report_id=r.id JOIN companies c ON c.id=r.company_id LEFT JOIN panels pn ON pn.id=rr.panel_id WHERE $whereDetail ORDER BY r.report_date DESC,r.id DESC,rr.id LIMIT 3000";$p=$pd;
        } else {
            $lim=($f['from']!==''||$f['to']!=='')?500:10;$hasSource=self::columnExists($pdo,'daily_reports','source_storage_path');$src=$hasSource?'r.source_storage_path':'NULL AS source_storage_path';
            $panelExpr="COALESCE(r.panel_id,(SELECT MIN(x.panel_id) FROM daily_report_rows x WHERE x.daily_report_id=r.id AND x.panel_id IS NOT NULL HAVING COUNT(DISTINCT x.panel_id)=1))";
            $sql="SELECT r.id,r.report_date,c.name company_name,COALESCE(pn.name,'—') panel_name,r.source_filename,$src,r.total_received received,r.total_paid paid,r.total_commission commission,(CAST(r.total_received AS DECIMAL(24,6))-CAST(r.total_paid AS DECIMAL(24,6))) net,r.site_status FROM daily_reports r JOIN companies c ON c.id=r.company_id LEFT JOIN panels pn ON pn.id=$panelExpr WHERE $w ORDER BY r.report_date DESC,r.id DESC LIMIT $lim";
        }
        $st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();
        // Normalize DB DECIMAL strings once here so rendering never calls numeric functions on strings.
        foreach($rows as &$r){foreach(['received','paid','commission','net','member_win'] as $k)if(array_key_exists($k,$r))$r[$k]=(float)$r[$k];}unset($r);

        // Display context is captured before monthly aggregation discards company/date columns.
        $sourceRowsForContext = $rows;
        $companyNames = []; $firstDate = null; $lastDate = null;
        foreach ($rows as $sourceRow) {
            $companyName = trim((string)($sourceRow['company_name'] ?? ''));
            if ($companyName !== '') $companyNames[$companyName] = true;
            $reportDate = (string)($sourceRow['report_date'] ?? '');
            if ($reportDate !== '') {
                if ($firstDate === null || $reportDate < $firstDate) $firstDate = $reportDate;
                if ($lastDate === null || $reportDate > $lastDate) $lastDate = $reportDate;
            }
        }
        if (!$companyNames && $f['company_id'] > 0) {
            $companyStmt = $pdo->prepare('SELECT name FROM companies WHERE id=? LIMIT 1');
            $companyStmt->execute([$f['company_id']]);
            $fallbackName = trim((string)$companyStmt->fetchColumn());
            if ($fallbackName !== '') $companyNames[$fallbackName] = true;
        }
        $context = ['companies'=>array_keys($companyNames),'from'=>$firstDate,'to'=>$lastDate];

        if($type==='monthly'||$type==='multi'){
            $groups=[];foreach($rows as $r){$fa=AccountingService::displayBusinessDate((string)$r['report_date']);$period=substr($fa,0,7);if(!isset($groups[$period]))$groups[$period]=['period'=>$period,'reports'=>0,'received'=>0.0,'paid'=>0.0,'commission'=>0.0,'net'=>0.0];$groups[$period]['reports']++;$groups[$period]['received']+=$r['received'];$groups[$period]['paid']+=$r['paid'];$groups[$period]['commission']+=$r['commission'];$groups[$period]['net']+=$r['net'];}$rows=array_values($groups);usort($rows,fn($a,$b)=>strcmp($b['period'],$a['period']));$rows=array_slice($rows,0,($f['from']!==''||$f['to']!=='')?120:10);
            // The header must describe the months actually shown, not older rows excluded by the 10/120-month display limit.
            $visiblePeriods=array_fill_keys(array_column($rows,'period'),true);
            $visibleCompanies=[];$visibleFrom=null;$visibleTo=null;
            foreach ($sourceRowsForContext as $sourceRow) {
                $date=(string)$sourceRow['report_date'];
                $period=substr(AccountingService::displayBusinessDate($date),0,7);
                if (!isset($visiblePeriods[$period])) continue;
                $company=trim((string)($sourceRow['company_name']??''));
                if ($company!=='') $visibleCompanies[$company]=true;
                if ($visibleFrom===null || $date<$visibleFrom) $visibleFrom=$date;
                if ($visibleTo===null || $date>$visibleTo) $visibleTo=$date;
            }
            $context=['companies'=>array_keys($visibleCompanies),'from'=>$visibleFrom,'to'=>$visibleTo];
        }
        $tot=['received'=>0.0,'paid'=>0.0,'commission'=>0.0,'net'=>0.0];foreach($rows as $r){foreach(['received','paid','commission'] as $k)$tot[$k]+=(float)($r[$k]??0);}$tot['net']=$tot['received']-$tot['paid'];
        return ['type'=>$type,'rows'=>$rows,'totals'=>$tot,'filters'=>$f,'context'=>$context];
    }

    /** Same daily report amounts, split only when immutable rows identify multiple panels. */
    private static function periodTransfer(array $f): array {
        if($f['company_id']<=0||$f['from']===''||$f['to']==='')throw new RuntimeException('شرکت و ابتدا و انتهای بازه گزارش را انتخاب کن.');
        $from=self::date($f['from']);$to=self::date($f['to']);
        if($from>$to)throw new RuntimeException('تاریخ شروع بعد از پایان است.');
        $pdo=Database::connection();
        // A single-panel document retains the canonical header totals. Multi-panel documents
        // use row snapshots; unassigned rows remain explicitly unassigned.
        $sql="SELECT r.id,r.report_date,c.name company_name,r.panel_id header_panel_id,
            r.total_received header_received,r.total_paid header_paid,r.total_commission header_commission,
            rr.panel_id,COALESCE(pn.name,'—') panel_name,COALESCE(hp.name,'—') header_panel_name,
            COUNT(rr.id) row_count,COALESCE(SUM(rr.received),0) received,
            COALESCE(SUM(rr.paid),0) paid,COALESCE(SUM(rr.commission),0) commission
            FROM daily_reports r JOIN companies c ON c.id=r.company_id
            LEFT JOIN daily_report_rows rr ON rr.daily_report_id=r.id
            LEFT JOIN panels pn ON pn.id=rr.panel_id LEFT JOIN panels hp ON hp.id=r.panel_id
            WHERE r.company_id=? AND r.status='final' AND r.deleted_at IS NULL AND r.report_date BETWEEN ? AND ?
            GROUP BY r.id,r.report_date,c.name,r.panel_id,r.total_received,r.total_paid,r.total_commission,rr.panel_id,pn.name,hp.name
            ORDER BY r.report_date ASC,r.id ASC,rr.panel_id ASC";
        $st=$pdo->prepare($sql);$st->execute([$f['company_id'],$from,$to]);
        $byReport=[];foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$byReport[$r['id']][]=$r;
        $rows=[];$panelTotals=[];$tot=['received'=>0.0,'paid'=>0.0,'commission'=>0.0,'net'=>0.0];
        foreach($byReport as $group){
            $header=$group[0];
            if(count($group)===1){
                $group[0]['panel_name']=$header['panel_id']!==null?$header['panel_name']:$header['header_panel_name'];
                $group[0]['panel_id']=$header['panel_id']??$header['header_panel_id'];
                foreach(['received','paid','commission'] as $k)$group[0][$k]=$header['header_'.$k];
            }else{
                foreach(['received','paid','commission'] as $k){
                    $sum=0.0;foreach($group as $g)$sum+=(float)$g[$k];
                    if(abs($sum-(float)$header['header_'.$k])>0.0001)throw new RuntimeException('اختلاف جمع سطرهای پنل با سند شماره '.$header['id'].'؛ گزارش قابل انتشار نیست.');
                }
            }
            foreach($group as $g){
                foreach(['received','paid','commission'] as $k){$g[$k]=(float)$g[$k];$tot[$k]+=$g[$k];}
                $g['net']=$g['received']-$g['paid'];$rows[]=$g;
                $key=(string)($g['panel_id']??'unassigned');
                if(!isset($panelTotals[$key]))$panelTotals[$key]=['panel_name'=>$g['panel_name'],'received'=>0.0,'paid'=>0.0,'commission'=>0.0,'net'=>0.0];
                foreach(['received','paid','commission','net'] as $k)$panelTotals[$key][$k]+=$g[$k];
            }
        }
        $tot['net']=$tot['received']-$tot['paid'];
        $tr=TransferService::settlementReport('',$f['company_id'],'final',$from,$to);
        $tr['totals']['adjusted_net']=$tot['net']+(float)$tr['totals']['paid']-(float)$tr['totals']['received'];
        $name=$pdo->prepare('SELECT name FROM companies WHERE id=?');$name->execute([$f['company_id']]);$company=(string)$name->fetchColumn();
        if($company==='')throw new RuntimeException('شرکت انتخاب‌شده پیدا نشد.');
        return ['type'=>'period_transfer','rows'=>$rows,'totals'=>$tot,'panel_totals'=>array_values($panelTotals),
            'transfers'=>$tr['rows'],'transfer_totals'=>$tr['totals'],'filters'=>$f,'context'=>['companies'=>[$company],'from'=>$from,'to'=>$to]];
    }
    private static function transferHistory(array $f): array {
        $from=$f['from']!==''?self::date($f['from']):'';$to=$f['to']!==''?self::date($f['to']):'';
        $tr=TransferService::settlementReport($f['q'],$f['company_id'],$f['status'],$from,$to,$f['direction']);
        $names=array_values(array_unique(array_column($tr['rows'],'company_name_snapshot')));
        return ['type'=>'transfer_history','rows'=>$tr['rows'],'totals'=>['received'=>$tr['totals']['received'],'paid'=>$tr['totals']['paid'],'commission'=>0.0,'net'=>$tr['totals']['received']-$tr['totals']['paid']],
            'transfer_totals'=>$tr['totals'],'filters'=>$f,'context'=>['companies'=>$names,'from'=>$from,'to'=>$to]];
    }
    /** Shared export presentation: transfer amounts remain in full toman, independently of report scaling. */
    public static function transferTable(array $data): array {
        $customer=($data['type']??'')==='period_transfer';
        $headers=$customer?['تاریخ تسویه','شماره سند','نوع تسویه','مبلغ (تومان)']:['تاریخ تسویه','شرکت','شماره سند','نوع تسویه','وضعیت','مبلغ (تومان)'];
        $rows=[];
        foreach(($customer?($data['transfers']??[]):($data['rows']??[])) as $r){
            $date=AccountingService::displayBusinessDate($r['period_end']);
            $direction=TransferService::directionLabel($r['direction'],$customer);
            $amount=abs((float)$r['total_toman']);
            $values=$customer?[$date,$r['transfer_code'],$direction,$amount]:[$date,$r['company_name_snapshot'],$r['transfer_code'],$direction,$r['status']==='final'?'نهایی':'ابطال‌شده',$amount];
            $rows[]=['values'=>$values,'direction'=>TransferService::directionClass($r['direction'],$customer),'status'=>$r['status']];
        }
        return ['headers'=>$headers,'rows'=>$rows];
    }
    public static function transferSummary(array $data): array {
        $t=$data['transfer_totals']??[];$customer=($data['type']??'')==='period_transfer';
        return $customer?[
            ['label'=>'جمع پرداختی به سایت (تومان)','value'=>(float)($t['received']??0),'direction'=>'negative'],
            ['label'=>'جمع دریافتی از سایت (تومان)','value'=>(float)($t['paid']??0),'direction'=>'positive'],
        ]:[
            ['label'=>'جمع دریافت از مشتری (تومان)','value'=>(float)($t['received']??0),'direction'=>'positive'],
            ['label'=>'جمع پرداخت به مشتری (تومان)','value'=>(float)($t['paid']??0),'direction'=>'negative'],
        ];
    }
    public static function netLabel(float $net): string {return $net>0?'برد سایت':($net<0?'باخت سایت':'تسویه صفر');}
    public static function title(array $data): string {
        $type=$data['type']??'';
        if(!in_array($type,['period_transfer','transfer_history'],true))return ReportHeading626::title($data);
        return ($type==='period_transfer'?'گزارش دوره‌ای با ترانسفر':'گزارش ترانسفرها').(!empty($data['context']['companies'])?' '.implode('، ',$data['context']['companies']):'');
    }
    private static function date(string $v): string {return AccountingService::normalizeBusinessDate($v);}
    private static function columnExists(PDO $pdo,string $table,string $column): bool {$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$st->execute([$table,$column]);return (bool)$st->fetchColumn();}
}
