<?php

declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
$type=(string)($_GET['type']??'report');
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Cache-Control: no-store');
function x(mixed $v): string {return htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');}
function cell(mixed $v,string $style='Cell',int $mergeAcross=0): string {$num=is_numeric($v)&&$v!=='';$merge=$mergeAcross>0?' ss:MergeAcross="'.$mergeAcross.'"':'';return '<Cell ss:StyleID="'.$style.'"'.$merge.'><Data ss:Type="'.($num?'Number':'String').'">'.x($v).'</Data></Cell>';}
$settings=SettingsService::all();
$title='گزارش';$headers=[];$rows=[];$tot=null;
if($type==='daily_report'){
    $id=(int)($_GET['id']??0);$r=AccountingService::reportWithRows($id);if(!$r)exit('Report not found');$title='گزارش '.$r['company_name'].' '.AccountingService::displayBusinessDate((string)$r['report_date']);$headers=['نام','Username','کمیسیون','دلار دریافتی','دریافتی','دلار پرداختی','پرداختی'];foreach($r['rows'] as $z)$rows[]=[ $z['display_name_snapshot']?:($z['original_name_snapshot']?:$z['source_username']),$z['source_username'],$z['commission'],((float)$z['member_win']<0?abs((float)$z['member_win']):''),$z['received'],((float)$z['member_win']>0?(float)$z['member_win']:''),$z['paid']];$tot=['received'=>(float)$r['total_received'],'paid'=>(float)$r['total_paid'],'commission'=>(float)$r['total_commission'],'net'=>(float)$r['site_net']];
}elseif($type==='customer'){
    $id=(int)($_GET['id']??0);$c=CustomerService::get($id);if(!$c)exit('Customer not found');$title='حساب '.$c['display_name'];$headers=['تاریخ','شرکت','پنل','نام','Username','Member Win','کمیسیون','دریافتی','پرداختی'];
    foreach($c['ledger']['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['name'],$r['source_username'],$r['member_win'],$r['commission'],$r['received'],$r['paid']];$tot=$c['ledger']['totals'];
}else{
    $f=$_GET;$data=ReportingService::report($f);$title='گزارش '.$data['type'];
    if($data['type']==='detail'){$headers=['تاریخ','شرکت','پنل','نام','Username','Member Win','کمیسیون','دریافتی','پرداختی'];foreach($data['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['name'],$r['source_username'],$r['member_win'],$r['commission'],$r['received'],$r['paid']];}
    elseif(in_array($data['type'],['monthly','multi'],true)){$headers=['دوره','تعداد گزارش','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'];foreach($data['rows'] as $r)$rows[]=[ $r['period'],$r['reports'],$r['received'],$r['paid'],$r['commission'],(($r['net']>0?'برد سایت ':($r['net']<0?'باخت سایت ':'تسویه ')).SettingsService::moneyNumber(abs($r['net']),$settings))];}
    else{$headers=['تاریخ','شرکت','پنل','دریافتی','پرداختی','کمیسیون','برد / باخت سایت'];foreach($data['rows'] as $r)$rows[]=[ AccountingService::displayBusinessDate((string)$r['report_date']),$r['company_name'],$r['panel_name'],$r['received'],$r['paid'],$r['commission'],(($r['net']>0?'برد سایت ':($r['net']<0?'باخت سایت ':'تسویه ')).SettingsService::moneyNumber(abs((float)$r['net']),$settings))];}$tot=$data['totals'];
}
$fn=preg_replace('/[^\pL\pN_-]+/u','-',$title)?:'report';header('Content-Disposition: attachment; filename="'.rawurlencode($fn).'.xls"');
$border='<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>';
$fontName=match($settings['font_table']??'vazirmatn'){'iransans'=>'IRANSans','system'=>'Tahoma',default=>'Vazirmatn'};
$recv=strtoupper(SettingsService::themeFill($settings['theme_recv'],$settings));$pay=strtoupper(SettingsService::themeFill($settings['theme_pay'],$settings));$comm=strtoupper(SettingsService::themeFill($settings['theme_comm'],$settings));$siteWin=strtoupper(SettingsService::themeFill($settings['theme_site_win']??$settings['theme_pay'],$settings));$siteLoss=strtoupper(SettingsService::themeFill($settings['theme_site_loss']??$settings['theme_recv'],$settings));
$styles='<Styles>'
.'<Style ss:ID="Cell"><Font ss:FontName="'.x($fontName).'" ss:Size="10"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'.$border.'</Style>'
.'<Style ss:ID="Head"><Font ss:FontName="'.x($fontName).'" ss:Size="10" ss:Bold="1"/><Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'.$border.'</Style>'
.'<Style ss:ID="Title"><Font ss:FontName="'.x($fontName).'" ss:Size="14" ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
.'<Style ss:ID="Recv"><Font ss:FontName="'.x($fontName).'" ss:Size="10" ss:Bold="1"/><Interior ss:Color="'.$recv.'" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
.'<Style ss:ID="Pay"><Font ss:FontName="'.x($fontName).'" ss:Size="10" ss:Bold="1"/><Interior ss:Color="'.$pay.'" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
.'<Style ss:ID="Comm"><Font ss:FontName="'.x($fontName).'" ss:Size="10" ss:Bold="1"/><Interior ss:Color="'.$comm.'" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
.'<Style ss:ID="SiteWin"><Font ss:FontName="'.x($fontName).'" ss:Size="10" ss:Bold="1"/><Interior ss:Color="'.$siteWin.'" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
.'<Style ss:ID="SiteLoss"><Font ss:FontName="'.x($fontName).'" ss:Size="10" ss:Bold="1"/><Interior ss:Color="'.$siteLoss.'" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
.'</Styles>';
$cols='';for($i=0;$i<count($headers);$i++){$w=($i===0?150:($i===1?125:95));$cols.='<Column ss:AutoFitWidth="0" ss:Width="'.$w.'"/>';}
echo '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'.$styles.'<Worksheet ss:Name="گزارش"><Table>'.$cols;
echo '<Row ss:Height="30">'.cell($title,'Title',max(0,count($headers)-1)).'</Row><Row ss:Height="24">';foreach($headers as $h)echo cell($h,'Head');echo '</Row>';
foreach($rows as $r){echo '<Row ss:Height="22">';foreach($r as $i=>$v){$st='Cell';$h=$headers[$i]??'';$isMoney=false;if(str_contains($h,'دریافتی')){$st='Recv';$isMoney=!str_contains($h,'دلار');}elseif(str_contains($h,'پرداختی')){$st='Pay';$isMoney=!str_contains($h,'دلار');}elseif(str_contains($h,'کمیسیون')){$st='Comm';$isMoney=true;}elseif(str_contains((string)$v,'برد سایت')){$st='SiteWin';}elseif(str_contains((string)$v,'باخت سایت')){$st='SiteLoss';}$out=($isMoney&&is_numeric($v))?SettingsService::moneyNumber($v,$settings):$v;echo cell($out,$st);}echo '</Row>';}
if($tot){echo '<Row/><Row>'.cell('جمع دریافتی','Recv').cell(SettingsService::moneyNumber($tot['received']??0,$settings),'Recv').'</Row><Row>'.cell('جمع پرداختی','Pay').cell(SettingsService::moneyNumber($tot['paid']??0,$settings),'Pay').'</Row><Row>'.cell('جمع کمیسیون','Comm').cell(SettingsService::moneyNumber($tot['commission']??0,$settings),'Comm').'</Row><Row>'.cell(($net=($tot['net']??(($tot['received']??0)-($tot['paid']??0))))>0?'برد سایت':($net<0?'باخت سایت':'تسویه'),$net>0?'SiteWin':($net<0?'SiteLoss':'Comm')).cell(SettingsService::moneyNumber(abs($net),$settings),$net>0?'SiteWin':($net<0?'SiteLoss':'Comm')).'</Row>';}
echo '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><DisplayRightToLeft/><FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal><TopRowBottomPane>2</TopRowBottomPane></WorksheetOptions></Worksheet></Workbook>';
