<?php

declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

try {
    if ($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);throw new RuntimeException('درخواست نامعتبر است.');}
    $q=trim((string)($_GET['customer_query']??''));
    if (mb_strlen($q,'UTF-8')>100) {http_response_code(422);throw new RuntimeException('متن جست‌وجو بیش از حد طولانی است.');}
    $company=max(0,(int)($_GET['company_id']??0));$panel=max(0,(int)($_GET['panel_id']??0));
    $rows=CustomerService::list($q,true,$company,$panel);
    $settings=SettingsService::all();
    $out=[];
    foreach($rows as $c){
        $out[]=[
            'id'=>(int)$c['id'],
            'name'=>(string)$c['display_name'],
            'active'=>(bool)$c['active'],
            'account_count'=>(int)$c['account_count'],
            'companies'=>(string)($c['company_names']??''),
            'panels'=>(string)($c['panel_names']??''),
            'usernames'=>(string)($c['usernames']??''),
            'received'=>SettingsService::money($c['received'],$settings),
            'paid'=>SettingsService::money($c['paid'],$settings),
        ];
    }
    echo json_encode(['ok'=>true,'rows'=>$out,'count'=>count($out)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    Logger::error('customers.search_failed',['error'=>$e->getMessage()]);
    if(http_response_code()<400)http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>http_response_code()===422?$e->getMessage():'دریافت نتایج جست‌وجو ممکن نشد.'],JSON_UNESCAPED_UNICODE);
}
