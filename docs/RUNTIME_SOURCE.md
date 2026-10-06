# Runtime Source Policy

از v0.4.14 به بعد، GitHub منبع Canonical سورس اجرایی پروژه است. Release ZIP باید از همین Source tree ساخته شود.

## داخل Git
- PHP source
- CSS/JS
- migrations
- installer/upgrade scripts
- tests
- configuration examples
- product/architecture documentation

## خارج Git
- `config/config.php`
- passwords/tokens/secrets
- `storage/logs/*`
- backups
- uploaded Report_WL / AccountSettings customer data
- generated private exports
- licensed font binaries

اگر Runtime روی Production به‌صورت دستی تغییر کرد، قبل از Release بعدی باید diff آن با GitHub reconcile و سپس Commit شود.
