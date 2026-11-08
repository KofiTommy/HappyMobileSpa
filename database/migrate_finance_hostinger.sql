-- Finance upgrade for an existing Happy Mobile Spa MariaDB database.
-- Back up first. Run in phpMyAdmin with the spa database selected.
-- Safe to rerun: existing columns, tables, bookings, staff, and finance records are preserved.

ALTER TABLE staff ADD COLUMN IF NOT EXISTS monthly_salary DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER biography;

CREATE TABLE IF NOT EXISTS salary_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED NOT NULL,
  salary_month CHAR(7) NOT NULL,
  basic_salary DECIMAL(10,2) NOT NULL,
  bonus DECIMAL(10,2) NOT NULL DEFAULT 0,
  deduction DECIMAL(10,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  paid_at DATE NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT '',
  reference VARCHAR(120) NOT NULL DEFAULT '',
  notes TEXT NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  cancelled_at DATETIME NULL,
  UNIQUE KEY unique_staff_salary_month (staff_id,salary_month),
  INDEX idx_salary_month_status (salary_month,status),
  CONSTRAINT fk_salary_staff FOREIGN KEY(staff_id) REFERENCES staff(id),
  CONSTRAINT fk_salary_creator FOREIGN KEY(created_by) REFERENCES admin_users(id),
  CONSTRAINT fk_salary_updater FOREIGN KEY(updated_by) REFERENCES admin_users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category VARCHAR(40) NOT NULL,
  vendor VARCHAR(160) NOT NULL DEFAULT '',
  description VARCHAR(500) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  paid_at DATE NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT '',
  reference VARCHAR(120) NOT NULL DEFAULT '',
  notes TEXT NOT NULL,
  receipt_path VARCHAR(255) NULL,
  receipt_name VARCHAR(190) NULL,
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  cancelled_at DATETIME NULL,
  INDEX idx_expense_date_status (expense_date,status),
  INDEX idx_expense_category (category),
  CONSTRAINT fk_expense_creator FOREIGN KEY(created_by) REFERENCES admin_users(id),
  CONSTRAINT fk_expense_updater FOREIGN KEY(updated_by) REFERENCES admin_users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS monthly_budgets (
  salary_month CHAR(7) PRIMARY KEY,
  expected_income DECIMAL(12,2) NOT NULL DEFAULT 0,
  salary_budget DECIMAL(12,2) NOT NULL DEFAULT 0,
  expense_budgets JSON NOT NULL,
  notes TEXT NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_budget_creator FOREIGN KEY(created_by) REFERENCES admin_users(id),
  CONSTRAINT fk_budget_updater FOREIGN KEY(updated_by) REFERENCES admin_users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS finance_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(20) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(30) NOT NULL,
  details JSON NOT NULL,
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_finance_entity (entity_type,entity_id),
  INDEX idx_finance_created (created_at),
  CONSTRAINT fk_finance_admin FOREIGN KEY(admin_id) REFERENCES admin_users(id)
) ENGINE=InnoDB;
