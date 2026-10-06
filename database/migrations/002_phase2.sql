CREATE TABLE IF NOT EXISTS companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_name (name),
  CONSTRAINT fk_companies_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  display_name_mode ENUM('display','original') NOT NULL DEFAULT 'display',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_panel_company_name (company_id, name),
  KEY idx_panels_company (company_id, active),
  CONSTRAINT fk_panels_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_panels_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  panel_id BIGINT UNSIGNED NOT NULL,
  source_username VARCHAR(190) NOT NULL,
  source_uuid VARCHAR(190) NULL,
  first_name VARCHAR(190) NULL,
  last_name VARCHAR(190) NULL,
  original_name VARCHAR(380) NOT NULL,
  display_name VARCHAR(380) NULL,
  rate_type ENUM('general','special') NOT NULL DEFAULT 'general',
  active TINYINT(1) NOT NULL DEFAULT 1,
  manual TINYINT(1) NOT NULL DEFAULT 0,
  source_payload_json LONGTEXT NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_account_panel_username (panel_id, source_username),
  KEY idx_accounts_username (source_username),
  KEY idx_accounts_uuid (source_uuid),
  KEY idx_accounts_panel_active (panel_id, active),
  CONSTRAINT fk_accounts_panel FOREIGN KEY (panel_id) REFERENCES panels(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_settings_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  panel_id BIGINT UNSIGNED NOT NULL,
  source_filename VARCHAR(255) NOT NULL,
  source_hash CHAR(64) NOT NULL,
  status ENUM('completed','blocked','failed') NOT NULL DEFAULT 'completed',
  total_rows INT UNSIGNED NOT NULL DEFAULT 0,
  added_rows INT UNSIGNED NOT NULL DEFAULT 0,
  changed_rows INT UNSIGNED NOT NULL DEFAULT 0,
  missing_rows INT UNSIGNED NOT NULL DEFAULT 0,
  reactivated_rows INT UNSIGNED NOT NULL DEFAULT 0,
  stats_json LONGTEXT NULL,
  imported_by BIGINT UNSIGNED NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  error_message TEXT NULL,
  KEY idx_asi_panel_date (panel_id, imported_at),
  KEY idx_asi_hash (source_hash),
  CONSTRAINT fk_asi_panel FOREIGN KEY (panel_id) REFERENCES panels(id) ON DELETE RESTRICT,
  CONSTRAINT fk_asi_user FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  report_date DATE NOT NULL,
  source_filename VARCHAR(255) NULL,
  source_hash CHAR(64) NULL,
  duplicate_fingerprint CHAR(64) NULL,
  status ENUM('draft','final') NOT NULL DEFAULT 'draft',
  total_received DECIMAL(24,6) NOT NULL DEFAULT 0,
  total_paid DECIMAL(24,6) NOT NULL DEFAULT 0,
  total_commission DECIMAL(24,6) NOT NULL DEFAULT 0,
  site_net DECIMAL(24,6) NOT NULL DEFAULT 0,
  site_status ENUM('win','loss','settled') NOT NULL DEFAULT 'settled',
  created_by BIGINT UNSIGNED NULL,
  finalized_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_reports_company_date (company_id, report_date),
  KEY idx_reports_duplicate (duplicate_fingerprint),
  CONSTRAINT fk_reports_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_reports_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_report_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  daily_report_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NULL,
  panel_id BIGINT UNSIGNED NULL,
  source_username VARCHAR(190) NOT NULL,
  original_name_snapshot VARCHAR(380) NULL,
  display_name_snapshot VARCHAR(380) NULL,
  member_win DECIMAL(24,6) NOT NULL DEFAULT 0,
  rate_type_snapshot ENUM('general','special') NOT NULL DEFAULT 'general',
  rate_value_snapshot DECIMAL(24,6) NOT NULL DEFAULT 0,
  loss_percent_snapshot DECIMAL(12,6) NOT NULL DEFAULT 0,
  commission DECIMAL(24,6) NOT NULL DEFAULT 0,
  received DECIMAL(24,6) NOT NULL DEFAULT 0,
  paid DECIMAL(24,6) NOT NULL DEFAULT 0,
  row_origin ENUM('import','manual','temporary') NOT NULL DEFAULT 'import',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_report_rows_report (daily_report_id),
  KEY idx_report_rows_account (account_id),
  KEY idx_report_rows_panel (panel_id),
  CONSTRAINT fk_report_rows_report FOREIGN KEY (daily_report_id) REFERENCES daily_reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_report_rows_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_report_rows_panel FOREIGN KEY (panel_id) REFERENCES panels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  daily_report_id BIGINT UNSIGNED NOT NULL,
  revision_no INT UNSIGNED NOT NULL,
  before_json LONGTEXT NULL,
  after_json LONGTEXT NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_report_revision (daily_report_id, revision_no),
  CONSTRAINT fk_revision_report FOREIGN KEY (daily_report_id) REFERENCES daily_reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_revision_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_meta(meta_key, meta_value) VALUES
('app_version','0.2.0'),
('schema_version','002_phase2')
ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
