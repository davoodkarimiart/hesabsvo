ALTER TABLE daily_reports
  ADD COLUMN panel_id BIGINT UNSIGNED NULL AFTER company_id,
  MODIFY COLUMN status ENUM('draft','final','deleted') NOT NULL DEFAULT 'draft',
  ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER finalized_at,
  ADD COLUMN deleted_at DATETIME NULL AFTER deleted_by,
  ADD KEY idx_reports_panel_date (panel_id, report_date),
  ADD KEY idx_reports_status_date (status, report_date),
  ADD CONSTRAINT fk_reports_panel FOREIGN KEY (panel_id) REFERENCES panels(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_reports_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

UPDATE daily_reports r
JOIN (
  SELECT daily_report_id, MIN(panel_id) AS panel_id
  FROM daily_report_rows
  WHERE panel_id IS NOT NULL
  GROUP BY daily_report_id
  HAVING COUNT(DISTINCT panel_id)=1
) x ON x.daily_report_id=r.id
SET r.panel_id=x.panel_id
WHERE r.panel_id IS NULL;

INSERT INTO app_meta(meta_key, meta_value) VALUES
('app_version','0.3.1'),
('schema_version','004_phase3_guardrails')
ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
