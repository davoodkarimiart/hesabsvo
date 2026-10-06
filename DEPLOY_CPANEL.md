# cPanel deploy v0.4.12

Upload `soltanhesab-release-v0.4.12.zip` to `~/public_html/soltan.toseno.ir/`, then run:

```bash
cd ~/public_html/soltan.toseno.ir || exit 1
BACKUP="$HOME/soltanhesab-pre-v0.4.12-$(date +%Y%m%d-%H%M%S).tar.gz"
tar --exclude='./storage/backups/*' --exclude='./storage/report_sources/*' --exclude='./soltanhesab-release-v0.4.12.zip' -czf "$BACKUP" .
unzip -oq soltanhesab-release-v0.4.12.zip -d .
php -l index.php
php -l app/Services/ReportingService.php
php verify_release.php
rm -f soltanhesab-release-v0.4.12.zip
echo "v0.4.12 deployed. Backup: $BACKUP"
```
