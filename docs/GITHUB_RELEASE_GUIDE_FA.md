# انتشار نسخه نصبی در GitHub

## یک بار برای هر نسخه

1. [GitHub Actions → Publish customer installer](https://github.com/davoodkarimiart/hesabsvo/actions/workflows/publish-installer.yml) را باز کنید.
2. روی **Run workflow** کلیک کنید، شاخه **main** و نسخه `0.6.53` را انتخاب کنید.
3. اجرا باید با وضعیت سبز تمام شود. Workflow فایل ZIP و `SHA256SUMS.txt` را از سورس همان Commit می‌سازد.
4. از [Releases](https://github.com/davoodkarimiart/hesabsvo/releases) فایل `soltanhesab-v0.6.53-customer-install.zip` را در قسمت **Assets** دانلود کنید.

> GitHub Releases کنونی هنوز با اجرای موفق Workflow ایجاد می‌شود؛ صرف وجود فایل Workflow به معنی بارگذاری فایل نیست.

## کنترل قبل از تحویل

- لاگ Workflow شامل موفقیت تست Syntax تمامی فایل‌های PHP، ۱۶ migration و سلامت فایل ZIP باشد.
- فایل ZIP را با `SHA256SUMS.txt` همان Release تطبیق دهید.
- هش این فایل تولیدشده از GitHub لزوماً با هش بسته تحویل قبلی یکسان نیست، چون بازتولید از محتوای مخزن و متادیتای آرشیو انجام می‌شود.
- **روی سایت نصب‌شده مشتری Extract نکنید.** این فایل فقط برای نصب تازه است.
- برنامه‌نویس مسئول است اولین اجرای بسته خروجی GitHub را روی یک محیط خالی مجزا بررسی کند.
