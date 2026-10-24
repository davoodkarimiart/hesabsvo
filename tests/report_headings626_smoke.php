<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/AccountingService.php';
require dirname(__DIR__).'/app/Support/ReportHeading626.php';
$cases = [
  [
    ['type'=>'detail','context'=>['companies'=>['شاهین','حاتم'],'from'=>'2026-10-05','to'=>'2026-10-21'],'filters'=>[]],
    'ریز پرداخت شاهین، حاتم','بازه: 1405/07/13 تا 1405/07/29',
  ],
  [
    ['type'=>'monthly','context'=>['companies'=>['شاهین','حاتم'],'from'=>'2026-10-05','to'=>'2026-10-21'],'filters'=>[]],
    'ماهانه شاهین، حاتم','بازه: 1405/07/13 تا 1405/07/29',
  ],
  [
    ['type'=>'daily','context'=>['companies'=>['شاهین'],'from'=>'2026-10-21','to'=>'2026-10-21'],'filters'=>[]],
    'روزانه شاهین','تاریخ: 1405/07/29',
  ],
  [
    ['type'=>'detail','rows'=>[['company_name'=>'شاهین'],['company_name'=>'حاتم']],'filters'=>['from'=>'1405/07/01','to'=>'1405/07/12']],
    'ریز پرداخت شاهین، حاتم','بازه: 1405/07/01 تا 1405/07/12',
  ],
];
foreach ($cases as $i => [$data,$title,$period]) {
    if (ReportHeading626::title($data)!==$title || ReportHeading626::period($data)!==$period) {
        fwrite(STDERR,"Case ".($i+1)." failed: ".ReportHeading626::title($data).' | '.ReportHeading626::period($data)."\n");exit(1);
    }
}
echo "[OK] Four title/period cases, including mixed companies and Jalali dates.\n";
