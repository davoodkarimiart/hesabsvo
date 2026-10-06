INSERT INTO settings(setting_key,setting_value)
SELECT 'theme_site_win', COALESCE((SELECT setting_value FROM settings WHERE setting_key='theme_pay' LIMIT 1),'#92cdd9')
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='theme_site_win');

INSERT INTO settings(setting_key,setting_value)
SELECT 'theme_site_loss', COALESCE((SELECT setting_value FROM settings WHERE setting_key='theme_recv' LIMIT 1),'#ff5d60')
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='theme_site_loss');

INSERT INTO app_meta(meta_key,meta_value) VALUES
('app_version','0.4.8'),('schema_version','010_v048_customer_reporting_ui')
ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
