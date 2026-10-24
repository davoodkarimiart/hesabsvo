# همگام‌سازی سورس ۰٫۶٫۵۳ در GitHub

مخزن سورس اجرایی: `davoodkarimiart/hesabsvo`. مخزن `davoodkarimiart/soltanhesab` فقط نقشه‌راه و سوابق پروژه است.

این handoff از snapshot نسخهٔ ۰٫۶٫۴۹ و پچ‌های رسمی ۰٫۶٫۵۰ تا ۰٫۶٫۵۳ ساخته شده است. فایل‌های مخزن GitHub را با سورس همسان کنید، ولی `.github`، migrationها، محافظ‌های `.htaccess` و فایل‌های جاری مخزن را حفظ کنید. انتشار از طریق branch و PR انجام شود؛ force-push نکنید.

قبل از merge:

- `VERSION` باید `0.6.53` باشد.
- `config/config.php`، کلیدها، `storage/mcp/`، tokenهای Telegram/OAuth، لاگ‌ها، بکاپ‌ها، فایل‌های مشتری و archiveهای ZIP نباید در تغییرات باشند.
- workflow باید نسخه و فایل‌های Phase 11 را بررسی کند، lint PHP/JS و smoke testها را اجرا کند.
- وضعیت OAuth/MCP، backup/restore و ارسال Telegram در یادداشت انتشار دقیق و بدون ادعای تأییدنشده ثبت شود.

برای ارتقای سایت فعال این ZIP را روی هاست Extract نکنید؛ این بسته برای همگام‌سازی سورس و نصب تازه است.
