-- Select the live spa database in phpMyAdmin and export a backup first.
-- Safe to rerun. This does not change profile photos or existing staff details.
SET @hms_video_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='staff' AND COLUMN_NAME='video_url'),
  'SELECT 1', 'ALTER TABLE staff ADD COLUMN video_url VARCHAR(2048) NULL'
);
PREPARE hms_video_statement FROM @hms_video_sql;
EXECUTE hms_video_statement;
DEALLOCATE PREPARE hms_video_statement;
