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
            self::revision($pdo,$id,$before?'updated':'created',$before,$uid);$pdo->commit();if(class_exists('Logger'))Logger::audit($before?'expense.updated':'expense.created',['expense_id'=>$id,'company_id'=>$company,'user_id'=>$uid]);return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function void(int $id,int $revision,int $uid): void {
        self::requireReady();if($uid<=0)throw new RuntimeException('کاربر نامعتبر است.');
        $pdo=Database::connection();$lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$pdo->beginTransaction();
        try{$st=$pdo->prepare('SELECT * FROM operating_expenses WHERE id=?'.$lock);$st->execute([$id]);$before=$st->fetch(PDO::FETCH_ASSOC);
            if(!$before||$before['status']!=='final'||(int)$before['revision_no']!==$revision)throw new RuntimeException('سند تغییر کرده یا ابطال شده است.');
            $pdo->prepare("UPDATE operating_expenses SET status='void',revision_no=revision_no+1,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$uid,$id]);
            self::revision($pdo,$id,'voided',$before,$uid);$pdo->commit();if(class_exists('Logger'))Logger::audit('expense.voided',['expense_id'=>$id,'company_id'=>(int)$before['company_id'],'user_id'=>$uid]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private static function revision(PDO $pdo,int $id,string $action,?array $before,int $uid): void {
        $st=$pdo->prepare('SELECT * FROM operating_expenses WHERE id=?');$st->execute([$id]);$after=$st->fetch(PDO::FETCH_ASSOC);
        $encode=static fn($v)=>json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $pdo->prepare('INSERT INTO operating_expense_revisions(expense_id,action_type,before_json,after_json,changed_by) VALUES(?,?,?,?,?)')->execute([$id,$action,$before===null?null:$encode($before),$encode($after),$uid]);
    }
}


/**
 * Maintenance gate loaded by the shared HTTP bootstrap via this service.
 * Kept here for the existing 1.3 updater's cumulative path allowlist.
 * Uses the existing settings table; no financial rows or new schema.
 */
final class PopupP21 {
    public static function meta(array $a=[]):array{
        $out=[];foreach(['subtitle'=>100,'version'=>50,'hours'=>24,'patches'=>16,'range'=>50] as $key=>$max){
            $v=$a[$key]??'';if(!is_string($v)||mb_strlen(trim($v))>$max||preg_match('/\R/u',$v))throw new RuntimeException('اطلاعات نمایشی نامعتبر است.');$out[$key]=trim($v);
        }
        $seconds=$a['delay_seconds']??15;if(!is_int($seconds)||$seconds<10||$seconds>30)throw new RuntimeException('زمان مطالعه بین ۱۰ تا ۳۰ ثانیه است.');
        $mode=$a['content_mode']??'list';if(!in_array($mode,['list','text'],true))throw new RuntimeException('نوع متن نامعتبر است.');
        return $out+['delay_seconds'=>$seconds,'content_mode'=>$mode];
    }
    public static function post(array $p,string $prefix):array{
        $a=[];foreach(['subtitle','version','hours','patches','range','content_mode'] as $k)if(isset($p[$prefix.$k]))$a[$k]=(string)$p[$prefix.$k];
        if(isset($p[$prefix.'delay_seconds']))$a['delay_seconds']=(int)$p[$prefix.'delay_seconds'];
        return self::meta($a);
    }
    public static function items(array $a):array{
        return array_map(static function($v){$p=explode(' | ',(string)$v,2);return count($p)===2?[trim($p[0]),trim($p[1])]:[trim($p[0]),''];},$a);
    }
    public static function installedVersion():string{
        $file=dirname(__DIR__,2).'/storage/updates/current.json';
        $state=is_file($file)?json_decode((string)file_get_contents($file),true):null;
        $release=is_array($state)?($state['release']??''):'';
        return is_string($release)&&preg_match('/^0\.6\.\d+-p\d+$/D',$release)?$release:'0.6.53';
    }
    public static function mcpItems(array $items):array{
        if(!array_is_list($items)||count($items)<1||count($items)>24)throw new RuntimeException('Invalid item count');
        $out=[];
        foreach($items as $item){
            if(is_array($item)){
                $title=$item['title']??null;$description=$item['description']??'';
                if(array_diff(array_keys($item),['title','description'])||!is_string($title)||!is_string($description)||trim($title)===''||mb_strlen(trim($title))>100||mb_strlen(trim($description))>280||str_contains($title,' | ')||preg_match('/\R/u',$title.$description))throw new RuntimeException('Invalid structured item');
                $item=trim($title).(trim($description)!==''?' | '.trim($description):'');
            }
            if(!is_string($item)||trim($item)===''||mb_strlen(trim($item))>400||preg_match('/\R/u',$item))throw new RuntimeException('Invalid announcement item');
            $out[]=trim($item);
        }
        return $out;
    }
    public static function structured(array $items):array{
        return array_map(static fn($pair)=>['title'=>$pair[0],'description'=>$pair[1]],self::items($items));
    }
    public static function fontCss():string{
        $root=dirname(__DIR__,2).'/assets/fonts/';$css='';
        foreach(['400','700'] as $w)if(is_file($root.'vazirmatn-'.$w.'.woff2'))$css.='@font-face{font-family:Vazirmatn;src:url("assets/fonts/vazirmatn-'.$w.'.woff2") format("woff2");font-weight:'.$w.';font-display:swap}';
        foreach(['Iranian-Sans.ttf','Iranian Sans.ttf'] as $name)if(is_file($root.$name)){$css.='@font-face{font-family:IRANSansLocal;src:url("assets/fonts/'.rawurlencode($name).'") format("truetype");font-weight:400 700;font-display:swap}';break;}
        return $css;
    }
}

final class MaintenanceP13 {
    public const UNTIL='maintenance_until_p13';
    public const ANNOUNCEMENT='maintenance_announcement_p13';
    public static function read(): array {
        $st=Database::connection()->prepare('SELECT setting_key,setting_value FROM settings WHERE setting_key IN (?,?)');
        $st->execute([self::UNTIL,self::ANNOUNCEMENT]);$r=$st->fetchAll(PDO::FETCH_KEY_PAIR);
        $until=(int)($r[self::UNTIL]??0);
        $a=json_decode((string)($r[self::ANNOUNCEMENT]??''),true);
        return ['until'=>max(0,$until),'announcement'=>is_array($a)?$a:[]];
    }
    public static function active(array $state,?int $now=null): bool {return (int)$state['until']>($now??time());}
    public static function parseNotes(string $raw): array {
        $lines=preg_split('/\R/u',trim($raw))?:[];$out=[];
        foreach($lines as $line){$line=trim($line);if($line==='')continue;if(mb_strlen($line)>400)throw new RuntimeException('هر مورد حداکثر ۴۰۰ نویسه باشد.');$out[]=$line;}
        if(count($out)<1||count($out)>24)throw new RuntimeException('بین ۱ تا ۲۴ مورد، هر مورد در یک خط وارد کن.');
        return $out;
    }
    private static function write(PDO $pdo,string $key,string $value): void {
        $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
          ?'INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value'
          :'INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)';
        $pdo->prepare($sql)->execute([$key,$value]);
    }
    public static function start(int $minutes,string $title,string $raw,int $uid,?int $now=null,?int $exactUntil=null,int $showCount=1,array $meta=[]): array {
        if($uid<=0||$minutes<1||$minutes>10080)throw new RuntimeException('مدت تعمیرات باید بین ۱ دقیقه و ۷ روز باشد.');
        $title=trim($title);if($title===''||mb_strlen($title)>100)throw new RuntimeException('عنوان اعلان حداکثر ۱۰۰ نویسه باشد.');
        $items=self::parseNotes($raw);$now=$now??time();$until=$exactUntil??($now+$minutes*60);if($until<=$now||$until>$now+604800||$showCount<1||$showCount>5)throw new RuntimeException('زمان پایان یا تعداد نمایش نامعتبر است.');
        $a=['id'=>bin2hex(random_bytes(12)),'title'=>$title,'items'=>$items,'item_details'=>PopupP21::structured($items),'initiator_uid'=>$uid,'created_at'=>$now,'show_count'=>$showCount,'presentation'=>PopupP21::meta($meta)];
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            self::lockUntil($pdo);if(self::active(self::read(),$now))throw new RuntimeException('به‌روزرسانی دیگری فعال است.');
            self::write($pdo,self::ANNOUNCEMENT,json_encode($a,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            self::write($pdo,self::UNTIL,(string)$until);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['until'=>$until,'announcement'=>$a];
    }
    private static function lockUntil(PDO $pdo):void {
        $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)':'INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)';
        $pdo->prepare($sql)->execute([self::UNTIL,'0']);
        $st=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$st->execute([self::UNTIL]);$st->fetchColumn();
    }
    public static function stop(string $expectedId,int $uid):array {
        if($uid<=0||!preg_match('/^[a-f0-9]{24}$/D',$expectedId))throw new RuntimeException('Invalid maintenance confirmation');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{self::lockUntil($pdo);$state=self::read();if(!hash_equals((string)($state['announcement']['id']??''),$expectedId))throw new RuntimeException('Maintenance ID changed');if(self::active($state)){self::write($pdo,self::UNTIL,'0');$stopped=true;}else{$stopped=false;}$pdo->commit();}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        if($stopped&&class_exists('Logger'))Logger::audit('developer.maintenance_stopped',['user_id'=>$uid,'announcement_id'=>$expectedId,'until'=>$state['until']]);
        return ['stopped'=>$stopped,'active'=>false,'announcement_id'=>$expectedId];
    }
    public static function describe(array $state,?int $now=null):array {
        $now=$now??time();$until=(int)$state['until'];$tz=new DateTimeZone('Asia/Tehran');
        return ['active'=>self::active($state,$now),'until'=>$until,'end_tehran'=>$until>0?(new DateTimeImmutable('@'.$until))->setTimezone($tz)->format(DATE_ATOM):null,'remaining_seconds'=>max(0,$until-$now),'announcement'=>$state['announcement']];
    }
    public static function prepareMcp(array $args,?int $now=null):array {
        $now=$now??time();$mode=$args['mode']??null;$title=$args['title']??null;$items=$args['items']??null;$count=$args['show_count']??null;
        if(!in_array($mode,['test','exact'],true)||!is_string($title)||trim($title)===''||mb_strlen(trim($title))>100||!is_array($items)||!array_is_list($items)||count($items)<1||count($items)>24||!is_int($count)||$count<1||$count>5)throw new RuntimeException('Invalid maintenance details');
        $items=PopupP21::mcpItems($items);
        if($mode==='exact'){if(!is_string($args['date']??null)||!is_string($args['clock']??null))throw new RuntimeException('Exact Tehran date and time required');$until=self::exactDeadline($args['date'],$args['clock'],$now);}
        else{if(isset($args['date'])||isset($args['clock']))throw new RuntimeException('Test mode does not accept a date or clock');$until=$now+60;}
        return ['mode'=>$mode,'until'=>$until,'title'=>trim($title),'items'=>$items,'item_details'=>PopupP21::structured($items),'show_count'=>$count,'presentation'=>PopupP21::meta($args)];
    }
    private static function seenKey(int $uid): string {return 'maintenance_seen_p13_'.$uid;}
    public static function pending(int $uid): ?array {
        if($uid<=0)return null;
        $state=self::read();$a=$state['announcement'];
        if(self::active($state)||empty($a['id'])||empty($a['items'])||!is_array($a['items']))return null;
        $st=Database::connection()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$st->execute([self::seenKey($uid)]);
        $raw=$st->fetchColumn();$seen=json_decode((string)$raw,true);$count=$raw===$a['id']?1:(is_array($seen)&&($seen['id']??'')===$a['id']?(int)($seen['count']??0):0);return $count>=max(1,min(5,(int)($a['show_count']??1)))?null:$a;
    }
    public static function acknowledge(int $uid,string $id): void {
        $a=self::pending($uid);
        if(!$a||!hash_equals((string)$a['id'],$id))return;
        $pdo=Database::connection();$pdo->beginTransaction();try{$key=self::seenKey($uid);$lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';self::writeSeenDefault($pdo,$key);$st=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?'.$lock);$st->execute([$key]);$raw=$st->fetchColumn();$seen=json_decode((string)$raw,true);$count=$raw===$id?1:(is_array($seen)&&($seen['id']??'')===$id?(int)($seen['count']??0):0);if($count<(int)($a['show_count']??1))self::write($pdo,$key,json_encode(['id'=>$id,'count'=>$count+1],JSON_THROW_ON_ERROR));$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}if(class_exists('Logger'))Logger::audit('developer.announcement_acknowledged',['user_id'=>$uid,'announcement_id'=>$id]);
    }
    private static function writeSeenDefault(PDO $pdo,string $key):void {
        $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)':'INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)';
        $pdo->prepare($sql)->execute([$key,'']);
    }
    public static function exactDeadline(string $date,string $clock,?int $now=null):int {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!preg_match('/^\d{2}:\d{2}$/D',$clock))throw new RuntimeException('روز و ساعت پایان را مشخص کن.');
        $tz=new DateTimeZone('Asia/Tehran');$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$clock,$tz);
        if(!$d||$d->format('Y-m-d H:i')!==$date.' '.$clock)throw new RuntimeException('تاریخ یا ساعت پایان معتبر نیست.');
        $n=$now??time();$end=$d->getTimestamp();if($end<=$n||$end>$n+604800)throw new RuntimeException('زمان پایان باید در هفت روز آینده باشد.');return $end;
    }
    public static function pageHtml(int $until,bool $preview=false):string {
        $remaining=max(0,$until-time());$local=(new DateTimeImmutable('@'.$until))->setTimezone(new DateTimeZone('Asia/Tehran'));$end=e($local->format('H:i'));$day=e($local->format('Y-m-d'));
        $previewFlag=$preview?'true':'false';
        $fonts=PopupP21::fontCss();
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>سلطان حساب | در حال به‌روزرسانی</title><style>'.$fonts.'
*{box-sizing:border-box}html{color-scheme:light}body{margin:0;min-height:100vh;min-height:100dvh;display:grid;place-items:center;padding:clamp(12px,4vw,48px);font-family:Vazirmatn,IRANSansLocal,Tahoma,Arial,sans-serif;color:#302047;background:#fcfaff}main{position:relative;width:min(100%,1080px);min-height:min(640px,calc(100dvh - 80px));padding:clamp(28px,5vw,62px) clamp(18px,6vw,64px);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;border:1px solid #f0e7f9;border-radius:30px;background:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20240%20190%22%3E%3Cpath%20fill%3D%22%23eaddfa%22%20fill-opacity%3D%22.64%22%20d%3D%22M0%200H240C130%2010%2095%20165%200%20182Z%22%2F%3E%3Cpath%20d%3D%22M-12%20150C90%20148%20108%2035%20199%20-10%22%20stroke%3D%22%238451db%22%20stroke-width%3D%221.4%22%20fill%3D%22none%22%2F%3E%3Cpath%20d%3D%22M-12%20173C93%20168%20130%2032%20220%20-10%22%20stroke%3D%22%23fff%22%20stroke-width%3D%222%22%20fill%3D%22none%22%2F%3E%3C%2Fsvg%3E") left top/auto 31% no-repeat,url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20250%20190%22%3E%3Cdefs%3E%3ClinearGradient%20id%3D%22g%22%3E%3Cstop%20stop-color%3D%22%239343db%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%235011ad%22%2F%3E%3C%2FlinearGradient%3E%3C%2Fdefs%3E%3Cpath%20fill%3D%22url(%23g)%22%20d%3D%22M65%20190C148%20153%20145%2083%20250%2080V190Z%22%2F%3E%3Cpath%20d%3D%22M30%20190C136%20151%20145%2062%20260%2065%22%20stroke%3D%22%237722cb%22%20stroke-width%3D%221.6%22%20fill%3D%22none%22%2F%3E%3Cpath%20d%3D%22M2%20190C110%20145%20150%2035%20260%2036%22%20stroke%3D%22%23b793ed%22%20stroke-width%3D%221.4%22%20fill%3D%22none%22%2F%3E%3Cpath%20d%3D%22M55%20190C142%20150%20149%2075%20260%2074%22%20stroke%3D%22%23fff%22%20stroke-width%3D%222%22%20fill%3D%22none%22%2F%3E%3C%2Fsvg%3E") right bottom/auto 29% no-repeat,radial-gradient(ellipse at 0 0,#eee7fc,transparent 38%),radial-gradient(ellipse at 100% 100%,#eee8fa,transparent 43%),linear-gradient(145deg,#fdfbff,#fff 54%,#faf5ff);box-shadow:0 12px 35px #57229a13,0 3px 8px #6b399315;overflow:hidden}main:before{content:"";position:absolute;top:16px;right:22px;width:26%;height:2px;background:linear-gradient(90deg,transparent,#cda4f4,#8516db);border-radius:4px}.mark{width:clamp(68px,9vw,108px);height:clamp(68px,9vw,108px);object-fit:contain;display:block;margin:0 auto 6px}.brand,footer{display:flex;align-items:center;justify-content:center;gap:14px}.brand{font-size:clamp(17px,2vw,23px);font-weight:700;color:#32125b}.brand:before,.brand:after,footer:before,footer:after{content:"";width:clamp(22px,4vw,55px);height:2px;background:linear-gradient(90deg,#c795ef,#8d23d7);flex:none}h1{font-size:clamp(25px,4.6vw,48px);line-height:1.5;margin:22px 0 10px;font-weight:700;color:#32134f}h1 span{color:#8120d4;background:linear-gradient(110deg,#5908a6,#aa28ef);background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent}p{font-size:clamp(13px,1.6vw,18px);line-height:2;color:#514760;margin:0 auto}.timer{direction:ltr;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:clamp(8px,3vw,30px);width:min(100%,550px);margin:clamp(22px,4vw,34px) auto 24px}.timer>div{position:relative;padding:clamp(12px,2vw,20px) 4px;border:1px solid #f0e7fa;background:linear-gradient(145deg,#fff,#f7effc);border-radius:clamp(15px,3vw,28px);box-shadow:0 4px 10px #7d37bc16,inset 0 1px 0 #fff}.timer>div+div:before{content:":";position:absolute;left:calc(clamp(8px,3vw,30px) / -2);transform:translateX(-50%);top:35%;color:#8a1bd8;font-weight:700}.timer b{display:block;font-size:clamp(31px,5vw,48px);font-weight:700;color:#7818c3;font-variant-numeric:tabular-nums;line-height:1.2}.timer small{font-size:clamp(12px,1.5vw,16px);color:#514760;display:block;margin-top:9px}.reopen{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:10px 16px;border:1px solid #f0e6fa;background:linear-gradient(145deg,#fff,#f4eafa);border-radius:24px;padding:12px 22px;box-shadow:0 4px 12px #7d37bc16;font-size:clamp(13px,1.6vw,17px);color:#514760}.reopen .label{display:flex;align-items:center;gap:8px}.reopen svg{width:25px;height:25px;color:#851ed5}.end{color:#6d14b2;font-size:clamp(16px,2vw,22px);font-weight:700;border-right:1px solid #b793ed;padding-right:16px}.date{font-size:14px;color:#514760;margin-top:14px;font-weight:700}footer{font-size:clamp(11px,1.35vw,15px);color:#514760;margin-top:32px;line-height:1.9}.preview{position:fixed;top:8px;right:8px;background:#f2e7ff;color:#4a207c;padding:5px 9px;border-radius:8px;font-size:11px;z-index:2}@media(max-width:600px){body{padding:12px}main{min-height:calc(100dvh - 24px);padding:32px 15px;border-radius:25px;background-size:auto 22%,auto 20%,auto,auto,auto}h1{margin:20px 0 12px}.timer{margin:25px auto 22px;gap:10px}.timer>div+div:before{left:-5px}.reopen{flex-direction:column;gap:8px;width:100%;max-width:310px;padding:13px 8px}.end{border:0;padding:0}footer{margin-top:28px;gap:8px}main:before{display:none}}@media(max-height:550px){main{min-height:0;padding-top:24px;padding-bottom:24px}.mark{width:54px;height:54px}h1{margin-top:10px}.timer{margin:12px auto}footer{margin-top:14px}}
/* P22: the maintenance surface is the page, not a centered card. */
html,body{width:100%;min-height:100%;margin:0}body{display:block;padding:0!important;background:#fff}
main{width:100%;max-width:none;min-height:100vh;min-height:100dvh;border:0;border-radius:0;box-shadow:none;overflow:visible;padding:clamp(32px,6vh,76px) clamp(16px,7vw,100px);background-size:auto 38%,auto 37%,auto,auto,auto}
main:before{top:clamp(16px,3vw,44px);right:clamp(16px,4vw,60px);width:18%}
.mark{width:clamp(72px,10vw,118px);height:clamp(72px,10vw,118px)}
@media(max-width:600px){main{min-height:100vh;min-height:100dvh;padding:40px 16px max(38px,env(safe-area-inset-bottom));background-size:auto 21%,auto 20%,auto,auto,auto}main:before{display:none}.timer{max-width:390px}.reopen{max-width:350px}footer{max-width:100%;gap:8px}h1{font-size:clamp(24px,6.2vw,36px)}}
@media(max-height:550px) and (min-width:601px){main{min-height:100vh;min-height:100dvh;padding:24px 16px}.mark{width:54px;height:54px}.timer{margin:12px auto}footer{margin-top:14px}}

/* P23 reference: royal violet, larger central typography, fine upper/lower rails. */
main{isolation:isolate;background-size:auto min(26vh,220px),auto min(25vh,210px),auto,auto,auto!important;padding:clamp(38px,6vh,72px) 24px}
main:before,main:after{content:"";position:absolute;height:2px;width:clamp(120px,27vw,420px);border-radius:8px;display:block;pointer-events:none}
main:before{top:25px;right:32px;background:linear-gradient(to right,transparent,#bca3ed 45%,#7622c9 82%,#9c6de1 98%)}
main:after{bottom:25px;left:32px;background:linear-gradient(to right,transparent,#bca3ed 20%,#9b6ade 45%,transparent)}
.rail-top,.rail-bottom{position:absolute;height:6px;width:clamp(28px,4vw,54px);background:#7721ca;border-radius:8px;pointer-events:none}
.rail-top{right:56px;top:23px}.rail-bottom{left:57px;bottom:23px}
.mark{width:clamp(105px,12vw,150px);height:clamp(105px,12vw,150px);margin-bottom:8px}
.brand{font-size:clamp(20px,2.5vw,28px);font-weight:700;line-height:1.6;color:#27114c}
h1{font-size:clamp(35px,5vw,64px);line-height:1.5;margin:clamp(17px,3vh,28px) 0 10px;font-weight:700;color:#281344}
h1 span{background:linear-gradient(100deg,#6b15bd,#9d27e6);background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent}
p{font-size:clamp(17px,2vw,23px);font-weight:500;color:#51475e;line-height:1.9}
.timer{width:min(100%,660px);gap:clamp(14px,3vw,38px);margin:clamp(22px,4vh,40px) auto 22px}
.timer>div{padding:18px 8px;border-radius:25px;background:linear-gradient(145deg,#fff,#f2ebfc);box-shadow:0 5px 12px #6833b223}
.timer b{font-size:clamp(40px,5vw,62px);color:#661bbd;font-weight:700}
.timer small{font-size:clamp(15px,1.8vw,20px);font-weight:600;color:#51475e;margin-top:7px}
.reopen{border-radius:28px;font-size:clamp(15px,1.7vw,21px);padding:14px 22px;background:linear-gradient(110deg,#fff,#f3edfc);font-weight:500}.end{font-size:clamp(18px,2vw,26px);font-weight:700;color:#5912a7}
.date{font-size:17px;font-weight:700;margin-top:12px}footer{font-size:clamp(14px,1.5vw,19px);margin-top:clamp(18px,3.5vh,36px)}
@media(max-width:600px){main{padding:40px 16px max(45px,env(safe-area-inset-bottom));background-size:auto 118px,auto 100px,auto,auto,auto!important}.mark{width:105px;height:105px}.brand{font-size:20px}h1{font-size:clamp(24px,6.2vw,36px);margin:20px 0 11px}p{font-size:15px;line-height:1.95}.timer{width:min(100%,390px);gap:12px;margin:26px auto 23px}.timer>div{padding:13px 3px;border-radius:20px}.timer b{font-size:clamp(32px,10vw,45px)}.timer small{font-size:14px}.reopen{max-width:350px;padding:12px 9px;font-size:15px}.end{font-size:20px}.date{font-size:15px}footer{font-size:12px;margin-top:30px}main:before{display:none}.rail-top{display:none}main:after{left:17px;bottom:20px;width:55%}.rail-bottom{left:33px;bottom:18px;width:35px}}
@media(max-height:700px) and (min-width:601px){main{padding:26px 18px}.mark{width:86px;height:86px}h1{font-size:clamp(30px,4vw,48px);margin:10px 0 6px}p{font-size:17px}.timer{margin:17px auto 14px}.timer>div{padding:11px 6px}.timer b{font-size:43px}.timer small{font-size:15px}.reopen{padding:9px 16px}footer{margin-top:16px}}
</style></head><body>'.($preview?'<span class="preview">پیش‌نمایش · سایت فعال است</span>':'').'<main><span class="rail-top" aria-hidden="true"></span><span class="rail-bottom" aria-hidden="true"></span><img class="mark" src="assets/brand/pwa-192.png" alt=""><div class="brand">سلطان حساب</div><h1>در حال <span>به‌روزرسانی</span> هستیم</h1><p>در حال آماده‌سازی تجربه‌ای بهتر برای شما هستیم.<br>پس از پایان به‌روزرسانی، ورود دوباره امکان‌پذیر خواهد بود.</p><div class="timer" aria-label="زمان باقی‌مانده"><div><b id="hh">00</b><small>ساعت</small></div><div><b id="mm">00</b><small>دقیقه</small></div><div><b id="ss">00</b><small>ثانیه</small></div></div><div class="reopen"><div class="label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18m-13 4h.01m4 0h.01m4 0h.01m-8 3h.01m4 0h.01"/></svg> زمان تقریبی بازگشایی:</div><div class="end">ساعت <b dir="ltr">'.$end.'</b> به وقت تهران</div></div><div class="date" dir="ltr">'.$day.'</div><footer>از همراهی و شکیبایی شما سپاسگزاریم.</footer></main><script>const preview='.$previewFlag.',initial='.$remaining.',deadline=performance.now()+initial*1000,pad=n=>String(n).padStart(2,"0");function tick(){const n=Math.max(0,Math.ceil((deadline-performance.now())/1000));document.getElementById("hh").textContent=pad(Math.floor(n/3600));document.getElementById("mm").textContent=pad(Math.floor(n/60)%60);document.getElementById("ss").textContent=pad(n%60);if(n===0&&!preview)location.replace("index.php?page=login");}tick();setInterval(tick,250);</script></body></html>';
    }

    public static function ownerSessionMatches(array $state,array $session,?array $user,?int $now=null):bool {
        $a=$state['announcement']??[];$marker=$session['maintenance_owner_p23']??null;
        return self::active($state,$now)&&is_array($marker)&&is_array($user)
            &&($user['role']??'')==='developer'&&(int)($user['id']??0)>0
            &&(int)$user['id']===(int)($a['initiator_uid']??0)
            &&(int)$user['id']===(int)($marker['uid']??0)
            &&is_string($marker['id']??null)&&is_string($a['id']??null)
            &&preg_match('/^[a-f0-9]{24}$/D',$a['id'])===1&&hash_equals($a['id'],$marker['id']);
    }
    public static function currentOwnerSession(array $state):bool {
        if(!isset($_SESSION['maintenance_owner_p23'])||!class_exists('Auth'))return false;
        return self::ownerSessionMatches($state,$_SESSION,Auth::user());
    }
    public static function gate(): void {
        $script=basename((string)($_SERVER['SCRIPT_FILENAME']??$_SERVER['SCRIPT_NAME']??''));
        // Installer/CLI are not public application sessions. Cron is paused with the other HTTP endpoints.
        if(PHP_SAPI==='cli'||PHP_SAPI==='phpdbg'||str_contains((string)($_SERVER['SCRIPT_NAME']??''),'/install/'))return;
        $state=self::read();if(!self::active($state)){unset($_SESSION['maintenance_owner_p23']);if($state['until']>0){$st=Database::connection()->prepare('UPDATE settings SET setting_value=? WHERE setting_key=? AND setting_value=?');$st->execute(['0',self::UNTIL,(string)$state['until']]);if($st->rowCount()&&class_exists('Logger'))Logger::system('developer.maintenance_finished',['until'=>$state['until']]);}return;}
        // Maintenance exception is only transport-level: MCP enforces bearer, role, scope and an operation allowlist below.
        if($script==='mcp.php')return;
        // Existing OAuth refresh tokens must be renewable during longer maintenance; interactive authorization remains blocked.
        if($script==='oauth.php'&&($_SERVER['REQUEST_METHOD']??'')==='POST'&&($_GET['endpoint']??'')==='token'&&($_POST['grant_type']??'')==='refresh_token')return;
        if(!in_array($script,['oauth.php','mcp.php','cron_soltan_backup.php'],true)&&self::currentOwnerSession($state))return;
        $now=time();$remaining=max(1,$state['until']-$now);
        // Clear application authentication on the next request, not the stored user record.
        unset($_SESSION['user'],$_SESSION['_security_version'],$_SESSION['_csrf'],$_SESSION['maintenance_owner_p23']);
        http_response_code(503);header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('Pragma: no-cache');header('Retry-After: '.$remaining);header('X-Robots-Tag: noindex, nofollow');
        if(in_array($script,['mcp.php','oauth.php'],true)||str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($script==='mcp.php'?['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32000,'message'=>'Maintenance','data'=>['retry_after'=>$remaining]]]:['error'=>'temporarily_unavailable','retry_after'=>$remaining]);exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo self::pageHtml($state['until']);exit;
    }
}

/** Independent developer announcements; never change maintenance state or financial records. */
final class AnnouncementP20 {
    private const KEY='announcement_general_p20';
    private static function put(PDO $pdo,string $key,string $value):void{
        $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value':'INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)';
        $pdo->prepare($sql)->execute([$key,$value]);
    }
    public static function status():array{
        $s=Database::connection()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([self::KEY]);$a=json_decode((string)$s->fetchColumn(),true);return is_array($a)?$a:[];
    }
    public static function publish(string $title,string $raw,string $kind,int $count,int $days,int $target,int $uid,array $meta=[]):array{
        $title=trim($title);
        if($uid<1||$title===''||mb_strlen($title)>100||!in_array($kind,['notice','suggestion','update'],true)||$count<1||$count>5||$days<1||$days>30||$target<0)throw new RuntimeException('تنظیمات اطلاعیه نامعتبر است.');
        $items=MaintenanceP13::parseNotes($raw);$pdo=Database::connection();
        if($target>0){$s=$pdo->prepare('SELECT COUNT(*) FROM users WHERE id=? AND active=1');$s->execute([$target]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('کاربر فعال پیدا نشد.');}
        $now=time();$a=['id'=>bin2hex(random_bytes(12)),'title'=>$title,'items'=>$items,'item_details'=>PopupP21::structured($items),'kind'=>$kind,'show_count'=>$count,'target_user_id'=>$target,'created_at'=>$now,'expires_at'=>$now+$days*86400,'enabled'=>true,'presentation'=>PopupP21::meta($meta)];
        self::put($pdo,self::KEY,json_encode($a,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        if(class_exists('Logger'))Logger::audit('developer.announcement_published',['user_id'=>$uid,'announcement_id'=>$a['id'],'target_user_id'=>$target]);
        return $a;
    }
    public static function cancel(string $id,int $uid):array{
        if($uid<1||!preg_match('/^[a-f0-9]{24}$/D',$id))throw new RuntimeException('شناسه اطلاعیه نامعتبر است.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$s=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$s->execute([self::KEY]);$a=json_decode((string)$s->fetchColumn(),true);
        if(!is_array($a)||!hash_equals((string)($a['id']??''),$id))throw new RuntimeException('اطلاعیه تغییر کرده است.');
        $a['enabled']=false;self::put($pdo,self::KEY,json_encode($a,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$pdo->commit();}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        if(class_exists('Logger'))Logger::audit('developer.announcement_cancelled',['user_id'=>$uid,'announcement_id'=>$id]);return $a;
    }
    private static function seenKey(int $uid):string{return 'announcement_seen_p20_'.$uid;}
    private static function countSeen($raw,string $id):int{$s=json_decode((string)$raw,true);return is_array($s)&&($s['id']??'')===$id?(int)($s['count']??0):0;}
    public static function pending(int $uid):?array{
        if($uid<1)return null;$a=self::status();if(empty($a['enabled'])||empty($a['id'])||empty($a['items'])||(int)($a['expires_at']??0)<=time()||!in_array((int)($a['target_user_id']??0),[0,$uid],true))return null;
        $s=Database::connection()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([self::seenKey($uid)]);return self::countSeen($s->fetchColumn(),$a['id'])<(int)$a['show_count']?$a:null;
    }
    public static function acknowledge(int $uid,string $id):void{
        $a=self::pending($uid);if(!$a||!hash_equals($a['id'],$id))return;$pdo=Database::connection();$key=self::seenKey($uid);$pdo->beginTransaction();
        try{$sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)':'INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)';$pdo->prepare($sql)->execute([$key,'']);
        $s=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$s->execute([$key]);$n=self::countSeen($s->fetchColumn(),$id);
        if($n<(int)$a['show_count'])self::put($pdo,$key,json_encode(['id'=>$id,'count'=>$n+1],JSON_THROW_ON_ERROR));$pdo->commit();}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}

// Every application HTTP entry point includes app/bootstrap.php, which loads this file.
// Direct service includes in CLI tests must remain side-effect free.
if(in_array(dirname(__DIR__).'/bootstrap.php',get_included_files(),true))MaintenanceP13::gate();
