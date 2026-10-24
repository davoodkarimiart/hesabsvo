<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
if(!class_exists('ZipArchive')){http_response_code(500);exit('افزونه ZipArchive روی سرور فعال نیست.');}
$selected=require __DIR__.'/cumulative_data.php';
$ids=array_unique(array_map('intval',(array)($_GET['ids']??[])));
$all=[];
foreach($ids as $id){
    $report=AccountingService::reportWithRows($id);
    $rel=str_replace('\\','/',(string)($report['source_storage_path']??''));
    $base=realpath(base_path('storage/report_sources'));
    $file=($base&&str_starts_with($rel,'storage/report_sources/'))?realpath(base_path($rel)):false;
    if(!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)){
        http_response_code(422);exit('فایل مادر گزارش '.$id.' آرشیو نشده؛ امکان تهیه اکسل سه‌ستونی دقیق نیست.');
    }
    $raw=XlsxReader::rows($file,(string)($report['source_filename']??''));
    $objects=XlsxReader::objects($raw,['Username','Member Win']);
    $label=(string)$report['company_name'].' | '.(string)($report['panel_name']??'پنل نامشخص').' | '.AccountingService::displayBusinessDate((string)$report['report_date']).' | #'.$id;
    $all[]=[[$label,'',''],['Username','Turnover','Member Win']];
    $turnover=0.0;$member=0.0;
    foreach($objects as $obj){
        $name=trim((string)($obj['Username']??''));
        if($name===''||preg_match('/^(grand\s*total|total)$/i',$name))continue;
        $v1=str_replace(',','',(string)($obj['Turnover']??0));$v2=str_replace(',','',(string)($obj['Member Win']??0));
        if(!is_numeric($v1)||!is_numeric($v2)){http_response_code(422);exit('داده عددی نامعتبر در فایل مادر گزارش '.$id);}
        $turnover+=(float)$v1;$member+=(float)$v2;
        $all[]=[[$name,(float)$v1,(float)$v2]];
    }
    $all[]=[['Grand Total',round($turnover,6),round($member,6)],['','','']];
}
$rows=[];
foreach($all as $part)foreach($part as $v){
 $title=(string)($v[0]??'');$kind=$title==='Grand Total'?'total':'data';
 $rows[]=['kind'=>$kind,'values'=>$v];
}
require_once __DIR__.'/app/Support/ReportXlsx68.php';
ReportXlsx68::download('ریپورت سایت '.$selected['company_name'],'بازه: '.$selected['date_label'].' | مقادیر دلاری گزارش‌های انتخابی',['Username','Turnover','Member Win'],[36,22,23],['text','dollar','member'],$rows,'site-report-combined.xlsx','ریپورت سایت',true);
