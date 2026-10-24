# Changelog

## 0.6.53 — Phase 11 MCP and backup hotfix
- Added an authenticated JSON-RPC MCP endpoint with OAuth authorization, PKCE, expiring scoped tokens, and per-tool permission checks.
- Added bounded report, customer, transfer, system-health, and developer diagnostics tools; financial row snapshots are returned only through the explicitly requested report-detail tool.
- Added developer backup tools and a CLI-only scheduled backup entry point with retention and optional Telegram delivery.
- Added developer-only log browsing/export and privacy-limited authenticated browser error telemetry.
- No database migration was included. Runtime OAuth credentials, Telegram settings, backups, and logs remain under protected storage and are excluded from Git.
- Live handoff reports the site healthy and `create_backup` successful. Telegram delivery was not confirmed; end-to-end OAuth/MCP and restore acceptance remain separate checks.

## 0.6.30 — Unified GitHub source
- Real Settings tabs, anti-burst login throttling, optional server-verified Cloudflare Turnstile.

## 0.6.29 — Account security
- Change password, one-time recovery codes, login protection and session invalidation; auth migration 016.

## 0.6.28 — Archived-company management
- Restore makes company active; guarded permanent purge for previously cancelled/deleted financial data.

## 0.6.27 — Company archive and report dates
- Enlarged/bold date labels; safe company delete/archive, migration 015.

## 0.6.26 — Report headers
- Combined Excel title updated; numeric font emphasis; selected company names/date period shown in reports.
