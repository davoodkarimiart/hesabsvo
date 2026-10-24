<?php
/** Display-only report titles and date captions; never changes financial amounts. */
declare(strict_types=1);

final class ReportHeading626 {
    public static function title(array $data): string {
        $kind = match ((string)($data['type'] ?? '')) {
            'detail' => 'ریز پرداخت',
            'customer_detail' => 'ریز پرداخت مشتری',
            'daily' => 'روزانه',
            'monthly' => 'ماهانه',
            'multi' => 'چندماهه',
            'cumulative' => 'تجمعی',
            default => 'گزارش',
        };
        $names = (array)($data['context']['companies'] ?? []);
        if (!$names) {
            foreach ((array)($data['rows'] ?? []) as $r) {
                $name = trim((string)($r['company_name'] ?? ''));
                if ($name !== '') $names[] = $name;
            }
        }
        $names = array_values(array_unique(array_filter(array_map('trim', $names), static fn($s) => $s !== '')));
        return $kind . ($names ? ' ' . implode('، ', $names) : '');
    }

    public static function period(array $data): string {
        $filters = (array)($data['filters'] ?? []);
        $context = (array)($data['context'] ?? []);
        $start = trim((string)($filters['from'] ?? ''));
        $end = trim((string)($filters['to'] ?? ''));
        // Without an explicit filter, show the real date span of rows included in this report.
        if ($start === '') $start = (string)($context['from'] ?? '');
        if ($end === '') $end = (string)($context['to'] ?? '');
        $convert = static function(string $date): string {
            if ($date === '') return '';
            try {
                return AccountingService::displayBusinessDate(AccountingService::normalizeBusinessDate($date));
            } catch (Throwable) { return $date; }
        };
        $from = $convert($start);
        $to = $convert($end);
        if ($from !== '' && $to !== '') return $from === $to ? 'تاریخ: ' . $from : 'بازه: ' . $from . ' تا ' . $to;
        if ($from !== '') return 'از تاریخ: ' . $from;
        if ($to !== '') return 'تا تاریخ: ' . $to;
        return '';
    }

    public static function subtitle(array $data, string $unit = ''): string {
        $items = array_filter([self::period($data), $unit !== '' ? 'واحد مبلغ: ' . $unit : '']);
        return implode(' | ', $items);
    }
}
