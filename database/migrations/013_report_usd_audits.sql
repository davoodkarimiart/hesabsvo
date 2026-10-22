CREATE TABLE IF NOT EXISTS daily_report_usd_audits (
 daily_report_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 source_grand_total DECIMAL(24,6) NULL,
 source_raw_total DECIMAL(24,6) NULL,
 preview_total DECIMAL(24,6) NOT NULL,
 discrepancy DECIMAL(24,6) NOT NULL,
 reason TEXT NOT NULL,
 confirmed_by BIGINT UNSIGNED NULL,
 confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_confirmed_at (confirmed_at),
 CONSTRAINT fk_usd_audit_report FOREIGN KEY (daily_report_id) REFERENCES daily_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
