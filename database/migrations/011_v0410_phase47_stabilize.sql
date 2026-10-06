INSERT INTO app_meta(meta_key,meta_value) VALUES ('app_version','0.4.10'),('schema_version','011_v0410_phase47_stabilize') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);
