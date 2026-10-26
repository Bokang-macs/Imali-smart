-- ==============================================================================
-- Imali Smart - Personal Finance database schema (MySQL 5.7+ / MariaDB 10.3+, InnoDB, utf8mb4)
-- Import into an EXISTING empty database (phpMyAdmin > Import). It does not create or select a database.
-- Safe to re-run: every table uses CREATE TABLE IF NOT EXISTS and seed rows are inserted only once.
-- Covers every table the app code uses, including scam_reports (community scam reports) and
-- payment_proofs, plus chart style (bar/line/pie), shareable statements (QR code) and budgets.scope.
-- Existing databases: run database/migrations/2026-10-26-chart-style-and-statement-links.sql instead.
-- ==============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, firebase_uid VARCHAR(128) NULL UNIQUE,
  auth_provider VARCHAR(32) NOT NULL DEFAULT 'local', full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE, phone_number VARCHAR(40) NULL, alternative_email VARCHAR(190) NULL,
  date_of_birth DATE NULL, province VARCHAR(80) NULL, city VARCHAR(100) NULL,
  preferred_contact_method ENUM('email','phone','none') NOT NULL DEFAULT 'email', password_hash VARCHAR(255) NOT NULL,
  preferred_language VARCHAR(12) NOT NULL DEFAULT 'en', currency_code CHAR(3) NOT NULL DEFAULT 'ZAR',
  opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00, firebase_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL, KEY idx_users_language (preferred_language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_settings (
  user_id BIGINT UNSIGNED PRIMARY KEY, low_data_mode TINYINT(1) NOT NULL DEFAULT 0,
  browser_notifications_enabled TINYINT(1) NOT NULL DEFAULT 0, privacy_ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
  theme_preference VARCHAR(20) NOT NULL DEFAULT 'default', preferred_chart_type ENUM('bar','line','pie') NOT NULL DEFAULT 'bar', statement_theme VARCHAR(40) NOT NULL DEFAULT 'imali-editorial', document_retention_days INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY, income_pattern VARCHAR(40) NULL, household_size SMALLINT UNSIGNED NULL,
  primary_goal VARCHAR(160) NULL, employment_status VARCHAR(100) NULL, financial_confidence VARCHAR(30) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_personal_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY, institution_name VARCHAR(180) NULL, student_number VARCHAR(80) NULL,
  study_level VARCHAR(80) NULL, field_of_study VARCHAR(160) NULL,
  funding_status ENUM('self_funded','nsfas','bursary','scholarship','family_support','other','unknown') NOT NULL DEFAULT 'unknown',
  expected_graduation_year SMALLINT UNSIGNED NULL, monthly_allowance DECIMAL(14,2) NULL,
  residence_type ENUM('home','residence','renting','other','unknown') NOT NULL DEFAULT 'unknown',
  emergency_contact_name VARCHAR(160) NULL, emergency_contact_phone VARCHAR(40) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, name VARCHAR(120) NOT NULL,
  category_type ENUM('income','expense','both') NOT NULL DEFAULT 'expense', is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_category_owner_name (user_id, name),
  KEY idx_category_owner (user_id), CONSTRAINT fk_categories_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS user_hidden_categories (
  user_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (user_id, category_id),
  CONSTRAINT fk_hidden_category_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hidden_category_category FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NULL,
  name VARCHAR(140) NOT NULL, amount DECIMAL(14,2) NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL,
  scope VARCHAR(20) NOT NULL DEFAULT 'personal',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_budgets_owner_period (user_id, period_start, period_end),
  CONSTRAINT fk_budgets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_budgets_category FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NULL,
  budget_id BIGINT UNSIGNED NULL, transaction_type ENUM('income','expense','transfer') NOT NULL,
  amount DECIMAL(14,2) NOT NULL, transaction_date DATE NOT NULL, description VARCHAR(255) NOT NULL,
  payment_method VARCHAR(60) NULL, reference_code VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL, KEY idx_transactions_owner_date (user_id, transaction_date),
  KEY idx_transactions_owner_type (user_id, transaction_type), KEY idx_transactions_budget (budget_id),
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_transactions_budget FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE SET NULL,
  CONSTRAINT fk_transactions_category FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS savings_goals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(140) NOT NULL,
  target_amount DECIMAL(14,2) NOT NULL, current_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00, target_date DATE NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Emergency Fund', status ENUM('active','complete','paused') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_savings_owner_status (user_id, status), CONSTRAINT fk_savings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS savings_contributions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, goal_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(14,2) NOT NULL, contribution_date DATE NOT NULL, note VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_savings_contribution_goal FOREIGN KEY (goal_id) REFERENCES savings_goals(id) ON DELETE CASCADE,
  CONSTRAINT fk_savings_contribution_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS debts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, creditor_name VARCHAR(160) NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL, current_balance DECIMAL(14,2) NOT NULL, interest_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  minimum_payment DECIMAL(14,2) NOT NULL DEFAULT 0.00, due_date DATE NULL, status ENUM('active','paid','paused') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_debts_owner_status (user_id, status), CONSTRAINT fk_debts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS debt_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, debt_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(14,2) NOT NULL, payment_date DATE NOT NULL, note VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_debt_payments_owner_date (user_id, payment_date),
  CONSTRAINT fk_debt_payment_debt FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE,
  CONSTRAINT fk_debt_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
  user_id BIGINT UNSIGNED PRIMARY KEY, browser_enabled TINYINT(1) NOT NULL DEFAULT 0, email_enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS notification_delivery_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, recipient VARCHAR(255) NOT NULL,
  channel ENUM('email','browser') NOT NULL, status VARCHAR(40) NOT NULL, provider_id VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_notification_history_user (user_id, created_at),
  CONSTRAINT fk_notification_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS remember_me_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL, last_used_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_remember_user_expiry (user_id, expires_at), CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reset_user_expiry (user_id, expires_at), KEY idx_reset_expiry (expires_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL, event_type VARCHAR(120) NOT NULL, result VARCHAR(30) NOT NULL DEFAULT 'success',
  entity_type VARCHAR(100) NULL, entity_id BIGINT UNSIGNED NULL, details_json LONGTEXT NULL,
  occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_audit_user_time (user_id, occurred_at), KEY idx_audit_action_time (action, occurred_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dynamic_translations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, translation_hash VARCHAR(64) NOT NULL, source_lang VARCHAR(12) NOT NULL DEFAULT 'en',
  target_lang VARCHAR(12) NOT NULL, source_text TEXT NOT NULL, translated_text TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation (translation_hash, target_lang), KEY idx_translation_lookup (target_lang, translation_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_exports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
  report_type VARCHAR(40) NOT NULL, export_format ENUM('csv','excel','pdf','print') NOT NULL,
  period_start DATE NOT NULL, period_end DATE NOT NULL, file_name VARCHAR(255) NULL, language_code VARCHAR(12) NOT NULL DEFAULT 'en', template_version VARCHAR(40) NOT NULL DEFAULT 'imali-editorial-v1', qr_target TEXT NULL, page_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  -- Shareable statement: the QR code opens statement.php?t=<share_token>, which shows snapshot_json with no sign-in.
  share_token VARCHAR(64) NULL, snapshot_json LONGTEXT NULL, share_expires_at DATETIME NULL, share_revoked_at DATETIME NULL,
  share_views INT UNSIGNED NOT NULL DEFAULT 0, share_last_viewed_at DATETIME NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_report_share_token (share_token),
  KEY idx_report_exports_user_time (user_id, created_at),
  CONSTRAINT fk_report_exports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_proofs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, transaction_id BIGINT UNSIGNED NULL,
  storage_key VARCHAR(255) NOT NULL, original_name VARCHAR(255) NULL, mime_type VARCHAR(100) NULL, file_size INT UNSIGNED NULL,
  verification_status VARCHAR(30) NOT NULL DEFAULT 'unverified', extracted_json LONGTEXT NULL, scanned_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL,
  KEY idx_payment_proofs_owner (user_id, deleted_at),
  CONSTRAINT fk_payment_proofs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_proofs_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scam_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(24) NOT NULL, user_id BIGINT UNSIGNED NULL,
  scam_type VARCHAR(40) NOT NULL, channel VARCHAR(40) NOT NULL, incident_date DATE NULL,
  suspect_name VARCHAR(140) NULL, suspect_contact VARCHAR(255) NULL, amount_lost DECIMAL(14,2) NULL, loss_method VARCHAR(20) NULL,
  description TEXT NOT NULL, reported_to_police TINYINT(1) NOT NULL DEFAULT 0, police_case_number VARCHAR(60) NULL,
  reporter_email VARCHAR(190) NULL, is_anonymous TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'received',
  admin_note VARCHAR(500) NULL, source_hash CHAR(64) NULL, created_ts BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_scam_reports_reference (reference), KEY idx_scam_reports_user (user_id),
  KEY idx_scam_reports_status (status, created_ts), KEY idx_scam_reports_source (source_hash, created_ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Salary and wages', 'income', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Salary and wages');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Grants and support', 'income', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Grants and support');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Other income', 'income', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Other income');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Housing', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Housing');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Food and groceries', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Food and groceries');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Transport', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Transport');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Utilities', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Utilities');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Airtime and data', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Airtime and data');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Health', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Health');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Education', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Education');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Debt repayment', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Debt repayment');
INSERT INTO expense_categories (user_id, name, category_type, is_system) SELECT NULL, 'Giving and family support', 'expense', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE user_id IS NULL AND name = 'Giving and family support');
CREATE TABLE IF NOT EXISTS lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  summary TEXT,
  body LONGTEXT,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO lessons (slug,title,summary,body,sort_order) VALUES ('irregular-income','Budgeting with irregular income','A practical way to plan when money arrives at different times.','Start with essentials, separate committed payments from flexible spending, and budget from your lowest realistic month.',1),('emergency-fund','Build a small emergency fund','Small, consistent contributions can make surprises less disruptive.','Choose a first target that feels possible and keep contributions visible in your money picture.',2),('debt-plan','Make a debt payoff plan','Bring balances into one calm, practical plan.','Pay minimums on every active debt and direct any extra money to the priority balance.',3);
SET FOREIGN_KEY_CHECKS = 1;
