-- Clean-install / legacy-upgrade compatibility for company transfer settings.
-- These columns were added on running sites by historical CLI patch 0.6.12,
-- but that patch was never part of the original database/migrations directory.
-- Conditional SQL is compatible with MySQL and MariaDB without ADD COLUMN IF NOT EXISTS.
SET @soltan_role_ddl = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE companies ADD COLUMN company_role ENUM(''agent'',''master'') NULL DEFAULT NULL',
    'SET @soltan_noop = 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='companies' AND COLUMN_NAME='company_role'
);
PREPARE soltan_role_stmt FROM @soltan_role_ddl;
EXECUTE soltan_role_stmt;
DEALLOCATE PREPARE soltan_role_stmt;

SET @soltan_percent_ddl = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE companies ADD COLUMN transfer_percent DECIMAL(6,3) NULL DEFAULT NULL',
    'SET @soltan_noop = 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='companies' AND COLUMN_NAME='transfer_percent'
);
PREPARE soltan_percent_stmt FROM @soltan_percent_ddl;
EXECUTE soltan_percent_stmt;
DEALLOCATE PREPARE soltan_percent_stmt;
