<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    $report=require __DIR__.'/cumulative_data.php';
    $settings=SettingsService::all();
    $recv=[];$pay=[];
    foreach($report['rows'] as $r){
        $name=(string)($r['display_name_snapshot']??$r['source_username']);
        if((float)$r['received']>0)$recv[]=['name'=>$name,'amount'=>(float)$r['received']];
        if((float)$r['paid']>0)$pay[]=['name'=>$name,'amount'=>(float)$r['paid']];
    }
    $received=(float)$report['total_received'];$paid=(float)$report['total_paid'];
    $net=$received-$paid;
    echo json_encode([
        'ok'=>true,'selected_count'=>count(array_unique(array_map('intval',(array)($_GET['ids']??[])))),'title'=>'حسابرسی اکانت‌های '.$report['company_name'],
        'date'=>$report['date_label'],'unit'=>SettingsService::moneyUnit($settings),
        'received'=>$received,'paid'=>$paid,'net'=>$net,
        'recv'=>$recv,'pay'=>$pay,'factor'=>SettingsService::scaleFactor($settings),
        'locale'=>($settings['digit_mode']??'en')==='fa'?'fa-IR':'en-US',
        'colors'=>[
            'recv'=>SettingsService::themeFill($settings['theme_recv'],$settings),
            'pay'=>SettingsService::themeFill($settings['theme_pay'],$settings),
            'win'=>SettingsService::themeFill($settings['theme_site_win']??$settings['theme_pay'],$settings),
            'loss'=>SettingsService::themeFill($settings['theme_site_loss']??$settings['theme_recv'],$settings),
        ]
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
