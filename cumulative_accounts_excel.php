<?php
declare(strict_types=1);
/** v0.6.8: selected reports, non-netting accounting summary, Persian UTF-8 XLSX. */
require_once __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
require_once __DIR__.'/app/Support/ReportXlsx68.php';
$data=require __DIR__.'/cumulative_data.php';
$s=SettingsService::all();$factor=SettingsService::scaleFactor($s);
$headers=['نام مشتری','دریافتی','پرداختی'];
$types=['text','received','paid'];
$rows=[];
foreach($data['rows'] as $r){
 $rows[]=['values'=>[
   (string)($r['display_name_snapshot']??$r['source_username']??''),
   (int)round((float)$r['received']/$factor,0,PHP_ROUND_HALF_UP),
   (int)round((float)$r['paid']/$factor,0,PHP_ROUND_HALF_UP),
 ]];
}
$rows[]=['kind'=>'total','values'=>['جمع کل',
 (int)round((float)$data['total_received']/$factor,0,PHP_ROUND_HALF_UP),
 (int)round((float)$data['total_paid']/$factor,0,PHP_ROUND_HALF_UP),
]];
$title='تجمعی '.$data['company_name'];
$subtitle=$data['date_label'].' | مبالغ به '.SettingsService::moneyUnit($s).' | دریافتی و پرداختی بدون تهاتر';
ReportXlsx68::download($title,$subtitle,$headers,[35,20,20],$types,$rows,'soltan-cumulative-summary-v068.xlsx','خلاصه تجمعی');
