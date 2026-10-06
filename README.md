# Soltan Hesab

Source repository for Soltan Hesab, currently at **v0.5.2** (host archive dated 2026-10-06).

## Version 0.5.2

- Adds a dashboard win/loss trend for 7, 30, or 90 days, split by company, with win/loss ratio and net KPIs.
- Shares report images without captions; unsupported browsers download the image only.
- Bumps the PWA shell cache to v052.

## Setup

1. Copy `config/config.example.php` to `config/config.php`.
2. Set the database credentials and a unique application secret in `config/config.php`.
3. Create the database and apply `database/migrations/` in numeric order.
4. Configure the web root to this directory and follow `DEPLOY_CPANEL.md` for production deployment.

Never commit `config/config.php`, `.user.ini`, runtime logs, backups, uploaded report sources, installation state, or production exports. The runtime directories are represented by empty `.gitkeep` files.

See `CHANGELOG.md`, `UPGRADE.md`, and `docs/` for release notes, upgrade steps, and test checklists. Run `php tests/phase3_formula_smoke.php` for the accounting formula smoke test.
