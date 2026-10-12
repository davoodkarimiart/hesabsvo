<?php
/** Server-side immutable report selection. Included after Auth::requireLogin(). */
declare(strict_types=1);
$raw=$_GET['ids']??[];
if(!is_array($raw) || count($raw)<1 || count($raw)>150)throw new RuntimeException('بین ۱ تا ۱۵۰ گزارش را انتخاب کن.');
$ids=[];foreach($raw as $id){if(!ctype_digit((string)$id)||(int)$id<1)throw new RuntimeException('شناسه گزارش نامعتبر است.');$ids[]=(int)$id;}
$ids=array_values(array_unique($ids));
$reports=[];$companyId=null;$companyName='';$start=null;$end=null;
foreach($ids as $id){
    $r=AccountingService::reportWithRows($id);
    if(!$r)throw new RuntimeException('گزارش شماره '.$id.' پیدا نشد.');
    if($companyId!==null && (int)$r['company_id']!==$companyId)throw new RuntimeException('گزارش‌های انتخابی باید متعلق به یک شرکت باشند.');
    $companyId=(int)$r['company_id'];$companyName=(string)$r['company_name'];
    $d=(string)$r['report_date'];if($start===null||$d<$start)$start=$d;if($end===null||$d>$end)$end=$d;
    $reports[]=$r;
}
$groups=[];
foreach($reports as $r){foreach($r['rows'] as $line){
    $name=trim((string)($line['customer_name']?:($line['display_name_snapshot']?:($line['original_name_snapshot']?:$line['source_username']))));
    // Stable registered customer identity wins; otherwise fall back to the display name.
    $key=!empty($line['customer_id'])?'customer:'.$line['customer_id']:'name:'.(function_exists('mb_strtolower')?mb_strtolower(preg_replace('/\s+/u',' ',$name),'UTF-8'):strtolower(preg_replace('/\s+/u',' ',$name)));
    if(!isset($groups[$key]))$groups[$key]=['name'=>$name,'received'=>0.0,'paid'=>0.0];
    $groups[$key]['received']+=(float)$line['received'];$groups[$key]['paid']+=(float)$line['paid'];
}}
$merged=[];$tr=0.0;$tp=0.0;
foreach($groups as $g){
    // Non-netting aggregation: maintain both accounting sides independently.
    $received=round((float)$g['received'],6);$paid=round((float)$g['paid'],6);
    if(abs($received)<0.000001 && abs($paid)<0.000001)continue;
    if($received>0)$merged[]=['display_name_snapshot'=>$g['name'],'original_name_snapshot'=>$g['name'],'source_username'=>$g['name'],'received'=>$received,'paid'=>0.0];
    if($paid>0)$merged[]=['display_name_snapshot'=>$g['name'],'original_name_snapshot'=>$g['name'],'source_username'=>$g['name'],'received'=>0.0,'paid'=>$paid];
    $tr+=$received;$tp+=$paid;
}
return ['id'=>0,'company_id'=>$companyId,'company_name'=>$companyName,'report_date'=>$start,'date_label'=>AccountingService::displayBusinessDate($start).($start!==$end?' تا '.AccountingService::displayBusinessDate($end):''),'total_received'=>round($tr,6),'total_paid'=>round($tp,6),'rows'=>$merged];
