<?php

declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
Auth::requireLogin();
$id=(int)($_GET['id']??0);if($id<1){http_response_code(404);exit('گزارش پیدا نشد.');}
$pdo=Database::connection();
$hasCol=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='daily_reports' AND column_name='source_storage_path'")->fetchColumn();
$sql=$hasCol?"SELECT id,company_id,source_filename,source_storage_path FROM daily_reports WHERE id=? AND status='final'":"SELECT id,company_id,source_filename,NULL source_storage_path FROM daily_reports WHERE id=? AND status='final'";
$st=$pdo->prepare($sql);$st->execute([$id]);$r=$st->fetch();if(!$r){http_response_code(404);exit('گزارش پیدا نشد.');}
if(!empty($r['source_storage_path'])){$rel=str_replace('\\','/',(string)$r['source_storage_path']);if(str_starts_with($rel,'storage/report_sources/')){$file=base_path($rel);$base=realpath(base_path('storage/report_sources'));$real=realpath($file);if($base&&$real&&str_starts_with($real,$base.DIRECTORY_SEPARATOR)&&is_file($real)){$name=basename((string)($r['source_filename']?:'Report_WL.xlsx'));header('Content-Type: application/octet-stream');header('Content-Length: '.filesize($real));header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($real);exit;}}}
// Old reports: build a clean Excel-compatible workbook directly from immutable saved rows.
$rows=$pdo->prepare('SELECT source_username,member_win FROM daily_report_rows WHERE daily_report_id=? ORDER BY id');$rows->execute([$id]);$data=$rows->fetchAll();if(!$data){http_response_code(404);exit('برای این گزارش ردیفی ثبت نشده است.');}
function xx(mixed $v): string {return htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');}
$name='Report_WL_'.$id.'_reconstructed.xls';header('Content-Type: application/vnd.ms-excel; charset=utf-8');header('Cache-Control: private, no-store');header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));header('X-Content-Type-Options: nosniff');
echo '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Report_WL"><Table>';
echo '<Row><Cell><Data ss:Type="String">Username</Data></Cell><Cell><Data ss:Type="String">Member Win</Data></Cell></Row>';
foreach($data as $z){echo '<Row><Cell><Data ss:Type="String">'.xx($z['source_username']).'</Data></Cell><Cell><Data ss:Type="Number">'.xx((float)$z['member_win']).'</Data></Cell></Row>';}
echo '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><DisplayRightToLeft/></WorksheetOptions></Worksheet></Workbook>';
