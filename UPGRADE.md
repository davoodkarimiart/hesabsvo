# Upgrade to v0.4.12

No database migration is required. Overlay the release on the existing installation. Preserve `config/config.php` and `storage/installed.lock`.

After deployment run `php verify_release.php`, then hard-refresh browser assets.
