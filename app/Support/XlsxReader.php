<?php

declare(strict_types=1);

final class XlsxReader {
    public static function rows(string $path, string $originalName=''): array {
        if (!is_file($path)) throw new RuntimeException('فایل Excel پیدا نشد.');
        $head = file_get_contents($path, false, null, 0, 8);
        $isXlsx = is_string($head) && str_starts_with($head, "PK\x03\x04");
        $isXls = is_string($head) && $head === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        if ($isXls) {
            throw new RuntimeException('فایل XLS معتبر است اما Parser سرور برای XLS از مسیر مرورگر ارسال می‌شود. JavaScript/SheetJS لود نشده؛ صفحه را با اینترنت فعال Refresh کن و دوباره فایل را بفرست.');
        }
        if (!$isXlsx) {
            throw new RuntimeException('فرمت واقعی فایل Excel شناخته نشد. XLS و XLSX پشتیبانی می‌شوند؛ فایل را بدون تغییر پسوند انتخاب کن.');
        }
        if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive روی هاست فعال نیست. extension=zip را فعال کن.');
        if (!function_exists('simplexml_load_string')) throw new RuntimeException('PHP SimpleXML روی هاست فعال نیست.');

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('فایل XLSX قابل باز شدن نیست.');
        try {
            $shared = self::sharedStrings($zip);
            $sheetPath = self::firstSheetPath($zip);
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) throw new RuntimeException('Worksheet اصلی در XLSX پیدا نشد.');
            $sx = simplexml_load_string($xml);
            if (!$sx) throw new RuntimeException('ساختار Worksheet معتبر نیست.');
            $sx->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $out = [];
            foreach ($sx->xpath('//x:sheetData/x:row') ?: [] as $row) {
                $cells = [];
                $maxCol = -1;
                foreach ($row->c as $cell) {
                    $ref = (string)$cell['r'];
                    $col = self::columnIndex($ref);
                    $maxCol = max($maxCol, $col);
                    $type = (string)$cell['t'];
                    $value = null;
                    if ($type === 'inlineStr') {
                        $value = isset($cell->is->t) ? (string)$cell->is->t : '';
                    } elseif (isset($cell->v)) {
                        $raw = (string)$cell->v;
                        if ($type === 's') $value = $shared[(int)$raw] ?? '';
                        elseif ($type === 'b') $value = $raw === '1';
                        elseif ($type === 'str') $value = $raw;
                        else $value = is_numeric($raw) ? (float)$raw : $raw;
                    }
                    $cells[$col] = $value;
                }
                if ($maxCol < 0) { $out[] = []; continue; }
                $line = [];
                for ($i=0; $i <= $maxCol; $i++) $line[] = $cells[$i] ?? null;
                $out[] = $line;
            }
            return $out;
        } finally {
            $zip->close();
        }
    }

    public static function objects(array $rows, array $requiredHeaders): array {
        $headerIndex = self::headerIndex($rows, $requiredHeaders);
        if ($headerIndex < 0) throw new RuntimeException('ساختار فایل شناخته نشد؛ ستون‌های لازم پیدا نشدند: ' . implode(', ', $requiredHeaders));
        $headers = array_map(fn($v)=>trim((string)($v ?? '')), $rows[$headerIndex]);
        $out = [];
        for ($r=$headerIndex+1; $r<count($rows); $r++) {
            $obj = [];
            foreach ($headers as $i=>$h) if ($h !== '') $obj[$h] = $rows[$r][$i] ?? null;
            if ($obj) $out[] = $obj;
        }
        return $out;
    }

    public static function headerIndex(array $rows, array $requiredHeaders): int {
        foreach (array_slice($rows, 0, 30, true) as $i=>$row) {
            $normalized = array_map(fn($x)=>self::norm((string)($x ?? '')), $row);
            $ok = true;
            foreach ($requiredHeaders as $h) {
                if (!in_array(self::norm($h), $normalized, true)) { $ok = false; break; }
            }
            if ($ok) return (int)$i;
        }
        return -1;
    }

    private static function sharedStrings(ZipArchive $zip): array {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        $sx = simplexml_load_string($xml);
        if (!$sx) return [];
        $sx->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $out = [];
        foreach ($sx->xpath('//x:si') ?: [] as $si) {
            $parts = [];
            if (isset($si->t)) $parts[] = (string)$si->t;
            foreach ($si->r ?? [] as $r) if (isset($r->t)) $parts[] = (string)$r->t;
            $out[] = implode('', $parts);
        }
        return $out;
    }

    private static function firstSheetPath(ZipArchive $zip): string {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) return 'xl/worksheets/sheet1.xml';
        $wb = simplexml_load_string($workbook); $rs = simplexml_load_string($rels);
        if (!$wb || !$rs) return 'xl/worksheets/sheet1.xml';
        $wb->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $wb->registerXPathNamespace('r','http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = $wb->xpath('//x:sheets/x:sheet') ?: [];
        if (!$sheets) return 'xl/worksheets/sheet1.xml';
        $attrs = $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = (string)($attrs['id'] ?? '');
        $rs->registerXPathNamespace('p','http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($rs->xpath('//p:Relationship') ?: [] as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $target = ltrim((string)$rel['Target'], '/');
                return str_starts_with($target,'xl/') ? $target : 'xl/' . $target;
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function columnIndex(string $ref): int {
        if (!preg_match('/^([A-Z]+)/i', $ref, $m)) return 0;
        $letters = strtoupper($m[1]); $n = 0;
        for ($i=0; $i<strlen($letters); $i++) $n = $n*26 + (ord($letters[$i])-64);
        return max(0, $n-1);
    }

    private static function norm(string $v): string {
        $v = trim($v);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        return mb_strtolower($v, 'UTF-8');
    }
}
