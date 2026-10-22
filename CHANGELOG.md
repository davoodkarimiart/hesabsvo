# Changelog

## 0.6.25 (2026-10-22) — Clean-source GitHub handoff
- Full reconstructed working tree from local v0.6.25 source with latest transfer UI, report exports and accounting code.
- Sanitized server-only files: config, installation lock, logs, backups, prior patch installers and temporary snapshots.
- Fixed clean-install metadata to record VERSION instead of 0.2.0.
- Added conditional migration 014 for company_role and transfer_percent, which originally existed only in a historical CLI patch.
- Added private-directory access safeguards, GitHub ignore rules and clean-deploy/GitHub guides.
- No import of production accounting data. No changes to accounting formulas.
