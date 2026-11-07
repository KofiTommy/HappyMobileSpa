<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') { throw new RuntimeException('Run from the command line.'); }
Database::connection()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS sms_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  slot_start DATETIME NOT NULL,
  due_at DATETIME NOT NULL,
  state VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  claimed_at DATETIME NULL,
  next_attempt_at DATETIME NULL,
  submitted_at DATETIME NULL,
  provider_id VARCHAR(160) NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_booking_sms(booking_id,kind,slot_start),
  INDEX idx_sms_due(state,due_at),
  CONSTRAINT fk_sms_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;
SQL);
echo "SMS notification queue ready.\n";
