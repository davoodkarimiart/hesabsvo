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
    if($data['type']==='transfer_history'){$tt=ReportingService::transferTable($data);$headers=$tt['headers'];foreach($tt['rows'] as $r)$rows[]=$r['values'];}
    elseif(in_array($data['type'],['detail','customer_detail'],true)){$headers=['تاریخ','شرکت','پنل','نام','Username','Member Win','کمیسیون','دریافتی','پرداختی'];foreach($data['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['name'],$r['source_username'],$r['member_win'],$r['commission'],$r['received'],$r['paid']];}
    elseif(in_array($data['type'],['monthly','multi'],true)){$headers=['دوره','تعداد گزارش','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'];foreach($data['rows'] as $r)$rows[]=[ $r['period'],$r['reports'],$r['received'],$r['paid'],$r['commission'],(($r['net']>0?'برد سایت ':($r['net']<0?'باخت سایت ':'تسویه ')).SettingsService::moneyNumber(abs($r['net']),$settings))];}
    else{$headers=['تاریخ','شرکت','پنل','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'];foreach($data['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['received'],$r['paid'],$r['commission'],(($r['net']>0?'برد سایت ':($r['net']<0?'باخت سایت ':'تسویه ')).SettingsService::moneyNumber(abs((float)$r['net']),$settings))];}$tot=$data['type']==='transfer_history'?null:$data['totals'];
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
if(isset($data)&&in_array($data['type'],['period_transfer','transfer_history'],true)){
    $n=count($headers);$add=static function(array $values,string $kind='data')use(&$items,$n){$items[]=['kind'=>$kind,'values'=>array_pad($values,$n,'')];};
    if($data['type']==='period_transfer'){
        $add(['جمع هر پنل','','','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'],'total');
        foreach($data['panel_totals'] as $p)$add([$p['panel_name'],'','',(int)round($p['received']/$factor),(int)round($p['paid']/$factor),(int)round($p['commission']/$factor),ReportingService::netLabel($p['net']).' '.SettingsService::money(abs($p['net']),$settings)]);
        $add(['ترانسفرهای دوره (تومان کامل)'],'total');
        // Keep all numbers in the first two columns of the shared worksheet or as exact strings in a separate section.
        $add(['تاریخ تسویه','شماره سند','نوع تسویه','مبلغ (تومان)']);
        foreach(ReportingService::transferTable($data)['rows'] as $r){$v=$r['values'];$add($v);}
    }else{
        // Full-toman amounts in history are not scaled by display preferences.
        $items=[];foreach(ReportingService::transferTable($data)['rows'] as $r){$v=$r['values'];$add($v);}
        $widths=[18,20,32,24,15,27];
    }
    foreach(ReportingService::transferSummary($data) as $s)$add([$s['label'],$s['value']],'total');
    if($data['type']==='period_transfer'){$v=(float)$data['transfer_totals']['adjusted_net'];$add(['نتیجه پس از ترانسفرها (تومان کامل): '.ReportingService::netLabel($v),TransferService::decimalAbs($data['transfer_totals']['adjusted_net'])],'total');}
}
$fname='report-'.date('Ymd-His').'.xlsx';
ReportXlsx68::download($title,implode(' | ',array_filter([$reportPeriod,'مبالغ به '.(($type==='transfer_history')?'تومان':SettingsService::moneyUnit($settings))])),$headers,$widths,$types,$items,$fname,'گزارش');
