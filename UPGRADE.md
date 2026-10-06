# Upgrade to v0.5.6

No database migration is required.

1. Back up the current installation.
2. Extract this package over the application root.
3. Run `php verify_release.php` and `php tests/phase3_formula_smoke.php`.
4. Fully close/reopen the mobile PWA/tab so cache v056 activates.
