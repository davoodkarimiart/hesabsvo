# v0.5.4
- Fixed Settings numeric validation for grouped/Persian/Arabic numeric input.
- Customer search is live and no longer requires Apply Filter for name matching.
- Hardened customer text/search fields against mobile password-manager/autofill prompts.
- Expanded keyboard Next flow to Settings/customer profile and search fields.
- PWA cache bumped to v054.

# v0.5.4

- Login scene now uses the exact desktop and portrait artwork extracted from the approved V28 HTML.
- Desktop and mobile crop/hitboxes now match the effective V23/V25 V28 rules exactly.
- Real PHP authentication remains unchanged behind the Golpooch reveal.
- PWA cache bumped to v053.
- No database migration.

# v0.5.2

- Dashboard: period-aware 7/30/90-day win/loss trend by company with win/loss ratio and net KPIs.
- Sharing: image shares no longer attach any caption/text. Native share sends the image file only; unsupported browsers fall back to image download only.
- PWA cache bumped to v052.

# Changelog

## 0.5.1 — Phase 8 UX polish 1
- Rebuilt Entry parameter layout: company/date/loss-percent first row on desktop, rate pair second row; mobile keeps date + loss percent and both rates compact.
- Added grouped thousands formatting to report and Settings rate inputs.
- Added client-side Persian alphabetical customer sorting.
- Densified Companies/Panels and Settings layouts.
- Increased effective report-grid mobile readability.
- Removed Golpooch explanatory chip and restored full-screen V32 crop/hitbox geometry.
- Added dashboard 7-report site-result trend chart.
- PWA cache bumped to v051.
