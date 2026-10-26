-- Imali Smart: upgrade an EXISTING database (MariaDB / XAMPP, MySQL 8.0.29+).
-- Safe to run more than once. New databases should import database/schema.sql instead.
-- The app also applies the column changes by itself the first time they are needed.

-- 1) Chart style: bar, line or pie (older databases only allowed graph or pie).
ALTER TABLE user_settings MODIFY COLUMN preferred_chart_type VARCHAR(10) NOT NULL DEFAULT 'bar';
UPDATE user_settings SET preferred_chart_type = 'bar' WHERE preferred_chart_type = 'graph';
ALTER TABLE user_settings MODIFY COLUMN preferred_chart_type ENUM('bar','line','pie') NOT NULL DEFAULT 'bar';

-- 2) Shareable statements: the QR code opens statement.php?t=<share_token>.
ALTER TABLE report_exports
  ADD COLUMN IF NOT EXISTS share_token VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS snapshot_json LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS share_expires_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS share_revoked_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS share_views INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS share_last_viewed_at DATETIME NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_report_share_token ON report_exports (share_token);

-- 3) Budgets: personal or other scope, used by the budget form.
ALTER TABLE budgets ADD COLUMN IF NOT EXISTS scope VARCHAR(20) NOT NULL DEFAULT 'personal';

-- 4) Proof of payment uploads.
CREATE TABLE IF NOT EXISTS payment_proofs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, transaction_id BIGINT UNSIGNED NULL,
  storage_key VARCHAR(255) NOT NULL, original_name VARCHAR(255) NULL, mime_type VARCHAR(100) NULL, file_size INT UNSIGNED NULL,
  verification_status VARCHAR(30) NOT NULL DEFAULT 'unverified', extracted_json LONGTEXT NULL, scanned_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL, KEY idx_payment_proofs_owner (user_id, deleted_at),
  CONSTRAINT fk_payment_proofs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_proofs_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payment_proofs ADD COLUMN IF NOT EXISTS extracted_json LONGTEXT NULL, ADD COLUMN IF NOT EXISTS scanned_at DATETIME NULL;
