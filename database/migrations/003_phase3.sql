INSERT INTO settings(setting_key,setting_value) VALUES
('default_rate','100'),
('special_rate','200'),
('default_loss_percent','10')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO app_meta(meta_key, meta_value) VALUES
('app_version','0.3.0'),
('schema_version','003_phase3')
ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
