# Test Checklist — v0.2.0

## Upgrade
- [ ] Terminal upgrade returns [OK].
- [ ] Existing Admin login still works.
- [ ] Existing Developer login still works.
- [ ] System Health shows migration `002_phase2`.
- [ ] daily_reports/daily_report_rows/report_revisions = READY.

## Company / Panel
- [ ] Create a Company.
- [ ] Create two Panels under one Company.
- [ ] Change Panel name display mode independently.
- [ ] Deactivate/reactivate a Panel.

## AccountSettings
- [ ] Upload a valid XLSX AccountSettings.
- [ ] Accounts are created.
- [ ] Re-upload same file: no duplicates.
- [ ] Edit one display name; re-sync same file; manual display name remains.
- [ ] Change one Account rate type to special; save.
- [ ] Search Username/name works.
- [ ] Remove one Username from a test XLSX and sync with Keep: account remains active.
- [ ] Repeat with Deactivate: missing account becomes inactive.
- [ ] Import a file mostly matching another Panel: import is blocked unless explicit override is checked.
- [ ] Import history shows filename/date/stats.

## Mobile
- [ ] Company list usable at ~375px width.
- [ ] Panel forms do not force horizontal page scroll.
- [ ] Focusing inputs on iPhone does not auto-zoom page.
