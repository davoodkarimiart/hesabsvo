<?php

declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    Auth::requireLogin();
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new RuntimeException('متد درخواست مجاز نیست.');
    $edit=(int)($_GET['editing']??0);
    $editing=$edit>0?$edit:null;
    if($editing){$existing=TransferService::get($editing);if(!$existing||$existing['status']!=='final'||(int)$existing['company_id']!==(int)($_GET['company_id']??0))throw new RuntimeException('ترانسفر قابل ویرایش نیست.');}
    $result=TransferService::preview($_GET,$editing);
    $result['source']['start_fa']=AccountingService::displayBusinessDate($result['source']['start']);
    $result['source']['end_fa']=AccountingService::displayBusinessDate($result['source']['end']);
    echo json_encode(['ok'=>true,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}
