<?php
declare(strict_types=1);
/** v0.6.8: Styled, actual right-to-left Excel for both detailed report types. */
require_once __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
require_once __DIR__.'/app/Support/ReportXlsx68.php';
$data=ReportingService::report($_GET);
if(!in_array($data['type'],['detail','customer_detail'],true))throw new RuntimeException('نوع گزارش ریز پرداخت معتبر نیست.');
$s=SettingsService::all();$factor=SettingsService::scaleFactor($s);
$headers=['تاریخ','شرکت','پنل','نام','Username','Member Win','کمیسیون','دریافتی','پرداختی'];
$types=['text','text','text','text','text','member','commission','received','paid'];
$widths=[15,12,12,28,22,16,16,16,16];
$rows=[];
foreach($data['rows'] as $r){
 $rows[]=['values'=>[
  AccountingService::displayBusinessDate((string)$r['report_date']),
  (string)$r['company_name'],(string)$r['panel_name'],(string)$r['name'],(string)$r['source_username'],
  (float)$r['member_win'],
  (int)round((float)$r['commission']/$factor,0,PHP_ROUND_HALF_UP),
  (int)round((float)$r['received']/$factor,0,PHP_ROUND_HALF_UP),
  (int)round((float)$r['paid']/$factor,0,PHP_ROUND_HALF_UP),
 ]];
}
$title=ReportHeading626::title($data);
$subtitle=implode(' | ',array_filter([ReportHeading626::period($data),'مبالغ به '.SettingsService::moneyUnit($s),'Member Win به دلار با ۲ رقم اعشار']));
ReportXlsx68::download($title,$subtitle,$headers,$widths,$types,$rows,'soltan-detail-rtl-v068.xlsx',$data['type']==='detail'?'ریز پرداخت':'ریز پرداخت مشتری');
