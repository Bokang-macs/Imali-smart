-- ==============================================================================
-- IMALI SMART - DEMO DATA SEED (three synthetic users, 12-26 October 2026)
-- File: database/seed_demo_users.sql
--
-- WHAT IT DOES
--   Creates three demo accounts and their data: users, settings, profiles, categories, budgets,
--   transactions, savings goals + contributions, debts + payments, notification preferences,
--   audit events and two clearly-labelled synthetic scam-awareness examples.
--   Every account is created on 2026-10-12; all activity is dated 2026-10-12 to 2026-10-26.
--
-- BEFORE YOU RUN IT
--   1. Import database/schema.sql into the database first (this file adds data only).
--   2. phpMyAdmin: select the Imali Smart database > Import > choose this file > Go.
--
-- SAFETY
--   * Idempotent: re-importing never duplicates anything (rows are matched by e-mail and by
--     the reference code TXN-YYYYMMDD-DEMO-xNN, never by auto-increment id).
--   * Never drops, truncates, or updates your data; foreign-key checks stay ON.
--   * Creates NO tables of any kind (no TEMPORARY tables, no helper tables): it only needs the
--     INSERT and SELECT privileges, so it also works on shared hosting.
--   * If one of the three e-mail addresses already belongs to a NON-demo account, that
--     account is left untouched and none of its data is seeded (see the final result table).
--   * Passwords are stored as PHP password_hash() bcrypt hashes ($2y$10$...), verified by login.php
--     with password_verify(). No plaintext password appears in this file.
--   * Transactions carry the marker DEMO in reference_code, so they are easy to identify.
--     All amounts are synthetic; nothing here was imported from a real bank.
--
-- ROLLBACK: see the commented block at the bottom of this file.
-- ==============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+02:00';



START TRANSACTION;

-- ------------------------------------------------------------------------------
-- 1. Standard (shared) categories: only added if the schema import did not create them
-- ------------------------------------------------------------------------------
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

-- ------------------------------------------------------------------------------
-- 2. Users (skipped when the e-mail already exists; created_at / updated_at = 12 Oct 2026)
-- ------------------------------------------------------------------------------
INSERT INTO users (auth_provider, full_name, email, phone_number, province, city, preferred_contact_method, password_hash, preferred_language, currency_code, opening_balance, created_at, updated_at)
SELECT 'local', 'Rotondwa Given Machete', 'givenrmacs@gmail.com', '0731780264', 'Mpumalanga', 'Mbombela', 'email', '$2y$10$wehWGY9llMAgvjV5xuK6.OW1FFashOWEJz27a9Y2VX.pUPsMI7ElC', 'en', 'ZAR', 450.00, '2026-10-12 08:14:32', '2026-10-12 08:14:32' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'givenrmacs@gmail.com');
INSERT INTO users (auth_provider, full_name, email, phone_number, province, city, preferred_contact_method, password_hash, preferred_language, currency_code, opening_balance, created_at, updated_at)
SELECT 'local', 'Khutso Mboweni', 'mbowenikhutso0426@gmail.com', '07769024558', 'Mpumalanga', 'Emalahleni', 'phone', '$2y$10$DkSmC.z4misr1eg73P2TAumxuUrp1qrUQpCtzA8JEezcvSXkfnkF.', 'en', 'ZAR', 4200.00, '2026-10-12 09:41:07', '2026-10-12 09:41:07' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'mbowenikhutso0426@gmail.com');
INSERT INTO users (auth_provider, full_name, email, phone_number, province, city, preferred_contact_method, password_hash, preferred_language, currency_code, opening_balance, created_at, updated_at)
SELECT 'local', 'Tshepiso Masingi', 'tshepisoletelo2@gmail.com', '0726816561', 'Limpopo', 'Polokwane', 'email', '$2y$10$stXeUzQ6LVoA8A0rR2RAzuZs2a6W26e2dy19p3o9YFpazVWFOCvhC', 'en', 'ZAR', 1850.00, '2026-10-12 11:03:55', '2026-10-12 11:03:55' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'tshepisoletelo2@gmail.com');

-- Only accounts created by this script (same e-mail AND same seed timestamp) receive data below.

-- ------------------------------------------------------------------------------
-- 3. Settings, profiles, notification preferences
-- ------------------------------------------------------------------------------
INSERT INTO user_settings (user_id, low_data_mode, browser_notifications_enabled, privacy_ai_enabled, theme_preference, preferred_chart_type, statement_theme, document_retention_days, updated_at)
SELECT o.uid, 1, 0, 1, 'dark', 'line', 'imali-editorial', 90, '2026-10-12 08:14:32' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'givenrmacs@gmail.com' AND NOT EXISTS (SELECT 1 FROM user_settings x WHERE x.user_id = o.uid);
INSERT INTO personal_profiles (user_id, income_pattern, household_size, primary_goal, employment_status, financial_confidence, updated_at)
SELECT o.uid, 'irregular', 5, 'Build a small emergency fund and save for a laptop', 'Student or learner', 'starting', '2026-10-12 08:14:32' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'givenrmacs@gmail.com' AND NOT EXISTS (SELECT 1 FROM personal_profiles x WHERE x.user_id = o.uid);
INSERT INTO notification_preferences (user_id, browser_enabled, email_enabled, updated_at)
SELECT o.uid, 0, 1, '2026-10-12 08:14:32' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'givenrmacs@gmail.com' AND NOT EXISTS (SELECT 1 FROM notification_preferences x WHERE x.user_id = o.uid);
INSERT INTO student_profiles (user_id, institution_name, student_number, study_level, field_of_study, funding_status, expected_graduation_year, monthly_allowance, residence_type, emergency_contact_name, emergency_contact_phone, updated_at)
SELECT o.uid, 'University of Mpumalanga', '202604170', 'Undergraduate - Year 2', 'Information Technology', 'family_support', 2028, 1500.00, 'home', 'Sample Contact (fictional)', '0715550100', '2026-10-12 08:14:32' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'givenrmacs@gmail.com' AND NOT EXISTS (SELECT 1 FROM student_profiles x WHERE x.user_id = o.uid);
INSERT INTO user_settings (user_id, low_data_mode, browser_notifications_enabled, privacy_ai_enabled, theme_preference, preferred_chart_type, statement_theme, document_retention_days, updated_at)
SELECT o.uid, 0, 1, 0, 'light', 'bar', 'imali-editorial', NULL, '2026-10-12 09:41:07' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'mbowenikhutso0426@gmail.com' AND NOT EXISTS (SELECT 1 FROM user_settings x WHERE x.user_id = o.uid);
INSERT INTO personal_profiles (user_id, income_pattern, household_size, primary_goal, employment_status, financial_confidence, updated_at)
SELECT o.uid, 'steady', 2, 'Grow my emergency fund while paying down vehicle finance', 'Employed', 'building', '2026-10-12 09:41:07' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'mbowenikhutso0426@gmail.com' AND NOT EXISTS (SELECT 1 FROM personal_profiles x WHERE x.user_id = o.uid);
INSERT INTO notification_preferences (user_id, browser_enabled, email_enabled, updated_at)
SELECT o.uid, 1, 1, '2026-10-12 09:41:07' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'mbowenikhutso0426@gmail.com' AND NOT EXISTS (SELECT 1 FROM notification_preferences x WHERE x.user_id = o.uid);
INSERT INTO user_settings (user_id, low_data_mode, browser_notifications_enabled, privacy_ai_enabled, theme_preference, preferred_chart_type, statement_theme, document_retention_days, updated_at)
SELECT o.uid, 1, 0, 1, 'default', 'pie', 'imali-editorial', 180, '2026-10-12 11:03:55' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'tshepisoletelo2@gmail.com' AND NOT EXISTS (SELECT 1 FROM user_settings x WHERE x.user_id = o.uid);
INSERT INTO personal_profiles (user_id, income_pattern, household_size, primary_goal, employment_status, financial_confidence, updated_at)
SELECT o.uid, 'mixed', 4, 'Save for a second oven to grow my home catering business', 'Self-employed', 'confident', '2026-10-12 11:03:55' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'tshepisoletelo2@gmail.com' AND NOT EXISTS (SELECT 1 FROM personal_profiles x WHERE x.user_id = o.uid);
INSERT INTO notification_preferences (user_id, browser_enabled, email_enabled, updated_at)
SELECT o.uid, 0, 1, '2026-10-12 11:03:55' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'tshepisoletelo2@gmail.com' AND NOT EXISTS (SELECT 1 FROM notification_preferences x WHERE x.user_id = o.uid);

-- ------------------------------------------------------------------------------
-- 4. User-specific categories (standard categories are reused, not copied)
-- ------------------------------------------------------------------------------
INSERT INTO expense_categories (user_id, name, category_type, is_system, created_at)
SELECT o.uid, c.name, c.ctype, 0, u.created_at FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Freelance income' AS name, 'income' AS ctype
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Entertainment', 'expense'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Personal care', 'expense'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Bank fees', 'expense'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Entertainment', 'expense'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Personal care', 'expense'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Bank fees', 'expense'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Business sales', 'income'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Business stock', 'expense'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Bank fees', 'expense') c JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.email = c.email JOIN users u ON u.id = o.uid
WHERE NOT EXISTS (SELECT 1 FROM expense_categories x WHERE x.user_id = o.uid AND x.name = c.name);

-- ------------------------------------------------------------------------------
-- 5. Budgets (October 2026, scope 'personal' like the app's budget form)
-- ------------------------------------------------------------------------------
INSERT INTO budgets (user_id, category_id, name, amount, period_start, period_end, scope, created_at, updated_at)
SELECT o.uid, c.id, b.name, b.amount, '2026-10-01', '2026-10-31', 'personal', DATE_ADD(u.created_at, INTERVAL 25 MINUTE), DATE_ADD(u.created_at, INTERVAL 25 MINUTE)
FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Groceries - October 2026' AS name, 'Food and groceries' AS cat, 750.00 AS amount
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Transport - October 2026', 'Transport', 450.00
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Airtime and data - October 2026', 'Airtime and data', 200.00
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Education - October 2026', 'Education', 400.00
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Groceries - October 2026', 'Food and groceries', 3000.00
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Transport and fuel - October 2026', 'Transport', 1800.00
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Airtime and data - October 2026', 'Airtime and data', 450.00
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Electricity - October 2026', 'Utilities', 900.00
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Rent - October 2026', 'Housing', 3800.00
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Debt repayments - October 2026', 'Debt repayment', 3600.00
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Business stock - October 2026', 'Business stock', 3200.00
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Household groceries - October 2026', 'Food and groceries', 900.00
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Transport - October 2026', 'Transport', 500.00
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Airtime and data - October 2026', 'Airtime and data', 250.00
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Electricity - October 2026', 'Utilities', 400.00
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Rent - October 2026', 'Housing', 1900.00
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'School costs - October 2026', 'Education', 600.00) b JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.email = b.email JOIN users u ON u.id = o.uid
LEFT JOIN expense_categories c ON c.name = b.cat AND (c.user_id = o.uid OR (c.user_id IS NULL AND c.is_system = 1))
WHERE NOT EXISTS (SELECT 1 FROM budgets x WHERE x.user_id = o.uid AND x.name = b.name AND x.period_start = '2026-10-01');

-- ------------------------------------------------------------------------------
-- 6. Transactions (income/expense only; savings and debt payments follow the app's own model, see notes)
-- ------------------------------------------------------------------------------
INSERT INTO transactions (user_id, category_id, budget_id, transaction_type, amount, transaction_date, description, payment_method, reference_code, created_at, updated_at)
SELECT o.uid, c.id, b.id, t.ttype, t.amount, t.tdate, t.descr, t.method, t.ref, t.ts, t.ts
FROM (SELECT 'givenrmacs@gmail.com' AS email, 'TXN-20261012-DEMO-R01' AS ref, '2026-10-12' AS tdate, 'income' AS ttype, 1500.00 AS amount, 'Family support received' AS descr, 'EFT' AS method, 'Grants and support' AS cat, NULL AS budget, '2026-10-12 15:17:00' AS ts
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261012-DEMO-R02', '2026-10-12', 'expense', 99.00, 'Mobile data bundle (3GB)', 'Mobile payment', 'Airtime and data', 'Airtime and data - October 2026', '2026-10-12 16:34:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261013-DEMO-R03', '2026-10-13', 'expense', 70.00, 'Taxi fares to campus and back', 'Cash', 'Transport', 'Transport - October 2026', '2026-10-13 08:51:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261014-DEMO-R04', '2026-10-14', 'expense', 286.40, 'Grocery shopping', 'Debit Card', 'Food and groceries', 'Groceries - October 2026', '2026-10-14 11:08:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261015-DEMO-R05', '2026-10-15', 'expense', 145.00, 'Printing and study materials', 'Debit Card', 'Education', 'Education - October 2026', '2026-10-15 14:25:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261016-DEMO-R06', '2026-10-16', 'expense', 70.00, 'Taxi fares to campus and back', 'Cash', 'Transport', 'Transport - October 2026', '2026-10-16 08:42:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261017-DEMO-R07', '2026-10-17', 'income', 1200.00, 'Freelance website development payment', 'EFT', 'Freelance income', NULL, '2026-10-17 11:59:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261018-DEMO-R08', '2026-10-18', 'expense', 150.00, 'Contribution to household groceries', 'Cash', 'Giving and family support', NULL, '2026-10-18 14:16:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261019-DEMO-R09', '2026-10-19', 'expense', 180.00, 'Store account instalment', 'EFT', 'Debt repayment', NULL, '2026-10-19 08:33:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261020-DEMO-R10', '2026-10-20', 'expense', 198.75, 'Grocery shopping', 'Debit Card', 'Food and groceries', 'Groceries - October 2026', '2026-10-20 11:50:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261021-DEMO-R11', '2026-10-21', 'expense', 120.00, 'Taxi fares for the week', 'Cash', 'Transport', 'Transport - October 2026', '2026-10-21 14:07:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261022-DEMO-R12', '2026-10-22', 'expense', 85.00, 'Takeaway meal with friends', 'Debit Card', 'Entertainment', NULL, '2026-10-22 08:24:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261023-DEMO-R13', '2026-10-23', 'expense', 49.00, 'Airtime top-up', 'Mobile payment', 'Airtime and data', 'Airtime and data - October 2026', '2026-10-23 11:41:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261024-DEMO-R14', '2026-10-24', 'income', 850.00, 'Freelance logo and landing page payment', 'Instant Pay', 'Freelance income', NULL, '2026-10-24 14:58:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261024-DEMO-R15', '2026-10-24', 'expense', 220.00, 'Second-hand textbook', 'Cash', 'Education', 'Education - October 2026', '2026-10-24 08:15:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261025-DEMO-R16', '2026-10-25', 'expense', 241.30, 'Grocery shopping', 'Debit Card', 'Food and groceries', 'Groceries - October 2026', '2026-10-25 11:32:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261025-DEMO-R17', '2026-10-25', 'expense', 11.50, 'Bank service fee', 'EFT', 'Bank fees', NULL, '2026-10-25 14:49:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'TXN-20261026-DEMO-R18', '2026-10-26', 'expense', 65.00, 'Haircut and toiletries', 'Cash', 'Personal care', NULL, '2026-10-26 08:06:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261012-DEMO-K01', '2026-10-12', 'expense', 842.60, 'Monthly grocery shopping', 'Debit Card', 'Food and groceries', 'Groceries - October 2026', '2026-10-12 15:17:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261013-DEMO-K02', '2026-10-13', 'expense', 650.00, 'Fuel purchase', 'Debit Card', 'Transport', 'Transport and fuel - October 2026', '2026-10-13 14:34:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261014-DEMO-K03', '2026-10-14', 'expense', 299.00, 'Mobile data bundle (20GB)', 'Mobile payment', 'Airtime and data', 'Airtime and data - October 2026', '2026-10-14 08:51:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261015-DEMO-K04', '2026-10-15', 'income', 17850.00, 'Monthly salary payment', 'EFT', 'Salary and wages', NULL, '2026-10-15 11:08:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261016-DEMO-K05', '2026-10-16', 'expense', 3800.00, 'Monthly rent', 'EFT', 'Housing', 'Rent - October 2026', '2026-10-16 14:25:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261016-DEMO-K06', '2026-10-16', 'expense', 500.00, 'Prepaid electricity purchase', 'EFT', 'Utilities', 'Electricity - October 2026', '2026-10-16 08:42:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261017-DEMO-K07', '2026-10-17', 'expense', 189.90, 'Pharmacy purchase', 'Debit Card', 'Health', NULL, '2026-10-17 11:59:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261018-DEMO-K08', '2026-10-18', 'expense', 450.00, 'Retail account instalment', 'EFT', 'Debt repayment', 'Debt repayments - October 2026', '2026-10-18 14:16:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261019-DEMO-K09', '2026-10-19', 'expense', 388.45, 'Grocery shopping', 'Debit Card', 'Food and groceries', 'Groceries - October 2026', '2026-10-19 08:33:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261020-DEMO-K10', '2026-10-20', 'expense', 3150.00, 'Vehicle finance monthly instalment', 'EFT', 'Debt repayment', 'Debt repayments - October 2026', '2026-10-20 11:50:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261020-DEMO-K11', '2026-10-20', 'expense', 69.00, 'Bank service fee', 'EFT', 'Bank fees', NULL, '2026-10-20 14:07:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261021-DEMO-K12', '2026-10-21', 'expense', 600.00, 'Fuel purchase', 'Debit Card', 'Transport', 'Transport and fuel - October 2026', '2026-10-21 08:24:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261022-DEMO-K13', '2026-10-22', 'expense', 215.00, 'Restaurant purchase', 'Debit Card', 'Entertainment', NULL, '2026-10-22 11:41:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261022-DEMO-K14', '2026-10-22', 'expense', 264.15, 'Grocery shopping', 'Debit Card', 'Food and groceries', 'Groceries - October 2026', '2026-10-22 14:58:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261023-DEMO-K15', '2026-10-23', 'expense', 800.00, 'Family contribution', 'EFT', 'Giving and family support', NULL, '2026-10-23 08:15:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261024-DEMO-K16', '2026-10-24', 'expense', 100.00, 'Airtime top-up', 'Mobile payment', 'Airtime and data', 'Airtime and data - October 2026', '2026-10-24 11:32:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261025-DEMO-K17', '2026-10-25', 'expense', 280.00, 'Barber and toiletries', 'Cash', 'Personal care', NULL, '2026-10-25 14:49:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'TXN-20261026-DEMO-K18', '2026-10-26', 'expense', 520.30, 'Grocery shopping', 'Debit Card', 'Food and groceries', 'Groceries - October 2026', '2026-10-26 08:06:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261012-DEMO-T01', '2026-10-12', 'expense', 640.00, 'Ingredients and packaging', 'Debit Card', 'Business stock', 'Business stock - October 2026', '2026-10-12 15:17:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261013-DEMO-T02', '2026-10-13', 'expense', 120.00, 'Taxi fare to wholesale market', 'Cash', 'Transport', 'Transport - October 2026', '2026-10-13 14:34:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261014-DEMO-T03', '2026-10-14', 'income', 1480.00, 'Catering order payment - church function', 'EFT', 'Business sales', NULL, '2026-10-14 08:51:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261015-DEMO-T04', '2026-10-15', 'expense', 300.00, 'Prepaid electricity purchase', 'EFT', 'Utilities', 'Electricity - October 2026', '2026-10-15 11:08:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261016-DEMO-T05', '2026-10-16', 'expense', 460.00, 'School uniform and stationery', 'Debit Card', 'Education', 'School costs - October 2026', '2026-10-16 14:25:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261017-DEMO-T06', '2026-10-17', 'income', 920.00, 'Weekly cash sales deposit', 'Cash deposit', 'Business sales', NULL, '2026-10-17 08:42:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261018-DEMO-T07', '2026-10-18', 'expense', 910.50, 'Bulk flour, cooking oil and chicken', 'Debit Card', 'Business stock', 'Business stock - October 2026', '2026-10-18 11:59:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261019-DEMO-T08', '2026-10-19', 'expense', 435.20, 'Household grocery shopping', 'Debit Card', 'Food and groceries', 'Household groceries - October 2026', '2026-10-19 14:16:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261020-DEMO-T09', '2026-10-20', 'income', 1760.00, 'Birthday party catering order', 'EFT', 'Business sales', NULL, '2026-10-20 08:33:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261020-DEMO-T10', '2026-10-20', 'expense', 1900.00, 'Monthly rent (backyard room)', 'EFT', 'Housing', 'Rent - October 2026', '2026-10-20 11:50:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261021-DEMO-T11', '2026-10-21', 'expense', 149.00, 'Mobile data bundle for customer orders', 'Mobile payment', 'Airtime and data', 'Airtime and data - October 2026', '2026-10-21 14:07:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261022-DEMO-T12', '2026-10-22', 'expense', 500.00, 'Family support sent home', 'EFT', 'Giving and family support', NULL, '2026-10-22 08:24:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261023-DEMO-T13', '2026-10-23', 'expense', 215.00, 'Pharmacy purchase', 'Debit Card', 'Health', NULL, '2026-10-23 11:41:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261024-DEMO-T14', '2026-10-24', 'income', 2340.00, 'Weekend catering orders and takings', 'Instant Pay', 'Business sales', NULL, '2026-10-24 14:58:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261024-DEMO-T15', '2026-10-24', 'expense', 1120.00, 'Ingredients for weekend orders', 'Debit Card', 'Business stock', 'Business stock - October 2026', '2026-10-24 08:15:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261025-DEMO-T16', '2026-10-25', 'expense', 220.00, 'Taxi fares and deliveries', 'Cash', 'Transport', 'Transport - October 2026', '2026-10-25 11:32:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261026-DEMO-T17', '2026-10-26', 'expense', 45.00, 'Business account monthly fee', 'EFT', 'Bank fees', NULL, '2026-10-26 14:49:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'TXN-20261026-DEMO-T18', '2026-10-26', 'expense', 312.60, 'Household grocery shopping', 'Debit Card', 'Food and groceries', 'Household groceries - October 2026', '2026-10-26 08:06:00') t JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.email = t.email
LEFT JOIN expense_categories c ON c.name = t.cat AND (c.user_id = o.uid OR (c.user_id IS NULL AND c.is_system = 1))
LEFT JOIN budgets b ON b.user_id = o.uid AND b.name = t.budget AND b.period_start = '2026-10-01'
WHERE NOT EXISTS (SELECT 1 FROM transactions x WHERE x.user_id = o.uid AND x.reference_code = t.ref);

-- ------------------------------------------------------------------------------
-- 7. Savings goals and contributions
--    current_amount = sum of the contributions below (set once, when the goal is first created)
-- ------------------------------------------------------------------------------
INSERT INTO savings_goals (user_id, name, target_amount, current_amount, target_date, category, status, created_at, updated_at)
SELECT o.uid, g.name, g.target, (SELECT COALESCE(SUM(c.amount), 0) FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Emergency fund' AS goal, 200.00 AS amount, '2026-10-17' AS cdate, 'Saved from freelance payment' AS note, '2026-10-17 10:00:00' AS ts
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Emergency fund', 150.00, '2026-10-24', 'Saved from freelance payment', '2026-10-24 11:11:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Laptop fund', 100.00, '2026-10-24', 'First laptop deposit', '2026-10-24 12:22:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Emergency fund (3 months)', 1500.00, '2026-10-16', 'Monthly savings contribution', '2026-10-16 13:33:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Emergency fund (3 months)', 250.00, '2026-10-26', 'Month-end top-up', '2026-10-26 14:44:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Vehicle service and tyres', 500.00, '2026-10-16', 'Monthly savings contribution', '2026-10-16 15:55:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Second oven and equipment', 300.00, '2026-10-17', 'Weekly savings from takings', '2026-10-17 10:06:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Second oven and equipment', 400.00, '2026-10-24', 'Weekly savings from takings', '2026-10-24 11:17:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Emergency cushion', 150.00, '2026-10-20', 'Saved from catering income', '2026-10-20 12:28:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Emergency cushion', 200.00, '2026-10-24', 'Saved from catering income', '2026-10-24 13:39:00') c WHERE c.email = g.email AND c.goal = g.name), g.tdate, g.category, 'active',
  DATE_ADD(u.created_at, INTERVAL 40 MINUTE), DATE_ADD(u.created_at, INTERVAL 40 MINUTE)
FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Emergency fund' AS name, 2000.00 AS target, '2027-03-31' AS tdate, 'Emergency Cushion' AS category
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Laptop fund', 9000.00, '2027-06-30', 'General Savings'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Emergency fund (3 months)', 30000.00, '2027-06-30', 'Emergency Cushion'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Vehicle service and tyres', 6000.00, '2027-03-31', 'General Savings'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Second oven and equipment', 12000.00, '2027-04-30', 'General Savings'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Emergency cushion', 8000.00, '2027-03-31', 'Emergency Cushion') g JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.email = g.email JOIN users u ON u.id = o.uid
WHERE NOT EXISTS (SELECT 1 FROM savings_goals x WHERE x.user_id = o.uid AND x.name = g.name);
INSERT INTO savings_contributions (goal_id, user_id, amount, contribution_date, note, created_at)
SELECT sg.id, o.uid, c.amount, c.cdate, c.note, c.ts
FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Emergency fund' AS goal, 200.00 AS amount, '2026-10-17' AS cdate, 'Saved from freelance payment' AS note, '2026-10-17 10:00:00' AS ts
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Emergency fund', 150.00, '2026-10-24', 'Saved from freelance payment', '2026-10-24 11:11:00'
   UNION ALL SELECT 'givenrmacs@gmail.com', 'Laptop fund', 100.00, '2026-10-24', 'First laptop deposit', '2026-10-24 12:22:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Emergency fund (3 months)', 1500.00, '2026-10-16', 'Monthly savings contribution', '2026-10-16 13:33:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Emergency fund (3 months)', 250.00, '2026-10-26', 'Month-end top-up', '2026-10-26 14:44:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Vehicle service and tyres', 500.00, '2026-10-16', 'Monthly savings contribution', '2026-10-16 15:55:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Second oven and equipment', 300.00, '2026-10-17', 'Weekly savings from takings', '2026-10-17 10:06:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Second oven and equipment', 400.00, '2026-10-24', 'Weekly savings from takings', '2026-10-24 11:17:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Emergency cushion', 150.00, '2026-10-20', 'Saved from catering income', '2026-10-20 12:28:00'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Emergency cushion', 200.00, '2026-10-24', 'Saved from catering income', '2026-10-24 13:39:00') c JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.email = c.email JOIN savings_goals sg ON sg.user_id = o.uid AND sg.name = c.goal
WHERE NOT EXISTS (SELECT 1 FROM savings_contributions x WHERE x.goal_id = sg.id AND x.contribution_date = c.cdate AND x.amount = c.amount);

-- ------------------------------------------------------------------------------
-- 8. Debts and payments (Tshepiso has no debt on purpose)
--    total_amount = balance when the debt was added on registration day;
--    current_balance = total_amount - sum of the payments below.
-- ------------------------------------------------------------------------------
INSERT INTO debts (user_id, creditor_name, total_amount, current_balance, interest_rate, minimum_payment, due_date, status, created_at, updated_at)
SELECT o.uid, d.creditor, d.total, d.total - (SELECT COALESCE(SUM(p.amount), 0) FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Clothing store account' AS creditor, 180.00 AS amount, '2026-10-19' AS pdate, 'Monthly instalment' AS note, '2026-10-19 12:05:00' AS ts
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Retail account', 450.00, '2026-10-18', 'Monthly instalment', '2026-10-18 13:14:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Vehicle finance - bank', 3150.00, '2026-10-20', 'Monthly instalment', '2026-10-20 14:23:00') p WHERE p.email = d.email AND p.creditor = d.creditor), d.rate, d.minpay, d.due, 'active',
  DATE_ADD(u.created_at, INTERVAL 30 MINUTE), DATE_ADD(u.created_at, INTERVAL 30 MINUTE)
FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Clothing store account' AS creditor, 1200.00 AS total, 21.0000 AS rate, 180.00 AS minpay, '2026-11-19' AS due
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Vehicle finance - bank', 96400.00, 12.7500, 3150.00, '2026-11-20'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Retail account', 4200.00, 21.0000, 450.00, '2026-11-18') d JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.email = d.email JOIN users u ON u.id = o.uid
WHERE NOT EXISTS (SELECT 1 FROM debts x WHERE x.user_id = o.uid AND x.creditor_name = d.creditor);
INSERT INTO debt_payments (debt_id, user_id, amount, payment_date, note, created_at)
SELECT db.id, o.uid, p.amount, p.pdate, p.note, p.ts
FROM (SELECT 'givenrmacs@gmail.com' AS email, 'Clothing store account' AS creditor, 180.00 AS amount, '2026-10-19' AS pdate, 'Monthly instalment' AS note, '2026-10-19 12:05:00' AS ts
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Retail account', 450.00, '2026-10-18', 'Monthly instalment', '2026-10-18 13:14:00'
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Vehicle finance - bank', 3150.00, '2026-10-20', 'Monthly instalment', '2026-10-20 14:23:00') p JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.email = p.email JOIN debts db ON db.user_id = o.uid AND db.creditor_name = p.creditor
WHERE NOT EXISTS (SELECT 1 FROM debt_payments x WHERE x.debt_id = db.id AND x.payment_date = p.pdate AND x.amount = p.amount);

-- ------------------------------------------------------------------------------
-- 9. Synthetic scam-awareness examples (fictional senders, no police case numbers)
-- ------------------------------------------------------------------------------
INSERT INTO scam_reports (reference, user_id, scam_type, channel, incident_date, suspect_name, suspect_contact, amount_lost, loss_method, description, reported_to_police, police_case_number, reporter_email, is_anonymous, status, created_ts, created_at, updated_at)
SELECT 'SR-261020-DEMO01', o.uid, 'job_offer', 'whatsapp', '2026-10-20', 'Fictional Recruiter (training example)', 'demo-recruiter@example.invalid', NULL, NULL, '[SYNTHETIC DEMO RECORD - fictional training example] A WhatsApp message offered an online task job paying R500 per hour but asked for a R300 registration fee first. No money was sent.', 0, NULL, o.email, 0, 'received', 1792484100, '2026-10-20 10:15:00', '2026-10-20 10:15:00' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'givenrmacs@gmail.com' AND NOT EXISTS (SELECT 1 FROM scam_reports x WHERE x.reference = 'SR-261020-DEMO01');
INSERT INTO scam_reports (reference, user_id, scam_type, channel, incident_date, suspect_name, suspect_contact, amount_lost, loss_method, description, reported_to_police, police_case_number, reporter_email, is_anonymous, status, created_ts, created_at, updated_at)
SELECT 'SR-261023-DEMO02', o.uid, 'bank_impersonation', 'sms', '2026-10-23', 'Fictional Bank Alert (training example)', 'demo-sender@example.invalid', NULL, NULL, '[SYNTHETIC DEMO RECORD - fictional training example] An SMS claimed the bank account was locked and asked for an OTP through a link. The link was not opened and no money was sent.', 0, NULL, o.email, 0, 'received', 1792766400, '2026-10-23 16:40:00', '2026-10-23 16:40:00' FROM (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o WHERE o.email = 'tshepisoletelo2@gmail.com' AND NOT EXISTS (SELECT 1 FROM scam_reports x WHERE x.reference = 'SR-261023-DEMO02');

-- ------------------------------------------------------------------------------
-- 10. Audit events (built from the seeded rows; no passwords, hashes or tokens)
-- ------------------------------------------------------------------------------
INSERT INTO audit_events (user_id, action, event_type, result, entity_type, entity_id, details_json, occurred_at, created_at)
SELECT u.id, 'auth.register', 'auth.register', 'success', 'user', u.id, '{"seed":"demo","synthetic":true}', u.created_at, u.created_at
FROM users u JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.uid = u.id
WHERE NOT EXISTS (SELECT 1 FROM audit_events x WHERE x.user_id = u.id AND x.action = 'auth.register');
INSERT INTO audit_events (user_id, action, event_type, result, entity_type, entity_id, details_json, occurred_at, created_at)
SELECT b.user_id, 'budget.created', 'budget.created', 'success', 'budget', b.id, CONCAT('{"seed":"demo","synthetic":true,"name":"', b.name, '","amount":', b.amount, '}'), b.created_at, b.created_at
FROM budgets b JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.uid = b.user_id
WHERE NOT EXISTS (SELECT 1 FROM audit_events x WHERE x.user_id = b.user_id AND x.action = 'budget.created' AND x.entity_id = b.id);
INSERT INTO audit_events (user_id, action, event_type, result, entity_type, entity_id, details_json, occurred_at, created_at)
SELECT g.user_id, 'savings_goal.created', 'savings_goal.created', 'success', 'savings_goal', g.id, CONCAT('{"seed":"demo","synthetic":true,"name":"', g.name, '","target_amount":', g.target_amount, '}'), g.created_at, g.created_at
FROM savings_goals g JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.uid = g.user_id
WHERE NOT EXISTS (SELECT 1 FROM audit_events x WHERE x.user_id = g.user_id AND x.action = 'savings_goal.created' AND x.entity_id = g.id);
INSERT INTO audit_events (user_id, action, event_type, result, entity_type, entity_id, details_json, occurred_at, created_at)
SELECT d.user_id, 'debt.created', 'debt.created', 'success', 'debt', d.id, CONCAT('{"seed":"demo","synthetic":true,"creditor":"', d.creditor_name, '"}'), d.created_at, d.created_at
FROM debts d JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.uid = d.user_id
WHERE NOT EXISTS (SELECT 1 FROM audit_events x WHERE x.user_id = d.user_id AND x.action = 'debt.created' AND x.entity_id = d.id);
INSERT INTO audit_events (user_id, action, event_type, result, entity_type, entity_id, details_json, occurred_at, created_at)
SELECT t.user_id, 'transaction.created', 'transaction.created', 'success', 'transaction', t.id, CONCAT('{"seed":"demo","synthetic":true,"type":"', t.transaction_type, '","amount":', t.amount, ',"transaction_date":"', t.transaction_date, '","reference_code":"', t.reference_code, '"}'), t.created_at, t.created_at
FROM transactions t JOIN (SELECT u.email AS email, u.id AS uid FROM users u JOIN (SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
   UNION ALL SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
   UNION ALL SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55') su ON su.email = u.email AND su.created_at = u.created_at AND u.deleted_at IS NULL) o ON o.uid = t.user_id
WHERE t.reference_code LIKE 'TXN-%-DEMO-%' AND NOT EXISTS (SELECT 1 FROM audit_events x WHERE x.user_id = t.user_id AND x.action = 'transaction.created' AND x.entity_id = t.id);

COMMIT;

-- ------------------------------------------------------------------------------
-- 11. RESULT TABLE (computed from the stored rows). A row says SKIPPED if that
--     e-mail already belonged to a non-demo account (nothing was changed for it).
-- ------------------------------------------------------------------------------
SELECT s.full_name AS user_name,
       CASE WHEN u.id IS NULL THEN 'SKIPPED - e-mail already used by a non-demo account' ELSE 'seeded' END AS seed_status,
       DATE(u.created_at) AS registered,
       u.opening_balance,
       (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.user_id = u.id AND t.deleted_at IS NULL AND t.transaction_type = 'income') AS total_income,
       (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.user_id = u.id AND t.deleted_at IS NULL AND t.transaction_type = 'expense') AS total_expenses,
       (SELECT COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount WHEN t.transaction_type = 'expense' THEN -t.amount ELSE 0 END),0) FROM transactions t WHERE t.user_id = u.id AND t.deleted_at IS NULL) AS net_cash_flow,
       u.opening_balance + (SELECT COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount WHEN t.transaction_type = 'expense' THEN -t.amount ELSE 0 END),0) FROM transactions t WHERE t.user_id = u.id AND t.deleted_at IS NULL) AS closing_balance,
       (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id AND t.deleted_at IS NULL) AS transactions,
       (SELECT COUNT(*) FROM budgets b WHERE b.user_id = u.id) AS budgets,
       (SELECT COUNT(*) FROM savings_goals g WHERE g.user_id = u.id) AS savings_goals,
       (SELECT COALESCE(SUM(g.current_amount),0) FROM savings_goals g WHERE g.user_id = u.id) AS total_saved,
       (SELECT COALESCE(SUM(d.current_balance),0) FROM debts d WHERE d.user_id = u.id AND d.status = 'active') AS outstanding_debt
FROM (
  SELECT 'givenrmacs@gmail.com' AS email, 'Rotondwa Given Machete' AS full_name, '2026-10-12 08:14:32' AS created_at
  UNION ALL
  SELECT 'mbowenikhutso0426@gmail.com', 'Khutso Mboweni', '2026-10-12 09:41:07'
  UNION ALL
  SELECT 'tshepisoletelo2@gmail.com', 'Tshepiso Masingi', '2026-10-12 11:03:55'
) s
LEFT JOIN users u ON u.email = s.email AND u.created_at = s.created_at AND u.deleted_at IS NULL
ORDER BY s.created_at;

-- ==============================================================================
-- OPTIONAL: VALIDATION QUERIES (copy one at a time into phpMyAdmin > SQL; read-only)
-- Every "problem" query should return ZERO rows unless it says otherwise.
-- ==============================================================================
-- V1  Exactly three demo users, exact names/e-mails, registered 2026-10-12:
--     SELECT id, full_name, email, phone_number, province, city, DATE(created_at) AS registered, opening_balance FROM users WHERE email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com') ORDER BY created_at;
-- V2  Passwords are bcrypt (PHP password_hash) - expect 3 rows, hash_length 60, prefix $2y$10$:
--     SELECT email, LEFT(password_hash,7) AS prefix, CHAR_LENGTH(password_hash) AS hash_length FROM users WHERE email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com');
-- V3  Transactions outside 2026-10-12..2026-10-26 (problem rows):
--     SELECT * FROM transactions WHERE reference_code LIKE 'TXN-%-DEMO-%' AND (transaction_date < '2026-10-12' OR transaction_date > '2026-10-26');
-- V4  Orphans: transactions whose category/budget belongs to someone else or is missing (problem rows):
--     SELECT t.id FROM transactions t LEFT JOIN expense_categories c ON c.id = t.category_id LEFT JOIN budgets b ON b.id = t.budget_id
--     WHERE t.reference_code LIKE 'TXN-%-DEMO-%' AND (t.category_id IS NULL OR c.id IS NULL OR (c.user_id IS NOT NULL AND c.user_id <> t.user_id) OR (t.budget_id IS NOT NULL AND (b.id IS NULL OR b.user_id <> t.user_id)));
-- V5  Duplicate seeded transactions (problem rows):
--     SELECT user_id, reference_code, COUNT(*) FROM transactions WHERE reference_code LIKE 'TXN-%-DEMO-%' GROUP BY user_id, reference_code HAVING COUNT(*) > 1;
-- V6  Savings goals whose current_amount differs from the sum of their contributions (problem rows):
--     SELECT g.id, g.name, g.current_amount, COALESCE(SUM(c.amount),0) AS contributions FROM savings_goals g LEFT JOIN savings_contributions c ON c.goal_id = g.id
--     WHERE g.user_id IN (SELECT id FROM users WHERE email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com')) GROUP BY g.id, g.name, g.current_amount HAVING g.current_amount <> COALESCE(SUM(c.amount),0);
-- V7  Debts whose balance differs from total minus payments, or paid-with-balance (problem rows):
--     SELECT d.id, d.creditor_name, d.total_amount, d.current_balance, COALESCE(SUM(p.amount),0) AS paid FROM debts d LEFT JOIN debt_payments p ON p.debt_id = d.id
--     WHERE d.user_id IN (SELECT id FROM users WHERE email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com')) GROUP BY d.id, d.creditor_name, d.total_amount, d.current_balance, d.status
--     HAVING d.current_balance <> d.total_amount - COALESCE(SUM(p.amount),0) OR (d.status = 'paid' AND d.current_balance > 0);
-- V8  Debt repayment expenses vs debt_payments per user (the two columns must match):
--     SELECT u.full_name,
--       (SELECT COALESCE(SUM(t.amount),0) FROM transactions t JOIN expense_categories c ON c.id = t.category_id WHERE t.user_id = u.id AND c.name = 'Debt repayment' AND t.deleted_at IS NULL) AS repayment_expenses,
--       (SELECT COALESCE(SUM(p.amount),0) FROM debt_payments p WHERE p.user_id = u.id) AS debt_payments
--     FROM users u WHERE u.email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com');
-- V9  Budget usage (spent is computed the way transactions.php computes it):
--     SELECT u.full_name, b.name, b.amount AS budget, COALESCE(SUM(t.amount),0) AS spent FROM budgets b JOIN users u ON u.id = b.user_id
--     LEFT JOIN transactions t ON t.budget_id = b.id AND t.user_id = b.user_id AND t.deleted_at IS NULL AND t.transaction_type = 'expense' AND t.transaction_date BETWEEN b.period_start AND b.period_end
--     WHERE u.email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com') GROUP BY u.id, u.full_name, b.id, b.name, b.amount ORDER BY u.id, b.name;
-- V10 Ownership check - child rows pointing at a different user than their parent (problem rows):
--     SELECT 'contribution' AS kind, c.id FROM savings_contributions c JOIN savings_goals g ON g.id = c.goal_id WHERE g.user_id <> c.user_id
--     UNION ALL SELECT 'debt_payment', p.id FROM debt_payments p JOIN debts d ON d.id = p.debt_id WHERE d.user_id <> p.user_id;
-- V11 Users and their row counts (isolation overview):
--     SELECT u.full_name, (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) AS tx, (SELECT COUNT(*) FROM budgets WHERE user_id = u.id) AS budgets,
--            (SELECT COUNT(*) FROM savings_goals WHERE user_id = u.id) AS goals, (SELECT COUNT(*) FROM debts WHERE user_id = u.id) AS debts FROM users u WHERE u.email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com');
--
-- ==============================================================================
-- ROLLBACK (removes ONLY these three demo accounts and everything attached to them).
-- Remove the leading "-- " from the statements below and run them together. Every table that
-- references users uses ON DELETE CASCADE except audit_events (SET NULL) and scam_reports (no FK),
-- which are deleted first. Real users are never touched because the e-mails are matched exactly.
-- ==============================================================================
-- START TRANSACTION;
-- DELETE FROM audit_events WHERE user_id IN (SELECT id FROM users WHERE email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com'));
-- DELETE FROM scam_reports WHERE reference IN ('SR-261020-DEMO01', 'SR-261023-DEMO02');
-- DELETE FROM users WHERE email IN ('givenrmacs@gmail.com', 'mbowenikhutso0426@gmail.com', 'tshepisoletelo2@gmail.com');
-- COMMIT;

