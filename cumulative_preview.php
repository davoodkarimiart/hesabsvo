<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    // The existing data service validates IDs, authorization and one-company scope.
    $selected = require __DIR__.'/cumulative_data.php';
    $raw = $_GET['ids'] ?? [];
    $ids = array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
    $reports = [];
    foreach ($ids as $id) {
        $one = AccountingService::reportWithRows($id);
        if (!$one || (int) $one['company_id'] !== (int) $selected['company_id']) {
            throw new RuntimeException('گزارش انتخابی نامعتبر است.');
        }
        $reports[] = $one;
    }
    $settings = SettingsService::all();
    require_once __DIR__.'/app/Support/CumulativeNet614.php';
    $factor = (float) SettingsService::scaleFactor($settings);
    $netted = CumulativeNet614::summary($reports, $factor);
    echo json_encode([
        'ok' => true,
        'selected_count' => count($reports),
        'title' => 'تجمعی '.$selected['company_name'],
        'date' => $selected['date_label'],
        'unit' => SettingsService::moneyUnit($settings),
        'received' => $netted['total_received'],
        'paid' => $netted['total_paid'],
        'total_received' => $netted['total_received'],
        'total_paid' => $netted['total_paid'],
        'net' => $netted['net'],
        'recv' => $netted['recv'],
        'pay' => $netted['pay'],
        'factor' => $factor,
        'locale' => ($settings['digit_mode'] ?? 'en') === 'fa' ? 'fa-IR' : 'en-US',
        'colors' => [
            'recv' => SettingsService::themeFill($settings['theme_recv'], $settings),
            'pay' => SettingsService::themeFill($settings['theme_pay'], $settings),
            'win' => SettingsService::themeFill($settings['theme_site_win'] ?? $settings['theme_pay'], $settings),
            'loss' => SettingsService::themeFill($settings['theme_site_loss'] ?? $settings['theme_recv'], $settings),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
