# آپدیت ریپوزیتوری hesabsvo با این ZIP

ریپوزیتوری: https://github.com/davoodkarimiart/hesabsvo

آخرین نسخه مشاهده‌شده پیش از تهیه این بسته: `0.5.6` روی `main` (کامیت `15f80058`). بسته فعلی: `0.6.25`.

## روش پیشنهادی با Git (روی کامپیوتر)

1. بسته ZIP را از ChatGPT دانلود کنید و اسم و مسیر آن را مشخص کنید.
2. یک clone تازه از GitHub بگیرید؛ **روی کپی پوشه هاست زنده کار نکنید**.
3. کدهای نسخه قبلی داخل clone را با فایل‌های ZIP جایگزین کنید، `git add -A` بزنید و تفاوت فایل‌ها را مرور کنید.
4. قبل از push با `git status` بررسی کنید هیچ `config/config.php`, `storage/installed.lock`, `error_log` و داده خصوصی وارد commit نشده باشد.

نمونه دستورات لینوکس / مک (در فولدر جدا و با ZIP دانلودشده کنار خودتان):

```bash
# Archive file must be at this exact location or change ZIP_PATH.
ZIP_PATH="$HOME/Downloads/hesabsvo-v0.6.25-github-fresh-install.zip"
git clone https://github.com/davoodkarimiart/hesabsvo.git
cd hesabsvo

# Remove OLD tracked release files from this fresh clone, keep .git history intact.
git rm -r -- .
unzip -oq "$ZIP_PATH" -d .

git add -A
git status --short
# Review all changed paths before committing.
git commit -m "release: full clean-install source v0.6.25"
git push origin main
```

نکته: اگر شاخه main بعد از ساخت بسته تغییر کرده باشد، اول تغییرات جدید را بررسی و با آن ادغام کنید؛ blind force-push نکنید. این دستورها بعد از `git rm` همه فایل‌های قدیمی را پاک می‌کنند و فایل‌های این بسته را جایگزین می‌کنند؛ روی مخزن کلون‌شده مخصوص این کار اجرا شوند.

**صرف آپلود کردن فایل ZIP داخل GitHub سورس‌ها را به‌روزرسانی نمی‌کند؛** ZIP باید Extract شود و محتویاتش commit شوند.

## بررسی پس از آپدیت

- فایل `VERSION` در GitHub باید `0.6.25` باشد.
- پوشه `database/migrations` باید فایل `014_company_transfer_settings.sql` را داشته باشد.
- فایل‌های `transfer_page619.php`, `transfer_calendar620.php` و `assets/transfer-receipt619.js` باید حضور داشته باشند.
- workflow در `.github/workflows/php-quality.yml` باید lint PHP و JavaScript را انجام دهد.
- هیچ رمز/توکن، دیتابیس، لاگ یا نسخه بکاپ در مخزن نباشد.
