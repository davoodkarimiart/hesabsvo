-- v0.4.5: preserve original Report_WL file for download from archive
ALTER TABLE daily_reports ADD COLUMN source_storage_path VARCHAR(500) NULL AFTER source_filename;
