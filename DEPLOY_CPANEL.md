# cPanel deployment — v0.5.6

Application root: `~/public_html/soltan.toseno.ir`

```bash
cd ~/public_html/soltan.toseno.ir || exit 1
BACKUP="$HOME/soltanhesab-pre-v0.5.6-$(date +%Y%m%d-%H%M%S).tar.gz"
tar --exclude='./storage/backups/*' --exclude='./storage/report_sources/*' --exclude='./soltanhesab-release-v0.5.6.zip' -czf "$BACKUP" .
unzip -oq soltanhesab-release-v0.5.6.zip -d .
php -l index.php
php -l app/Services/ReportingService.php
php verify_release.php
php tests/phase3_formula_smoke.php
rm -f soltanhesab-release-v0.5.6.zip
echo "v0.5.6 deployed. Backup: $BACKUP"
```
