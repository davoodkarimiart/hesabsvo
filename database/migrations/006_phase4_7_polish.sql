CREATE TABLE IF NOT EXISTS customer_autogroup_exclusions (
  account_id BIGINT UNSIGNED PRIMARY KEY,
  excluded_by BIGINT UNSIGNED NULL,
  excluded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_customer_autogroup_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_autogroup_user FOREIGN KEY (excluded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_meta(meta_key, meta_value) VALUES
('app_version','0.4.1'),
('schema_version','006_phase4_7_polish')
ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
