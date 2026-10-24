# انتشار سورس v0.6.30 روی GitHub

مخزن هدف فقط: **https://github.com/davoodkarimiart/hesabsvo**. این بسته باید **Extract** و فایل‌هایش جایگزین فایل‌های قبلی شود. صرفاً گذاشتن فایل ZIP در Repository باعث آپدیت کد نمی‌شود.

مخزن `soltanhesab` محل نقشه راه است و نباید این سورس جایگزین آن شود.

## دستور پیشنهادی برای بورک / Codex / Work

> سورس مرجع در `davoodkarimiart/hesabsvo` را با محتویات این ZIP جایگزین کن، تاریخچه Git را نگه دار و `.github`، migrations، installer و تست‌ها را ثبت کن. قبل از commit بررسی کن هیچ فایل حساس یا داده مشتری داخل diff نیست. نسخه باید `0.6.30` باشد. Pull Request یا commit بساز و نتایج تست را گزارش بده. Force-push نکن.

### روش خط فرمان در کلون تازه

```bash
ZIP_PATH="$HOME/Downloads/hesabsvo-v0.6.30-github-full-source.zip"
git clone https://github.com/davoodkarimiart/hesabsvo.git
cd hesabsvo
# اول شاخه جاری و تغییرات احتمالی جدید را بررسی کنید.
git status --short
# فقط در کلون تازه و تمیز، فایل‌های tracked نسخه قبل حذف شوند:
git rm -r -- .
unzip -oq "$ZIP_PATH" -d .
git add -A
git status --short
# Sensitive files check (these should NOT be present in git changes):
git diff --cached --name-only | grep -E '(^config/config\.php$|^storage/installed\.lock$|^storage/logs/|(^|/)error_log$|\.sql\.gz$)' && { echo 'STOP: private files in commit'; exit 1; } || true
php tests/phase3_formula_smoke.php
git commit -m "release: consolidate complete source v0.6.30"
git push origin main
```

اگر `main` در این فاصله جلو رفته، تغییرات جدید را بررسی و ادغام کنید؛ `force-push` مجاز نیست. انتشار نهایی بعد از تست واقعی نصب تازه انجام شود.
