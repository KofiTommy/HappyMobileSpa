-- Existing-installation security upgrade for MySQL or MariaDB/phpMyAdmin.
-- Select the LIVE spa database and export a backup before importing this file.
-- Safe to rerun: no users, passwords, bookings, or images are deleted/replaced.
-- Do not import schema.sql or a local database dump over the live database.

SET @hms_security_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='session_version'),
  'SELECT 1', 'ALTER TABLE admin_users ADD COLUMN session_version INT NOT NULL DEFAULT 1'
);
PREPARE hms_security_statement FROM @hms_security_sql;
EXECUTE hms_security_statement;
DEALLOCATE PREPARE hms_security_statement;

SET @hms_security_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='contact_name'),
  'SELECT 1', 'ALTER TABLE bookings ADD COLUMN contact_name VARCHAR(160) NULL'
);
PREPARE hms_security_statement FROM @hms_security_sql;
EXECUTE hms_security_statement;
DEALLOCATE PREPARE hms_security_statement;

SET @hms_security_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='contact_phone'),
  'SELECT 1', 'ALTER TABLE bookings ADD COLUMN contact_phone VARCHAR(30) NULL'
);
PREPARE hms_security_statement FROM @hms_security_sql;
EXECUTE hms_security_statement;
DEALLOCATE PREPARE hms_security_statement;

SET @hms_security_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='contact_email'),
  'SELECT 1', 'ALTER TABLE bookings ADD COLUMN contact_email VARCHAR(190) NULL'
);
PREPARE hms_security_statement FROM @hms_security_sql;
EXECUTE hms_security_statement;
DEALLOCATE PREPARE hms_security_statement;

UPDATE bookings SET contact_name=(SELECT name FROM customers WHERE customers.id=bookings.customer_id) WHERE contact_name IS NULL;
UPDATE bookings SET contact_phone=(SELECT phone FROM customers WHERE customers.id=bookings.customer_id) WHERE contact_phone IS NULL;
UPDATE bookings SET contact_email=COALESCE((SELECT email FROM customers WHERE customers.id=bookings.customer_id),'') WHERE contact_email IS NULL;

CREATE TABLE IF NOT EXISTS uploaded_photos (
  path VARCHAR(255) PRIMARY KEY,
  contents MEDIUMBLOB NOT NULL
) ENGINE=InnoDB;
