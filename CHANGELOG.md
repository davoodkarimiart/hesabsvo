# Changelog

## v0.5.6
- Restored the original `ریز پرداخت` report as its own mode with Company/Panel/date filters.
- Added a separate `ریز پرداخت مشتری` mode with customer selection; customer selection intentionally overrides Company/Panel and returns that customer's detail across all companies/panels.
- Dashboard chart now uses cumulative site result so direction toward Site Win / Site Loss is visually meaningful.
- Added explicit overall trend status and per-company final status in the legend.
- Dashboard point tooltip is clamped inside the chart card on desktop/mobile.
- Customer directory still defaults to Persian alphabetical A→Y; live search binding hardened across input/keyup/search/change.
- PWA cache bumped to v056.
- No database migration.

## v0.5.5
- Dashboard analytics + customer/report semantics correction.
