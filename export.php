<?php

declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
$type=(string)($_GET['type']??'report');
if(in_array($type,['detail','customer_detail'],true)){require __DIR__.'/customer_detail_excel.php';exit;}
if($type==='cumulative_selected'){require __DIR__.'/cumulative_accounts_excel.php';exit;}

header('Cache-Control: no-store');
function x(mixed $v): string {return htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');}
function cell(mixed $v,string $style='Cell',int $mergeAcross=0): string {$num=is_numeric($v)&&$v!=='';$merge=$mergeAcross>0?' ss:MergeAcross="'.$mergeAcross.'"':'';return '<Cell ss:StyleID="'.$style.'"'.$merge.'><Data ss:Type="'.($num?'Number':'String').'">'.x($v).'</Data></Cell>';}
$settings=SettingsService::all();
$title='گزارش';$headers=[];$rows=[];$tot=null;$reportPeriod='';
if($type==='cumulative_selected'){
    $aggregate=require __DIR__.'/cumulative_data.php';
    $title='تجمعی '.$aggregate['company_name'];$reportPeriod='بازه: '.$aggregate['date_label'];
    $headers=['نام مشتری','دریافتی','پرداختی','مانده سایت'];
    foreach($aggregate['rows'] as $z)$rows[]=[$z['display_name_snapshot'],$z['received'],$z['paid'],(float)$z['received']-(float)$z['paid']];
    $tot=['received'=>(float)$aggregate['total_received'],'paid'=>(float)$aggregate['total_paid'],'commission'=>0.0,'net'=>(float)$aggregate['total_received']-(float)$aggregate['total_paid']];
}elseif($type==='daily_report'){
    $id=(int)($_GET['id']??0);$r=AccountingService::reportWithRows($id);if(!$r)exit('Report not found');$title='روزانه '.$r['company_name'];$reportPeriod='تاریخ: '.AccountingService::displayBusinessDate((string)$r['report_date']);$headers=['نام','Username','کمیسیون','دلار دریافتی','دریافتی','دلار پرداختی','پرداختی'];foreach($r['rows'] as $z)$rows[]=[ $z['display_name_snapshot']?:($z['original_name_snapshot']?:$z['source_username']),$z['source_username'],$z['commission'],((float)$z['member_win']<0?abs((float)$z['member_win']):''),$z['received'],((float)$z['member_win']>0?(float)$z['member_win']:''),$z['paid']];$tot=['received'=>(float)$r['total_received'],'paid'=>(float)$r['total_paid'],'commission'=>(float)$r['total_commission'],'net'=>(float)$r['site_net']];
}elseif($type==='customer'){
    $id=(int)($_GET['id']??0);$c=CustomerService::get($id);if(!$c)exit('Customer not found');$title='حساب '.$c['display_name'];$headers=['تاریخ','شرکت','پنل','نام','Username','Member Win','کمیسیون','دریافتی','پرداختی'];
    foreach($c['ledger']['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['name'],$r['source_username'],$r['member_win'],$r['commission'],$r['received'],$r['paid']];$tot=$c['ledger']['totals'];
}else{
    $f=$_GET;$data=ReportingService::report($f);$title=ReportingService::title($data);$reportPeriod=ReportHeading626::period($data);
    if($data['type']==='expense_history'){$et=ReportingService::expenseTable($data);$headers=$et['headers'];$rows=$et['rows'];}elseif($data['type']==='transfer_history'){$tt=ReportingService::transferTable($data);$headers=$tt['headers'];foreach($tt['rows'] as $r)$rows[]=$r['values'];}
    elseif(in_array($data['type'],['detail','customer_detail'],true)){$headers=['تاریخ','شرکت','پنل','نام','Username','Member Win','کمیسیون','دریافتی','پرداختی'];foreach($data['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['name'],$r['source_username'],$r['member_win'],$r['commission'],$r['received'],$r['paid']];}
    elseif(in_array($data['type'],['monthly','multi'],true)){$headers=['دوره','تعداد گزارش','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'];foreach($data['rows'] as $r)$rows[]=[ $r['period'],$r['reports'],$r['received'],$r['paid'],$r['commission'],(($r['net']>0?'برد سایت ':($r['net']<0?'باخت سایت ':'تسویه ')).SettingsService::moneyNumber(abs($r['net']),$settings))];}
    else{$headers=['تاریخ','شرکت','پنل','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'];foreach($data['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['received'],$r['paid'],$r['commission'],(($r['net']>0?'برد سایت ':($r['net']<0?'باخت سایت ':'تسویه ')).SettingsService::moneyNumber(abs((float)$r['net']),$settings))];}$tot=in_array($data['type'],['transfer_history','expense_history'],true)?null:$data['totals'];
}

require_once __DIR__.'/app/Support/ReportXlsx68.php';
// OOXML .xlsx used consistently: XML Spreadsheet 2003 (.xls) was rejected by some Android viewers.
$types=[];$widths=[];
foreach($headers as $header){
 $types[]=match(true){$header==='Member Win'||str_contains($header,'دلار')=>'member',str_contains($header,'کمیسیون')=>'commission',str_contains($header,'دریافتی')=>'received',str_contains($header,'پرداختی')=>'paid',default=>'text'};
 $widths[]=str_contains($header,'نام')?32:($header==='Username'?24:18);
}
$items=[];$factor=SettingsService::scaleFactor($settings);
foreach($rows as $values){
 foreach($values as $j=>&$value){
  $kind=$types[$j]??'text';
  if(is_numeric($value)&&$value!==''&&in_array($kind,['received','paid','commission'],true))$value=(int)round(((float)$value)/$factor,0,PHP_ROUND_HALF_UP);
  elseif(is_numeric($value)&&$value!==''&&($kind==='member'))$value=(float)$value;
 }unset($value);
 $items[]=['values'=>$values];
}
if($tot&&count($headers)>=2){
 $more=[['جمع دریافتی',$tot['received']??0],['جمع پرداختی',$tot['paid']??0],['جمع کمیسیون',$tot['commission']??0],['مانده سایت',$tot['net']??0]];
 foreach($more as [$label,$val]){
  $values=array_fill(0,count($headers),'');$values[0]=$label;$values[1]=(int)round((float)$val/$factor,0,PHP_ROUND_HALF_UP);
  $items[]=['kind'=>'total','values'=>$values];
 }
}
if(isset($data)&&in_array($data['type'],['period_transfer','transfer_history','expense_history'],true)){
    $items=[];
    $number=static fn($v):int=>(int)TransferService::wholeToman($v);
    $scaled=static fn($v):int=>(int)round((float)$v/$factor,0,PHP_ROUND_HALF_UP);
    $tone=static fn($dir):int=>$dir==='positive'?30:($dir==='negative'?29:6);
    $netStyle=static fn($v):int=>$v>0?23:($v<0?24:6);
    if($data['type']==='expense_history'){
        $et=ReportingService::expenseTable($data);$headers=$et['headers'];$widths=[19,25,38,26];$types=['ltr','text','text','integer'];
        foreach($et['rows'] as $v)$items[]=['values'=>[$v[0],$v[1],$v[2],$number($v[3])],'styles'=>[0=>37,1=>38,2=>22,3=>51]];
        $items[]=['kind'=>'section','values'=>['جمع‌بندی هزینه‌ها (تومان)','','',''],'merge'=>[0=>3],'styles'=>[0=>31]];
        $items[]=['kind'=>'total','values'=>['جمع هزینه‌ها','','',$number($data['expense_total'])],'merge'=>[0=>2],'styles'=>[0=>50,3=>51]];
    }elseif($data['type']==='transfer_history'){
        $headers=['تاریخ تسویه','شرکت','شماره سند','نوع تسویه','وضعیت','مبلغ (تومان)'];
        $widths=[18,24,32,27,18,25];$types=['ltr','text','ltr','text','text','integer'];
        foreach(ReportingService::transferTable($data)['rows'] as $r){
            $v=$r['values'];$v[5]=$number($v[5]);$kindStyle=$r['direction']==='positive'?40:($r['direction']==='negative'?39:22);$amountStyle=$r['direction']==='positive'?42:($r['direction']==='negative'?41:51);
            $items[]=['values'=>$v,'styles'=>[0=>37,2=>38,3=>$kindStyle,5=>$amountStyle]];
        }
        $sum=ReportingService::settlementSummary($data);
        $items[]=['kind'=>'section','values'=>['جمع‌بندی ترانسفرها (تومان)','','','','',''],'merge'=>[0=>5],'styles'=>[0=>31]];
        $items[]=['kind'=>'section','values'=>[$sum[0]['label'],'','',$sum[1]['label'],'',''],'merge'=>[0=>2,3=>5],'styles'=>[0=>33,3=>32]];
        $items[]=['kind'=>'total','values'=>[$number($sum[0]['value']),'','',$number($sum[1]['value']),'',''],'merge'=>[0=>2,3=>5],'styles'=>[0=>30,3=>29]];
    }else{
        $headers=['تاریخ','شرکت','پنل','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'];
        $widths=[18,24,20,22,22,22,25];$types=['ltr','text','text','received','paid','commission','net'];
        foreach($data['rows'] as $r)$items[]=['values'=>[AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$scaled($r['received']),$scaled($r['paid']),$scaled($r['commission']),$scaled($r['net'])],'styles'=>[6=>$netStyle($r['net'])]];
        $t=$data['totals'];$items[]=['kind'=>'total','values'=>['جمع دوره','','',$scaled($t['received']),$scaled($t['paid']),$scaled($t['commission']),$scaled($t['net'])],'merge'=>[0=>2],'styles'=>[0=>31,3=>25,4=>26,5=>27,6=>$netStyle($t['net'])]];
        $items[]=['kind'=>'section','values'=>['جمع هر پنل','','','','','',''],'merge'=>[0=>6],'styles'=>[0=>31]];
        $items[]=['kind'=>'section','values'=>['پنل','','','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'],'merge'=>[0=>2],'styles'=>[0=>22,3=>34,4=>35,5=>36,6=>28]];
        foreach($data['panel_totals'] as $r)$items[]=['kind'=>'section','values'=>[$r['panel_name'],'','',$scaled($r['received']),$scaled($r['paid']),$scaled($r['commission']),$scaled($r['net'])],'merge'=>[0=>2],'styles'=>[0=>22,3=>25,4=>26,5=>27,6=>$netStyle($r['net'])]];
        $items[]=['kind'=>'section','values'=>['ترانسفرهای دوره (تومان کامل)','','','','','',''],'merge'=>[0=>6],'styles'=>[0=>31]];
        $items[]=['kind'=>'section','values'=>['تاریخ تسویه','شماره سند','','نوع تسویه','','مبلغ (تومان)',''],'merge'=>[1=>2,3=>4,5=>6],'styles'=>[0=>48,1=>49,3=>22,5=>50]];
        foreach(ReportingService::transferTable($data)['rows'] as $r){$v=$r['values'];$kindStyle=$r['direction']==='positive'?40:($r['direction']==='negative'?39:22);$amountStyle=$r['direction']==='positive'?42:($r['direction']==='negative'?41:51);$items[]=['kind'=>'section','values'=>[$v[0],$v[1],'',$v[2],'',$number($v[3]),''],'merge'=>[1=>2,3=>4,5=>6],'styles'=>[0=>37,1=>38,3=>$kindStyle,5=>$amountStyle]];}
        $items[]=['kind'=>'section','values'=>['هزینه‌های دوره (تومان کامل)','','','','','',''],'merge'=>[0=>6],'styles'=>[0=>31]];
        $items[]=['kind'=>'section','values'=>['تاریخ هزینه','','موضوع هزینه','','','مبلغ (تومان)',''],'merge'=>[0=>1,2=>4,5=>6],'styles'=>[0=>48,2=>22,5=>50]];
        foreach(ReportingService::expenseTable($data)['rows'] as $v)$items[]=['kind'=>'section','values'=>[$v[0],'',$v[1],'','',$number($v[2]),''],'merge'=>[0=>1,2=>4,5=>6],'styles'=>[0=>37,2=>22,5=>51]];
        $sum=ReportingService::settlementSummary($data);
        $items[]=['kind'=>'section','values'=>['جمع‌بندی نهایی (تومان کامل)','','','','','',''],'merge'=>[0=>6],'styles'=>[0=>31]];
        $items[]=['kind'=>'section','values'=>[str_replace(' (تومان)','',$sum[0]['label']),'',str_replace(' (تومان)','',$sum[1]['label']),'',str_replace(' (تومان)','',$sum[2]['label']),'',''],'merge'=>[0=>1,2=>3,4=>6],'styles'=>[0=>32,2=>44,4=>50]];
        $items[]=['kind'=>'total','values'=>[$number($sum[0]['value']),'',$number($sum[1]['value']),'',$number($sum[2]['value']),'',''],'merge'=>[0=>1,2=>3,4=>6],'styles'=>[0=>29,2=>43,4=>51]];
        $net=ReportingService::expenseNetSummary($data);
        $items[]=['kind'=>'total','values'=>['نتیجه نهایی','',$net['status'],'',$number($net['value']),'',''],'merge'=>[0=>1,2=>3,4=>6],'styles'=>[0=>55,2=>($net['net']>0?56:($net['net']<0?57:58)),4=>($net['net']>0?59:($net['net']<0?60:61))]];
    }
    $reportPeriod=ReportingService::periodLabel($data);
    $xlsxDates=['from'=>'','to'=>''];foreach(['from','to'] as $key){$raw=(string)($data['filters'][$key]??'');if($raw==='')$raw=(string)($data['context'][$key]??'');if($raw!=='')$xlsxDates[$key]=AccountingService::displayBusinessDate(AccountingService::normalizeBusinessDate($raw));}if($data['type']==='transfer_history'&&!empty($data['rows'])){$dates=array_column($data['rows'],'period_end');sort($dates,SORT_STRING);if($xlsxDates['from']==='')$xlsxDates['from']=AccountingService::displayBusinessDate($dates[0]);if($xlsxDates['to']==='')$xlsxDates['to']=AccountingService::displayBusinessDate(end($dates));}
    $xlsxOptions=['settlement'=>true,'headerStyles'=>match($data['type']){'transfer_history'=>[48,3,49,22,3,50],'expense_history'=>[48,49,22,50],default=>array_fill(0,count($headers),null)},'dates'=>$xlsxDates,'unit'=>in_array($data['type'],['transfer_history','expense_history'],true)?'تومان':SettingsService::moneyUnit($settings),'palette'=>['received'=>SettingsService::themeFill($settings['theme_recv'],$settings),'paid'=>SettingsService::themeFill($settings['theme_pay'],$settings),'commission'=>SettingsService::themeFill($settings['theme_comm'],$settings),'win'=>SettingsService::themeFill($settings['theme_site_win']??$settings['theme_pay'],$settings),'loss'=>SettingsService::themeFill($settings['theme_site_loss']??$settings['theme_recv'],$settings)]];
}
$fname='report-'.date('Ymd-His').'.xlsx';
ReportXlsx68::download($title,implode(' | ',array_filter([$reportPeriod,'مبالغ به '.(in_array($type,['transfer_history','expense_history'],true)?'تومان':SettingsService::moneyUnit($settings))])),$headers,$widths,$types,$items,$fname,'گزارش',false,$xlsxOptions??[]);
