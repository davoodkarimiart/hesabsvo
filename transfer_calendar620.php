<?php

declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
try {
    Auth::requireLogin();
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new RuntimeException('متد درخواست مجاز نیست.');
    $company=(int)($_GET['company_id']??0);
    $editing=(int)($_GET['editing']??0);
    if($editing>0){
        $existing=TransferService::get($editing);
        if(!$existing||$existing['status']!=='final'||(int)$existing['company_id']!==$company)throw new RuntimeException('ترانسفر قابل ویرایش نیست.');
    }
    $method=(string)($_GET['mode']??'calendar');
    $data=$method==='range'?TransferService::overlapInfo($company,(string)($_GET['from']??''),(string)($_GET['to']??''),$editing>0?$editing:null):TransferService::calendar($company,(string)($_GET['from']??''),(string)($_GET['to']??''),$editing>0?$editing:null);
    echo json_encode(['ok'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
