<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/helpers.php';

$configFile = base_path('config/config.php');
$lockFile = base_path('storage/installed.lock');
if (is_file($configFile) && is_file($lockFile)) {
    header('Location: ../index.php?page=login');
    exit;
}

$checks = [
    'PHP 8.1 یا بالاتر' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'fileinfo' => extension_loaded('fileinfo'),
    'JSON' => extension_loaded('json'),
    'ZipArchive (برای XLSX)' => class_exists('ZipArchive'),
    'SimpleXML (برای XLSX)' => function_exists('simplexml_load_string'),
    'پوشه config قابل نوشتن' => is_writable(base_path('config')),
    'پوشه storage قابل نوشتن' => is_writable(base_path('storage')),
];
$canInstall = !in_array(false, $checks, true);
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canInstall) {
    try {
        $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
        $dbPort = (int)($_POST['db_port'] ?? 3306);
        $dbName = trim((string)($_POST['db_name'] ?? ''));
        $dbUser = trim((string)($_POST['db_user'] ?? ''));
        $dbPass = (string)($_POST['db_pass'] ?? '');
        $adminUsername = trim((string)($_POST['admin_username'] ?? 'admin'));
        $adminPassword = (string)($_POST['admin_password'] ?? '');
        $developerUsername = trim((string)($_POST['developer_username'] ?? 'developer'));
        $developerPassword = (string)($_POST['developer_password'] ?? '');
        $timezone = trim((string)($_POST['timezone'] ?? 'Asia/Tehran'));

        if ($dbName === '' || $dbUser === '') {
            throw new RuntimeException('نام دیتابیس و کاربر دیتابیس الزامی است.');
        }
        if ($adminUsername === '' || strlen($adminPassword) < 8) {
            throw new RuntimeException('نام کاربری Admin و رمز حداقل ۸ کاراکتری الزامی است.');
        }
        if ($developerUsername === '' || strlen($developerPassword) < 8) {
            throw new RuntimeException('نام کاربری Developer و رمز حداقل ۸ کاراکتری الزامی است.');
        }
        if (strcasecmp($adminUsername, $developerUsername) === 0) {
            throw new RuntimeException('نام کاربری Admin و Developer باید متفاوت باشد.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);

        $migrationFiles = glob(base_path('database/migrations/*.sql')) ?: [];
        sort($migrationFiles, SORT_NATURAL);
        foreach ($migrationFiles as $migrationFile) {
            $version = basename($migrationFile, '.sql');
            $sql = file_get_contents($migrationFile);
            if ($sql === false) throw new RuntimeException('خواندن Migration ممکن نشد: ' . $version);
            $pdo->exec($sql);
            $pdo->prepare('INSERT IGNORE INTO migrations(version, applied_at) VALUES(?, NOW())')->execute([$version]);
        }

        $upsert = $pdo->prepare('INSERT INTO users(username,password_hash,role,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role=VALUES(role), active=1');
        $upsert->execute([$adminUsername, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);
        $upsert->execute([$developerUsername, password_hash($developerPassword, PASSWORD_DEFAULT), 'developer']);

        $pdo->prepare("INSERT INTO app_meta(meta_key, meta_value) VALUES('app_version','0.2.0') ON DUPLICATE KEY UPDATE meta_value='0.2.0'")->execute();

        $secret = bin2hex(random_bytes(32));
        $config = "<?php\nreturn " . var_export([
            'app'=>['name'=>'سلطان حساب','url'=>'','timezone'=>$timezone,'installed'=>true,'secret'=>$secret,'debug'=>false],
            'db'=>['host'=>$dbHost,'port'=>$dbPort,'name'=>$dbName,'user'=>$dbUser,'pass'=>$dbPass,'charset'=>'utf8mb4'],
            'telegram'=>['enabled'=>false,'bot_token'=>'','chat_id'=>''],
            'backup'=>['keep_local'=>7,'schedule'=>'daily'],
        ], true) . ";\n";

        if (file_put_contents($configFile, $config, LOCK_EX) === false) {
            throw new RuntimeException('نوشتن config/config.php ممکن نشد.');
        }
        file_put_contents($lockFile, 'installed_at=' . date(DATE_ATOM) . PHP_EOL, LOCK_EX);
        @chmod($configFile, 0640);
        $success = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#3559e0">
<title>نصب سلطان حساب</title>
<link rel="stylesheet" href="../assets/app.css">
</head>
<body class="installer-page">
<main class="install-shell">
<section class="install-card install-card-v2">
    <div class="installer-hero">
        <div class="logo">س</div>
        <div>
            <div class="installer-kicker">راه‌اندازی اولیه</div>
            <h1>نصب سلطان حساب</h1>
            <p>نسخه 0.2.0 • Company / Panel / AccountSettings</p>
        </div>
    </div>

    <div class="installer-steps" aria-label="مراحل نصب">
        <div class="installer-step active"><b>۱</b><span>بررسی سرور</span></div>
        <div class="installer-step active"><b>۲</b><span>دیتابیس</span></div>
        <div class="installer-step active"><b>۳</b><span>کاربران</span></div>
        <div class="installer-step"><b>۴</b><span>پایان</span></div>
    </div>

    <section class="installer-block">
        <div class="installer-block-head"><div><span class="section-badge">۱</span><h2>بررسی پیش‌نیازها</h2></div><small>قبل از نصب همه موارد باید سبز باشند.</small></div>
        <div class="check-grid"><?php foreach($checks as $name=>$ok): ?><div class="check <?= $ok?'ok':'bad' ?>"><span class="check-icon"><?= $ok?'✓':'×' ?></span><span><?= e($name) ?></span></div><?php endforeach; ?></div>
    </section>

    <?php if($success): ?>
        <div class="notice ok install-success">
            <b>نصب با موفقیت انجام شد.</b>
            <span>هر دو حساب Admin و Developer ساخته شدند و Installer قفل شد.</span>
            <a class="btn primary" href="../index.php?page=login">ورود به سلطان حساب</a>
        </div>
    <?php elseif($error): ?>
        <div class="notice bad"><b>نصب انجام نشد.</b><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <?php if(!$success && $canInstall): ?>
    <form method="post" class="installer-form">
        <section class="installer-block">
            <div class="installer-block-head"><div><span class="section-badge">۲</span><h2>اتصال دیتابیس</h2></div><small>اطلاعاتی که در cPanel ساخته‌ای.</small></div>
            <div class="form-grid">
                <label>Host<input name="db_host" value="<?= e((string)($_POST['db_host'] ?? 'localhost')) ?>" required autocomplete="off"></label>
                <label>Port<input name="db_port" value="<?= e((string)($_POST['db_port'] ?? '3306')) ?>" inputmode="numeric" required></label>
                <label>Database<input name="db_name" value="<?= e((string)($_POST['db_name'] ?? '')) ?>" required autocomplete="off"></label>
                <label>DB User<input name="db_user" value="<?= e((string)($_POST['db_user'] ?? '')) ?>" required autocomplete="off"></label>
                <label class="wide">DB Password<input name="db_pass" type="password" autocomplete="new-password"></label>
            </div>
        </section>

        <section class="installer-block">
            <div class="installer-block-head"><div><span class="section-badge">۳</span><h2>حساب‌های اولیه</h2></div><small>Admin برای کار روزانه؛ Developer برای ابزارهای فنی.</small></div>
            <div class="role-grid">
                <div class="role-card admin-role">
                    <div class="role-title"><span>👤</span><div><b>Admin</b><small>مدیریت روزمره سیستم</small></div></div>
                    <label>نام کاربری Admin<input name="admin_username" value="<?= e((string)($_POST['admin_username'] ?? 'admin')) ?>" required autocomplete="username"></label>
                    <label>رمز Admin<input name="admin_password" type="password" minlength="8" required autocomplete="new-password"></label>
                </div>
                <div class="role-card developer-role">
                    <div class="role-title"><span>🛠️</span><div><b>Developer</b><small>Health، لاگ، Backup و MCP</small></div></div>
                    <label>نام کاربری Developer<input name="developer_username" value="<?= e((string)($_POST['developer_username'] ?? 'developer')) ?>" required autocomplete="username"></label>
                    <label>رمز Developer<input name="developer_password" type="password" minlength="8" required autocomplete="new-password"></label>
                </div>
            </div>
            <label class="installer-timezone">Timezone<input name="timezone" value="<?= e((string)($_POST['timezone'] ?? 'Asia/Tehran')) ?>" required></label>
        </section>

        <div class="installer-submit-wrap">
            <div class="tiny">با نصب، Schema ساخته می‌شود، دو کاربر اولیه ثبت می‌شوند و Installer قفل خواهد شد.</div>
            <button class="primary install-submit" type="submit">نصب سلطان حساب</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if(!$canInstall): ?><div class="notice bad">پیش‌نیازهای قرمز را در هاست فعال یا قابل‌نوشتن کن و سپس صفحه را تازه‌سازی کن.</div><?php endif; ?>
</section>
</main>
</body>
</html>
