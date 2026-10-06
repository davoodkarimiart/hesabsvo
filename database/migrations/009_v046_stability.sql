INSERT INTO app_meta(meta_key,meta_value) VALUES ('app_version','0.4.6'),('schema_version','009_v046_stability') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
