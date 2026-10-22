<?php
declare(strict_types=1);

/**
 * View-only netting of a selected set of saved report rows.
 * No database writes. Uses a stable customer ID where possible.
 */
final class CumulativeNet614
{
    public static function summary(array $reports, float $factor = 1.0): array
    {
        if (!is_finite($factor) || $factor <= 0) {
            throw new RuntimeException('ضریب نمایش مبلغ نامعتبر است.');
        }
        $groups = [];
        $grossReceived = 0.0;
        $grossPaid = 0.0;
        foreach ($reports as $report) {
            foreach (($report['rows'] ?? []) as $row) {
                $name = trim((string) ($row['customer_name'] ?? ''));
                if ($name === '') $name = trim((string) ($row['display_name_snapshot'] ?? ''));
                if ($name === '') $name = trim((string) ($row['original_name_snapshot'] ?? ''));
                if ($name === '') $name = trim((string) ($row['source_username'] ?? ''));
                $customerId = (int) ($row['customer_id'] ?? 0);
                $normalizedName = preg_replace('/\s+/u', ' ', $name);
                $normalizedName = is_string($normalizedName) ? $normalizedName : $name;
                $key = $customerId > 0
                    ? 'customer:'.$customerId
                    : 'name:'.(function_exists('mb_strtolower') ? mb_strtolower($normalizedName, 'UTF-8') : strtolower($normalizedName));
                $received = (float) ($row['received'] ?? 0);
                $paid = (float) ($row['paid'] ?? 0);
                if (!is_finite($received) || !is_finite($paid) || $received < 0 || $paid < 0) {
                    throw new RuntimeException('داده مالی نامعتبر در گزارش انتخابی.');
                }
                if (!isset($groups[$key])) {
                    $groups[$key] = ['name' => $name, 'received' => 0.0, 'paid' => 0.0];
                }
                $groups[$key]['received'] += $received;
                $groups[$key]['paid'] += $paid;
                $grossReceived += $received;
                $grossPaid += $paid;
            }
        }
        $recv = [];
        $pay = [];
        $receivedTotal = 0.0;
        $paidTotal = 0.0;
        foreach ($groups as $group) {
            $balance = $group['received'] - $group['paid'];
            if (abs($balance) < 1e-7) continue;
            // The monetary values in the visible rows are integer display units.
            // Sum precisely those same visible, per-customer rounded amounts.
            $displayValue = round(abs($balance) / $factor, 0, PHP_ROUND_HALF_UP);
            if ($displayValue < 1) continue;
            $canonicalValue = $displayValue * $factor;
            $item = ['name' => $group['name'], 'amount' => $canonicalValue];
            if ($balance > 0) {
                $recv[] = $item;
                $receivedTotal += $canonicalValue;
            } else {
                $pay[] = $item;
                $paidTotal += $canonicalValue;
            }
        }
        return [
            'recv' => $recv,
            'pay' => $pay,
            'total_received' => $receivedTotal,
            'total_paid' => $paidTotal,
            // Unrounded financial site balance is authoritative: DO NOT net totals
            // after independently rounding displayed customer amounts.
            'net' => $grossReceived - $grossPaid,
            'gross_received' => $grossReceived,
            'gross_paid' => $grossPaid,
            'customer_count' => count($groups),
        ];
    }
}
