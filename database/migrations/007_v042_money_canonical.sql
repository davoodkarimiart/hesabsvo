-- v0.4.2: migrate historical thousand-Toman storage to canonical full-Toman storage.
-- Existing Phase 3/4 rows were calculated with rates like 200 meaning 200 thousand Toman.
-- Multiply stored Toman snapshots/totals by 1000 exactly once; migration tracking prevents re-run.
UPDATE daily_report_rows
SET rate_value_snapshot = rate_value_snapshot * 1000,
    commission = commission * 1000,
    received = received * 1000,
    paid = paid * 1000;

UPDATE daily_reports
SET total_received = total_received * 1000,
    total_paid = total_paid * 1000,
    total_commission = total_commission * 1000,
    site_net = site_net * 1000;

INSERT INTO app_meta(meta_key, meta_value) VALUES
('app_version','0.4.2'),
('schema_version','007_v042_money_canonical')
ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
