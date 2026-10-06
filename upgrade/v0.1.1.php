<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::requireDeveloper();

$error = '';
$success = '';
$pdo = Database::connection();
$currentAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn();

if (is_post()) {
    try {
        csrf_verify();
        $username = trim((string)($_POST['admin_username'] ?? ''));
        $password = (string)($_POST['admin_password'] ?? '');
        if ($username === '' || strlen($password) < 8) {
            throw new RuntimeException('نام کاربری Admin و رمز حداقل ۸ کاراکتری الزامی است.');
        }
        $stmt = $pdo->prepare("INSERT INTO users(username,password_hash,role,active) VALUES(?,?, 'admin',1) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='admin', active=1");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $pdo->prepare("INSERT INTO app_meta(meta_key, meta_value) VALUES('app_version','0.1.1') ON DUPLICATE KEY UPDATE meta_value='0.1.1'")->execute();
        Logger::audit('upgrade.0_1_1.admin_created', ['username'=>$username]);
        Logger::system('upgrade.0_1_1.completed', ['admin_username'=>$username]);
        $success = 'حساب Admin ساخته/به‌روزرسانی شد و نسخه برنامه روی 0.1.1 ثبت شد.';
        $currentAdmin = 1;
    } catch (Throwable $e) {
        Logger::error('upgrade.0_1_1.failed', ['error'=>$e->getMessage()]);
        $error = $e->getMessage();
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>ارتقا 0.1.1</title><link rel="stylesheet" href="../assets/app.css"></head><body><main class="install-shell"><section class="card install-card"><div class="brandline"><div class="logo">س</div><div><h1>ارتقا به 0.1.1</h1><p>تکمیل حساب Admin برای نصب قبلی</p></div></div><?php if($success): ?><div class="notice ok"><?= e($success) ?></div><?php endif; ?><?php if($error): ?><div class="notice bad"><?= e($error) ?></div><?php endif; ?><?php if($currentAdmin): ?><div class="notice ok">حداقل یک Admin فعال در سیستم وجود دارد.</div><?php else: ?><div class="notice bad">در نصب 0.1.0 فقط Developer ساخته شده بود. این صفحه Admin را اضافه می‌کند.</div><?php endif; ?><form method="post" class="stack"><?= csrf_field() ?><label>نام کاربری Admin<input name="admin_username" value="admin" required autocomplete="username"></label><label>رمز Admin<input name="admin_password" type="password" minlength="8" required autocomplete="new-password"></label><button class="primary" type="submit">ساخت / به‌روزرسانی Admin</button></form><a class="btn ghost" href="../index.php?page=dashboard">بازگشت به داشبورد</a></section></main></body></html>
