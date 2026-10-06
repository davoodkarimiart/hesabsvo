# cPanel deployment — v0.5.2

Create a backup before deploying. Upload the release archive to the application root, extract the application files, then run PHP syntax checks, `verify_release.php`, and `tests/phase3_formula_smoke.php`. Remove the uploaded archive after validation. No database migration is required for v0.5.2.
