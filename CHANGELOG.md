# Changelog

## 0.6.30 — Unified GitHub source, pending stable acceptance
- Real Settings tabs, anti-burst login throttling, optional server-verified Cloudflare Turnstile.

## 0.6.29 — Account security
- Change password, one-time recovery codes, login protection and session invalidation; auth migration 016.

## 0.6.28 — Archived-company management
- Restore makes company active; guarded permanent purge for previously cancelled/deleted financial data.

## 0.6.27 — Company archive and report dates
- Enlarged/bold date labels; safe company delete/archive, migration 015.

## 0.6.26 — Report headers
- Combined Excel title updated; numeric font emphasis; selected company names/date period shown in reports.

# Changelog

## 0.6.25 (2026-10-22) — Clean-source GitHub handoff
- Full reconstructed working tree from local v0.6.25 source with latest transfer UI, report exports and accounting code.
- Sanitized server-only files: config, installation lock, logs, backups, prior patch installers and temporary snapshots.
- Fixed clean-install metadata to record VERSION instead of 0.2.0.
- Added conditional migration 014 for company_role and transfer_percent, which originally existed only in a historical CLI patch.
- Added private-directory access safeguards, GitHub ignore rules and clean-deploy/GitHub guides.
- No import of production accounting data. No changes to accounting formulas.
