-- 0.6.29: safe recovery and invalidation of existing sessions after credential change.
ALTER TABLE users ADD COLUMN security_version INT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS user_recovery_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_recovery_hash (code_hash),
  KEY idx_user_recovery (user_id),
  CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_login_attempt_ip_time ON login_attempts (ip_hash, attempted_at);
CREATE INDEX idx_login_attempt_time ON login_attempts (attempted_at);
