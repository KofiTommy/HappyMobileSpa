<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') { throw new RuntimeException('Run from the command line.'); }
Database::connection()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS paystack_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL UNIQUE,
  reference VARCHAR(80) NOT NULL UNIQUE,
  amount_subunit BIGINT UNSIGNED NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'GHS',
  email VARCHAR(190) NOT NULL,
  mode VARCHAR(10) NOT NULL,
  state VARCHAR(30) NOT NULL,
  authorization_url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  CONSTRAINT fk_paystack_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;
SQL);
echo "Paystack payment storage ready.\n";
