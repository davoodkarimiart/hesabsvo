<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    // Same selected reports, archived source files and identifiers as the mother XLSX.
    $selected = require __DIR__.'/cumulative_data.php';
    $raw = $_GET['ids'] ?? [];
    $ids = array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
    if (count($ids) > 20) {
        throw new RuntimeException('برای تصویر مادر حداکثر ۲۰ پنل را همزمان انتخاب کن؛ اکسل محدودیت ندارد.');
    }
    $base = realpath(base_path('storage/report_sources'));
    if ($base === false) throw new RuntimeException('آرشیو فایل‌های مادر در دسترس نیست.');
    $sections = [];
    $numberOfRows = 0;
    foreach ($ids as $id) {
        $report = AccountingService::reportWithRows($id);
        if (!$report || (int)$report['company_id'] !== (int)$selected['company_id']) {
            throw new RuntimeException('شناسه گزارش نامعتبر است.');
        }
        $rel = str_replace('\\', '/', (string)($report['source_storage_path'] ?? ''));
        $file = str_starts_with($rel,'storage/report_sources/') ? realpath(base_path($rel)) : false;
        if (!$file || !str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('فایل مادر گزارش '.$id.' آرشیو نشده است.');
        }
        $sheet = XlsxReader::rows($file, (string)($report['source_filename'] ?? ''));
        $objects = XlsxReader::objects($sheet, ['Username','Member Win']);
        $rows = [];
        $turnover = 0.0;
        $memberWin = 0.0;
        foreach ($objects as $obj) {
            $username = trim((string)($obj['Username'] ?? ''));
            if ($username === '' || preg_match('/^(grand\s*total|total)$/i', $username)) continue;
            $v1 = str_replace(',','',(string)($obj['Turnover'] ?? 0));
            $v2 = str_replace(',','',(string)($obj['Member Win'] ?? 0));
            if (!is_numeric($v1) || !is_numeric($v2)) {
                throw new RuntimeException('مقدار غیرعددی در گزارش '.$id.'.');
            }
            $rows[] = ['username'=>$username,'turnover'=>(float)$v1,'member_win'=>(float)$v2];
            $turnover += (float)$v1;
            $memberWin += (float)$v2;
            ++$numberOfRows;
            if ($numberOfRows > 250) {
                throw new RuntimeException('تصویر مادر برای بیش از ۲۵۰ ردیف بسیار بلند می‌شود؛ فایل کامل را از اکسل دریافت کن.');
            }
        }
        $sections[] = [
            'id' => $id,
            'title' => (string)$report['company_name'].' | '.(string)($report['panel_name'] ?? 'پنل نامشخص').' | '.AccountingService::displayBusinessDate((string)$report['report_date']).' | #'.$id,
            'rows' => $rows,
            'turnover_total' => $turnover,
            'member_win_total' => $memberWin,
        ];
    }
    echo json_encode(['ok'=>true,'selected_count'=>count($sections),'sections'=>$sections],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
