CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(380) NOT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_customers_name (display_name),
  CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_accounts (
  customer_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  linked_by BIGINT UNSIGNED NULL,
  linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id, account_id),
  UNIQUE KEY uq_customer_accounts_account (account_id),
  KEY idx_customer_accounts_customer (customer_id),
  CONSTRAINT fk_customer_accounts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_accounts_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_accounts_user FOREIGN KEY (linked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
('money_scale','trim3'),
('digit_mode','fa'),
('font_global','vazirmatn'),
('font_heading','vazirmatn'),
('font_form','vazirmatn'),
('font_table','vazirmatn'),
('font_print','vazirmatn'),
('theme_bg','#f4f7fb'),
('theme_card','#ffffff'),
('theme_text','#172033'),
('theme_primary','#3559e0'),
('theme_recv','#ff5d60'),
('theme_pay','#92cdd9'),
('theme_comm','#78fa70'),
('theme_opacity','100')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO app_meta(meta_key, meta_value) VALUES
('app_version','0.4.0'),
('schema_version','005_phase4_7')
ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
