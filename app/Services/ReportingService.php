<?php

declare(strict_types=1);

final class ReportingService {
    public static function filters(): array {
        $pdo=Database::connection();
        $panels=$pdo->query("SELECT p.id,p.name,p.company_id,c.name company_name FROM panels p JOIN companies c ON c.id=p.company_id WHERE p.active=1 AND c.active=1 ORDER BY c.name,p.name")->fetchAll();
        return ['companies'=>AccountingService::companiesForEntry(),'panels'=>$panels,'customers'=>CustomerService::list('',true)];
    }

    public static function normalizeFilters(array $f): array {
        $f['type']=$f['type']??'cumulative';$f['type']=in_array($f['type'],['cumulative','daily','monthly','multi','detail','customer_detail','period_transfer','transfer_history','expense_history'],true)?$f['type']:'daily';
        $f['company_id']=(int)($f['company_id']??0);$f['panel_id']=(int)($f['panel_id']??0);$f['customer_id']=(int)($f['customer_id']??0);if($f['type']!=='customer_detail')$f['customer_id']=0;if($f['type']==='customer_detail'&&$f['customer_id']>0){$f['company_id']=0;$f['panel_id']=0;}
        $f['from']=trim((string)($f['from']??''));$f['to']=trim((string)($f['to']??''));
        $f['date_mode']=($f['date_mode']??'single')==='range'?'range':'single';
        if(in_array($f['type'],['period_transfer','transfer_history','expense_history'],true))$f['date_mode']='range';
        $f['date']=trim((string)($f['date']??''));
        if($f['date_mode']==='single'){$f['from']=$f['date'];$f['to']=$f['date'];}
        else $f['date']='';
        if(in_array($f['type'],['period_transfer','transfer_history','expense_history'],true)){
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
        if($type==='expense_history')return self::expenseHistory($f);
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
        $rows=[];$panelTotals=[];$tot=['received'=>0.0,'paid'=>0.0,'commission'=>0.0,'net'=>0.0];$exact=['received'=>'0.000000','paid'=>'0.000000','commission'=>'0.000000'];
        foreach($byReport as $group){
            $header=$group[0];foreach(['received','paid','commission'] as $k)$exact[$k]=TransferService::decimalAdd($exact[$k],$header['header_'.$k]);
            if(count($group)===1){
                $group[0]['panel_name']=$header['panel_id']!==null?$header['panel_name']:$header['header_panel_name'];
                $group[0]['panel_id']=$header['panel_id']??$header['header_panel_id'];
                foreach(['received','paid','commission'] as $k)$group[0][$k]=$header['header_'.$k];
            }else{
                foreach(['received','paid','commission'] as $k){
                    $sum='0.000000';foreach($group as $g)$sum=TransferService::decimalAdd($sum,$g[$k]);
                    if(abs((float)TransferService::decimalSubtract($sum,$header['header_'.$k]))>0.0001)throw new RuntimeException('اختلاف جمع سطرهای پنل با سند شماره '.$header['id'].'؛ گزارش قابل انتشار نیست.');
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
        $exactNet=TransferService::decimalSubtract($exact['received'],$exact['paid']);
        foreach($exact as $k=>$v)$tot[$k]=(float)$v;$tot['net']=(float)$exactNet;
        $tr=TransferService::settlementReport('',$f['company_id'],'final',$from,$to);
        $tr['totals']['adjusted_net']=TransferService::decimalSubtract(TransferService::decimalAdd($exactNet,$tr['totals']['paid']),$tr['totals']['received']);
        $expenses=ExpenseService::listing($f['company_id'],$from,$to,'final');
        $netAfterExpenses=TransferService::decimalSubtract($tr['totals']['adjusted_net'],$expenses['total']);
        $name=$pdo->prepare('SELECT name FROM companies WHERE id=?');$name->execute([$f['company_id']]);$company=(string)$name->fetchColumn();
        if($company==='')throw new RuntimeException('شرکت انتخاب‌شده پیدا نشد.');
        return ['type'=>'period_transfer','rows'=>$rows,'totals'=>$tot,'panel_totals'=>array_values($panelTotals),
            'transfers'=>$tr['rows'],'transfer_totals'=>$tr['totals'],'expenses'=>$expenses['rows'],'expense_total'=>$expenses['total'],'net_after_expenses'=>$netAfterExpenses,'filters'=>$f,'context'=>['companies'=>[$company],'from'=>$from,'to'=>$to]];
    }
    private static function expenseHistory(array $f): array {
        $list=ExpenseService::listing($f['company_id'],$f['from'],$f['to'],'final');
        $names=array_values(array_unique(array_column($list['rows'],'company_name_snapshot')));
        $from=$f['from']!==''?self::date($f['from']):($list['rows'][0]['expense_date']??'');
        $to=$f['to']!==''?self::date($f['to']):($list['rows'][count($list['rows'])-1]['expense_date']??'');
        return ['type'=>'expense_history','rows'=>$list['rows'],'expense_total'=>$list['total'],'expense_count'=>$list['count'],'totals'=>['received'=>0,'paid'=>0,'commission'=>0,'net'=>0],'filters'=>$f,'context'=>['companies'=>$names,'from'=>$from,'to'=>$to]];
    }
    public static function expenseTable(array $data): array {
        $history=$data['type']==='expense_history';$rows=[];
        foreach($history?$data['rows']:$data['expenses'] as $r){
            $v=[AccountingService::displayBusinessDate($r['expense_date'])];
            if($history)$v[]=$r['company_name_snapshot'];
            $v[]=$r['subject'];$v[]=$r['amount_toman'];$rows[]=$v;
        }
        return ['headers'=>$history?['تاریخ هزینه','شرکت','موضوع هزینه','مبلغ (تومان)']:['تاریخ هزینه','موضوع هزینه','مبلغ (تومان)'],'rows'=>$rows];
    }
    public static function expenseNetSummary(array $data): array {
        $v=$data['net_after_expenses'];$net=(float)$v;
        return ['label'=>'نتیجه نهایی','status'=>self::netLabel($net),'value'=>TransferService::decimalAbs($v),'net'=>$net];
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
            $amount=TransferService::decimalAbs($r['total_toman']);
            $values=$customer?[$date,$r['transfer_code'],$direction,$amount]:[$date,$r['company_name_snapshot'],$r['transfer_code'],$direction,$r['status']==='final'?'نهایی':'ابطال‌شده',$amount];
            $rows[]=['values'=>$values,'direction'=>TransferService::directionClass($r['direction'],$customer),'status'=>$r['status']];
        }
        return ['headers'=>$headers,'rows'=>$rows];
    }
    public static function transferSummary(array $data): array {
        $t=$data['transfer_totals']??[];$customer=($data['type']??'')==='period_transfer';
        return $customer?[
            ['label'=>'جمع پرداختی به سایت (تومان)','value'=>($t['received']??'0.000000'),'direction'=>'negative'],
            ['label'=>'جمع دریافتی از سایت (تومان)','value'=>($t['paid']??'0.000000'),'direction'=>'positive'],
        ]:[
            ['label'=>'جمع دریافت از مشتری (تومان)','value'=>($t['received']??'0.000000'),'direction'=>'positive'],
            ['label'=>'جمع پرداخت به مشتری (تومان)','value'=>($t['paid']??'0.000000'),'direction'=>'negative'],
        ];
    }
    /** Explicit date boundaries with LTR marks understood by legacy Excel and browsers. */
    public static function periodLabel(array $data): string {
        $f=$data['filters']??[];$c=$data['context']??[];
        $from=(string)($f['from']??'');$to=(string)($f['to']??'');
        if($from==='')$from=(string)($c['from']??'');
        if($to==='')$to=(string)($c['to']??'');
        if(($data['type']??'')==='transfer_history'&&!empty($data['rows'])){
            $dates=array_column($data['rows'],'period_end');sort($dates,SORT_STRING);
            if($from==='')$from=$dates[0]??'';if($to==='')$to=end($dates)?:'';
        }
        $fmt=static function(string $d):string {
            if($d==='')return '';
            try{$d=AccountingService::displayBusinessDate(AccountingService::normalizeBusinessDate($d));}catch(Throwable){}
            return "\u{200E}".$d."\u{200E}";
        };
        if($from!==''&&$to!==''&&$from===$to)return 'تاریخ: '.$fmt($from);
        return implode('  |  ',array_filter([$from!==''?'از تاریخ: '.$fmt($from):'',$to!==''?'تا تاریخ: '.$fmt($to):'']));
    }
    public static function settlementSummary(array $data): array {
        $summary=self::transferSummary($data);
        if(($data['type']??'')==='period_transfer'){
            $summary[]=['label'=>'جمع هزینه‌ها (تومان)','value'=>$data['expense_total'],'direction'=>'expense'];
        }
        return $summary;
    }
    public static function netLabel(float $net): string {return $net>0?'برد سایت':($net<0?'باخت سایت':'تسویه صفر');}
    public static function title(array $data): string {
        $type=$data['type']??'';
        if(!in_array($type,['period_transfer','transfer_history','expense_history'],true))return ReportHeading626::title($data);
        return (match($type){'period_transfer'=>'گزارش دوره‌ای با ترانسفر و هزینه','expense_history'=>'گزارش هزینه‌ها',default=>'گزارش ترانسفرها'}).(!empty($data['context']['companies'])?' '.implode('، ',$data['context']['companies']):'');
    }
    private static function date(string $v): string {return AccountingService::normalizeBusinessDate($v);}
    private static function columnExists(PDO $pdo,string $table,string $column): bool {$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$st->execute([$table,$column]);return (bool)$st->fetchColumn();}
}


/** Expense ledger: separate from transfer and daily-report amounts. No writes during reports. */
final class ExpenseService {
    public static function ready(): bool {
        $pdo=Database::connection();
        if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            return (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('operating_expenses','operating_expense_revisions')")->fetchColumn()===2;
        }
        return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('operating_expenses','operating_expense_revisions')")->fetchColumn()===2;
    }
    private static function requireReady(): void {
        if(!self::ready())throw new RuntimeException('سیستم هزینه‌ها هنوز فعال نشده است؛ توسعه‌دهنده باید از مرکز به‌روزرسانی آن را فعال کند.');
    }
    public static function amount(mixed $raw): string {
        $v=trim(strtr((string)$raw,array_combine(preg_split('//u','۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789'))));
        $v=str_replace('٬',',',$v);
        if(!preg_match('/^(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)$/D',$v))throw new RuntimeException('مبلغ هزینه باید عدد صحیح مثبت به تومان باشد.');
        $v=ltrim(str_replace(',','',$v),'0');
        if($v===''||strlen($v)>15)throw new RuntimeException('مبلغ هزینه خارج از محدوده مجاز است.');
        return $v;
    }
    public static function date(string $raw): string {
        if(trim($raw)==='')throw new RuntimeException('تاریخ هزینه را انتخاب کن.');
        $date=AccountingService::normalizeBusinessDate($raw);
        $normalized=strtr(trim($raw),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $parts=preg_split('~[./-]~',$normalized);if((int)$parts[0]<1700&&AccountingService::displayBusinessDate($date)!==sprintf('%04d/%02d/%02d',(int)$parts[0],(int)$parts[1],(int)$parts[2]))throw new RuntimeException('تاریخ شمسی هزینه نامعتبر است.');
        return $date;
    }
    public static function get(int $id): ?array {
        self::requireReady();
        $st=Database::connection()->prepare('SELECT * FROM operating_expenses WHERE id=?');$st->execute([$id]);return $st->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public static function listing(int $company=0,string $from='',string $to='',string $status='final',string $q=''): array {
        self::requireReady();$pdo=Database::connection();$where=['1=1'];$args=[];
        if($company>0){$where[]='company_id=?';$args[]=$company;}
        if($from!==''){$from=self::date($from);$where[]='expense_date>=?';$args[]=$from;}
        if($to!==''){$to=self::date($to);$where[]='expense_date<=?';$args[]=$to;}
        if($from!==''&&$to!==''&&$from>$to)throw new RuntimeException('تاریخ شروع بعد از پایان است.');
        if(!in_array($status,['','final','void'],true))throw new RuntimeException('وضعیت هزینه نامعتبر است.');
        if($status!==''){$where[]='status=?';$args[]=$status;}
        if(trim($q)!==''){$where[]='subject LIKE ?';$args[]='%'.trim($q).'%';}
        $st=$pdo->prepare('SELECT * FROM operating_expenses WHERE '.implode(' AND ',$where).' ORDER BY expense_date ASC,id ASC');$st->execute($args);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);$total='0.000000';$count=0;
        foreach($rows as $r)if($r['status']==='final'){$total=TransferService::decimalAdd($total,$r['amount_toman']);$count++;}
        return ['rows'=>$rows,'total'=>$total,'count'=>$count];
    }
    public static function save(array $input,int $uid): int {
        self::requireReady();if($uid<=0)throw new RuntimeException('کاربر نامعتبر است.');
        $id=max(0,(int)($input['expense_id']??0));$company=(int)($input['company_id']??0);
        $date=self::date((string)($input['expense_date']??''));$amount=self::amount($input['amount_toman']??'');
        $subject=trim((string)($input['subject']??''));if($subject===''||mb_strlen($subject)>250)throw new RuntimeException('موضوع هزینه باید بین ۱ و ۲۵۰ نویسه باشد.');
        $pdo=Database::connection();$lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$pdo->beginTransaction();
        try{
            $before=null;
            if($id){$st=$pdo->prepare('SELECT * FROM operating_expenses WHERE id=?'.$lock);$st->execute([$id]);$before=$st->fetch(PDO::FETCH_ASSOC);
                if(!$before||$before['status']!=='final'||(int)$before['revision_no']!==(int)($input['revision_no']??0))throw new RuntimeException('سند تغییر کرده یا ابطال شده است؛ دوباره آن را باز کن.');
                if((int)$before['company_id']!==$company)throw new RuntimeException('شرکت سند قابل تغییر نیست.');
            }
            $st=$pdo->prepare('SELECT name,active,archived_at FROM companies WHERE id=?'.$lock);$st->execute([$company]);$c=$st->fetch(PDO::FETCH_ASSOC);
            if(!$c||(!$before&&(!(int)$c['active']||$c['archived_at']!==null)))throw new RuntimeException('شرکت فعال انتخاب کن.');
            if($before){$pdo->prepare('UPDATE operating_expenses SET expense_date=?,amount_toman=?,subject=?,updated_by=?,revision_no=revision_no+1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$date,$amount,$subject,$uid,$id]);}
            else{$pdo->prepare('INSERT INTO operating_expenses(company_id,company_name_snapshot,expense_date,amount_toman,subject,created_by,updated_by) VALUES(?,?,?,?,?,?,?)')->execute([$company,$c['name'],$date,$amount,$subject,$uid,$uid]);$id=(int)$pdo->lastInsertId();}
            self::revision($pdo,$id,$before?'updated':'created',$before,$uid);$pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function void(int $id,int $revision,int $uid): void {
        self::requireReady();if($uid<=0)throw new RuntimeException('کاربر نامعتبر است.');
        $pdo=Database::connection();$lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$pdo->beginTransaction();
        try{$st=$pdo->prepare('SELECT * FROM operating_expenses WHERE id=?'.$lock);$st->execute([$id]);$before=$st->fetch(PDO::FETCH_ASSOC);
            if(!$before||$before['status']!=='final'||(int)$before['revision_no']!==$revision)throw new RuntimeException('سند تغییر کرده یا ابطال شده است.');
            $pdo->prepare("UPDATE operating_expenses SET status='void',revision_no=revision_no+1,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$uid,$id]);
            self::revision($pdo,$id,'voided',$before,$uid);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private static function revision(PDO $pdo,int $id,string $action,?array $before,int $uid): void {
        $st=$pdo->prepare('SELECT * FROM operating_expenses WHERE id=?');$st->execute([$id]);$after=$st->fetch(PDO::FETCH_ASSOC);
        $encode=static fn($v)=>json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $pdo->prepare('INSERT INTO operating_expense_revisions(expense_id,action_type,before_json,after_json,changed_by) VALUES(?,?,?,?,?)')->execute([$id,$action,$before===null?null:$encode($before),$encode($after),$uid]);
    }
}
