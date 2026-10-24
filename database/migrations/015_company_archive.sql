-- Archive preserves historical reports/transfers and their foreign keys.
ALTER TABLE companies ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER active;
