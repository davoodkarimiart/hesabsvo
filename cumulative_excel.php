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
$rows=[];foreach($all as $part)foreach($part as $r)$rows[]=$r;
function xmlEscape(string $s):string{return htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');}
function cellXml(mixed $v,string $column,int $i,int $style=0):string{
  $id=$column.$i;$attr=' s="'.($style?:((is_numeric($v)&&$v!=='')?4:0)).'"';
  if(is_int($v)||is_float($v))return '<c r="'.$id.'"'.$attr.'><v>'.xmlEscape((string)$v).'</v></c>';
  return '<c r="'.$id.'"'.$attr.' t="inlineStr"><is><t>'.xmlEscape((string)$v).'</t></is></c>';
}
$styles='<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="15"/><color rgb="FFFFFFFF"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF36436B"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDDEAF4"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFCAEAD9"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf fontId="0" fillId="0" borderId="1" xfId="0"/><xf fontId="1" fillId="2" borderId="1" xfId="0"/><xf fontId="2" fillId="3" borderId="1" xfId="0"/><xf fontId="2" fillId="4" borderId="1" xfId="0"/><xf fontId="0" fillId="0" borderId="1" numFmtId="4" xfId="0"/></cellXfs></styleSheet>';
$sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="false"/></sheetViews><cols><col min="1" max="1" width="50" customWidth="1"/><col min="2" max="3" width="22" customWidth="1"/></cols><sheetData>';
$merges=[];foreach($rows as $j=>$r){$i=$j+1;$band=($r[0]!==''&&($r[1]??'')===''&&($r[2]??'')==='');$header=($r[0]??'')==='Username';$total=($r[0]??'')==='Grand Total';$style=$band?1:($header?2:($total?3:0));$sheet.='<row r="'.$i.'" ht="'.($band?34:26).'" customHeight="1">';foreach(['A','B','C'] as $k=>$col)$sheet.=cellXml($r[$k]??'',$col,$i,$style);$sheet.='</row>';if($band)$merges[]='<mergeCell ref="A'.$i.':C'.$i.'"/>';}$sheet.='</sheetData><mergeCells count="'.count($merges).'">'.implode('',$merges).'</mergeCells></worksheet>';
$tmp=tempnam(sys_get_temp_dir(),'svo_cumulative_');$zip=new ZipArchive();if($zip->open($tmp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('ساخت اکسل ناموفق بود.');
$zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Combined Reports" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
$zip->addFromString('xl/styles.xml',$styles);$zip->addFromString('xl/worksheets/sheet1.xml',$sheet);$zip->close();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="combined-report-svo.xlsx"');header('Content-Length: '.filesize($tmp));header('Cache-Control: private, no-store');readfile($tmp);unlink($tmp);
