<?php

declare(strict_types=1);require __DIR__.'/app/bootstrap.php';header('Content-Type:text/plain; charset=utf-8');$pdo=Database::connection();
function vc(PDO $p,string $t,string $c): bool {$s=$p->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$s->execute([$t,$c]);return (bool)$s->fetchColumn();}
function vt(PDO $p,string $t): bool {$s=$p->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$t]);return (bool)$s->fetchColumn();}
$idx=file_get_contents(__DIR__.'/index.php');$rs=file_get_contents(__DIR__.'/app/Services/ReportingService.php');$cs=file_get_contents(__DIR__.'/app/Services/CustomerService.php');$css=file_get_contents(__DIR__.'/assets/app.css');$checks=[
'report view icon'=>str_contains($idx,'report-view-icon'),
'no report file column'=>!str_contains($idx,'no-image no-output\">فایل'),
'app confirm'=>str_contains(file_get_contents(base_path('assets/app.js')),'window.appConfirm'),
'v0.4.16 cache'=>str_contains($idx,'app.css?v=0416'),
'source storage column'=>vc($pdo,'daily_reports','source_storage_path'),
'autogroup exclusions'=>vt($pdo,'customer_autogroup_exclusions'),
'decimal numeric normalization'=>str_contains($rs,'Normalize DB DECIMAL strings'),
'row-authoritative panel filter'=>str_contains($rs,'EXISTS(SELECT 1 FROM daily_report_rows px'),
'account fallback name'=>str_contains($cs,'USERNAME:')&&str_contains($cs,'bestLabel'),
'customer directory autogroup'=>str_contains($cs,'autoGroupUnlinked'),
'download reconstruction'=>str_contains(file_get_contents(__DIR__.'/download_report_source.php'),'reconstructed.xls'),
'customer dialog real fit'=>str_contains($css,'v0.4.10 final Phase 4–7 stabilization'),
'report action simplified'=>str_contains($idx,'report-view-icon')&&!str_contains($idx,'>ویرایش</button>'),
'summary report file'=>is_file(__DIR__.'/summary_report.php')&&str_contains($idx,'خلاصه گزارش'),
'soft-deleted report cleanup'=>str_contains(file_get_contents(__DIR__.'/app/Services/AccountSettingsService.php'),"COALESCE(r.status,'final')<>'deleted'"),
'output source hidden'=>!str_contains($idx,'no-image no-output\">فایل')&& !str_contains(file_get_contents(__DIR__.'/print.php'),"'فایل','دریافتی'"),
'theme opacity outputs'=>str_contains(file_get_contents(__DIR__.'/app/Services/SettingsService.php'),'themeFill'),
];$bad=false;foreach($checks as $k=>$ok){echo ($ok?'[OK] ':'[FAIL] ').$k."\n";if(!$ok)$bad=true;}exit($bad?1:0);
