# سلطان حساب | Soltan Hesab

وب‌اپ حسابداری سبک برای مدیریت شرکت‌ها، پنل‌ها و حساب‌ها، ورود فایل‌های AccountSettings و Report_WL، محاسبهٔ گزارش‌های روزانه و نگهداری دفتر حساب مشتریان.

**نسخهٔ این سورس: `0.4.12`**  
**فناوری‌ها:** PHP 8.1+، MySQL/MariaDB، JavaScript و CSS بدون فریم‌ورک

## امکانات اصلی

- ورود Admin و Developer با Session، CSRF و محدودسازی تلاش ورود
- مدیریت Company، Panel و AccountSettings
- پردازش Report_WL، پیش‌نمایش قابل ویرایش و ثبت نهایی گزارش
- محاسبهٔ حسابداری در سرویس PHP سمت سرور
- مدیریت مشتری و گروه‌بندی حساب‌ها
- گزارش‌های روزانه، ماهانه، چندماهه و ریز حساب
- Migrationهای نسخه‌بندی‌شده و صفحهٔ نصب
- خروجی Excel، چاپ/PDF و گزارش مشتری

## ساختار پروژه

```text
app/                    منطق برنامه و سرویس‌ها
assets/                 CSS، JavaScript و راهنمای فونت‌ها
config/                 نمونه تنظیمات؛ تنظیمات واقعی محلی است
database/migrations/    Migrationهای مرتب‌شدهٔ دیتابیس
docs/                   مستندات فنی و چک‌لیست‌ها
install/                 نصب اولیه
storage/                 فایل‌های اجرایی محلی؛ داده‌ها وارد Git نمی‌شوند
tests/                   تست دود بررسی فرمول حسابداری
upgrade/                 اسکریپت‌های ارتقا
index.php                برنامهٔ اصلی
```

## پیش‌نیازها

- PHP 8.1 یا جدیدتر با PDO MySQL، ZipArchive و SimpleXML
- MySQL یا MariaDB
- مرورگر مدرن؛ ورود Excel قدیمی ممکن است برای SheetJS به دسترسی شبکه نیاز داشته باشد

## نصب اولیه

1. یک دیتابیس MySQL/MariaDB و کاربر جداگانه برای برنامه بساز.
2. سورس را در Document Root دامنه قرار بده.
3. مطمئن شو `config/` و `storage/` هنگام نصب قابل‌نوشتن هستند.
4. صفحهٔ `/install/` را باز کن و مشخصات دیتابیس و دو حساب Admin و Developer را ثبت کن.
5. بعد از نصب، مسیر نصب را طبق سیاست هاست محدود کن و دسترسی نوشتن به `config/` را بردار.
6. با حساب Developer، صفحهٔ Health را بررسی کن و تست‌های زیر را اجرا کن.

Installer فایل `config/config.php` را می‌سازد. این فایل شامل رمز دیتابیس و Secret برنامه است و عمداً در Git قرار نمی‌گیرد. `config/config.example.php` فقط الگوی تنظیمات است.

## تست و بررسی

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
php tests/phase3_formula_smoke.php
```

در یک نصب فعال، بررسی‌های دیتابیس و نسخه را هم اجرا کن:

```bash
php verify_release.php
```

`verify_release.php` به دیتابیس نصب‌شده نیاز دارد و در محیط CI اجرا نمی‌شود.

## حریم داده و Git

تنظیمات واقعی، فایل‌های گزارش مشتری، فایل‌های Excel/CSV/PDF، لاگ‌ها، Backupها و وضعیت نصب در `.gitignore` هستند. قبل از Commit هر فایل تازه، مطمئن شو دادهٔ شخصی یا اعتبارنامه ندارد.

## مستندات

- [تغییرات نسخه](CHANGELOG.md)
- [ارتقا به 0.4.12](UPGRADE.md)
- [راهنمای استقرار cPanel](DEPLOY_CPANEL.md)
- [قواعد کسب‌وکار](docs/BUSINESS_RULES.md)
- [معماری](docs/ARCHITECTURE.md)
- [چک‌لیست تست](docs/TEST_CHECKLIST.md)

این Repository سورس نسخهٔ `0.4.12` را نگه می‌دارد. تغییرات نسخه‌های بالاتر باید از سورس کامل همان Release وارد شوند؛ فایل‌های ناقص یا خروجی‌های Deploy جایگزین سورس Release نیستند.
