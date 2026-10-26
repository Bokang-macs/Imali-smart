# Imali Smart – Demo Users Documentation

Companion to `database/seed_demo_users.sql`. All data is **synthetic**, covers **12–26 October 2026**, and all three accounts were registered on **12 October 2026**.

## 1. How to use

1. Import `database/schema.sql` into an empty database (skip if already done). The seed file adds data only.
2. phpMyAdmin → select the Imali Smart database → **Import** → choose `seed_demo_users.sql` → **Go**.
3. The final result table shows each user's totals. A row reading `SKIPPED` means that email already belongs to a non-demo account; that account was not changed and nothing was seeded for it.
4. Re-importing is safe: nothing is duplicated (rows are matched by email and by transaction reference code, never by auto-increment id).

## 2. Logging in

The app uses local PHP authentication (`password_hash()` on register/reset, `password_verify()` in `login.php`). The seed stores bcrypt hashes (`$2y$10$…`), so the three accounts sign in through the normal login page with the email and password you supplied. The SQL file contains only the hashes, never the plaintext passwords, and no authentication code was changed. Remember-me and password-reset tokens are deliberately not seeded.

| Account | Email (login) | Password | Phone |
|---|---|---|---|
| Rotondwa Given Machete | givenrmacs@gmail.com | Bokang-macs@04 | 0731780264 (generated) |
| Khutso Mboweni | mbowenikhutso0426@gmail.com | MboweniK | 07769024558 |
| Tshepiso Masingi | tshepisoletelo2@gmail.com | MasingiT | 0726816561 |

Passwords are case-sensitive. This document contains plaintext passwords, so keep it private.

## 3. Financial profiles

### Rotondwa Given Machete – student, irregular income
- Mbombela, Mpumalanga. English, ZAR. Opening balance R450.00.
- Student profile: University of Mpumalanga, BSc Information Technology (year 2), funded by family support (R1,500/month), living at home. Student number and emergency contact are fictional.
- Income: family support (R1,500), two freelance payments (R1,200, R850).
- Spending: taxi fares, groceries, data/airtime, study materials, household contribution, small treats.
- Budgets (Oct): Groceries R750, Transport R450, Airtime and data R200, Education R400.
- Savings: Emergency fund (target R2,000, saved R350), Laptop fund (target R9,000, saved R100).
- Debt: clothing store account, R1,200 → R1,020 after one R180 instalment.
- Settings: dark theme, line charts, low-data mode on, AI assistance on, 90-day document retention.

### Khutso Mboweni – salaried employee
- Emalahleni, Mpumalanga. Opening balance R4,200.00.
- Income: monthly salary R17,850 on 15 Oct.
- Spending: groceries, fuel, rent (R3,800), prepaid electricity, data/airtime, pharmacy, family contribution, personal care, bank fee.
- Budgets (Oct): Groceries R3,000, Transport R1,800, Airtime and data R450, Electricity R900, Rent R3,800, Debt repayments R3,600.
- Savings: Emergency fund (target R30,000, saved R1,750), Vehicle service and tyres (target R6,000, saved R500).
- Debts: vehicle finance R96,400 → R93,250 (R3,150 instalment, 12.75%); retail account R4,200 → R3,750 (R450 instalment, 21%).
- Settings: light theme, bar charts, browser notifications on, AI assistance off.

### Tshepiso Masingi – self-employed home caterer
- Polokwane, Limpopo. Opening balance R1,850.00.
- Income: four catering and sales receipts (R6,500 total) with variable amounts.
- Spending: business stock, rent (R1,900), school costs, groceries, electricity, transport, family support, pharmacy, data, bank fee.
- Budgets (Oct): Business stock R3,200, Groceries R900, Transport R500, Airtime and data R250, Electricity R400, Rent R1,900, School costs R600.
- Savings: Second oven and equipment (target R12,000, saved R700), Emergency cushion (target R8,000, saved R350).
- Debt: none (deliberately).
- Settings: default theme, pie charts, low-data mode on, AI assistance on, 180-day retention.
- Her month ends with a net outflow (−R827.30) covered by her opening balance, which is intended to show tight cash flow.

## 4. Financial reconciliation

Figures are computed from the stored rows. The app's balance rule is: **opening balance + income − expenses** (transfers are ignored).

| User | Registered | Opening | Income | Expenses | Net | Closing | Tx | Budgets | Goals | Saved | Debt owed |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Rotondwa Given Machete | 2026-10-12 | 450.00 | 3,550.00 | 1,990.95 | 1,559.05 | 2,009.05 | 18 | 4 | 2 | 450.00 | 1,020.00 |
| Khutso Mboweni | 2026-10-12 | 4,200.00 | 17,850.00 | 13,118.40 | 4,731.60 | 8,931.60 | 18 | 6 | 2 | 2,250.00 | 97,000.00 |
| Tshepiso Masingi | 2026-10-12 | 1,850.00 | 6,500.00 | 7,327.30 | −827.30 | 1,022.70 | 18 | 7 | 2 | 1,050.00 | 0.00 |

Checks applied when the file was generated:
- The running balance never goes negative on any day for any user.
- Each goal's `current_amount` equals the sum of its contributions, and no goal is complete.
- Each debt's `current_balance` equals its total minus its payments, and no debt is marked paid.
- Debt-repayment expense transactions equal the `debt_payments` rows for each user (counted once in cash flow).
- All dates fall within 12–26 Oct 2026; nothing is in the future or before registration.
- Every category used matches its transaction type, and no budget is exceeded.

## 5. Table coverage

| Table | Status |
|---|---|
| users, user_settings, personal_profiles, notification_preferences | Seeded for all three |
| student_profiles | Rotondwa only (the only student) |
| expense_categories | Standard categories reused; user-specific ones added only where needed (Freelance income, Entertainment, Personal care, Bank fees, Business sales, Business stock) |
| budgets, transactions | Seeded (October 2026, scope `personal`) |
| savings_goals, savings_contributions | Seeded (2 goals each) |
| debts, debt_payments | Rotondwa 1, Khutso 2, Tshepiso none |
| audit_events | Registration, budget, goal, debt and transaction events; no secrets |
| scam_reports | Two clearly labelled fictional examples; no police case numbers |
| notification_delivery_history | Not seeded: it records real provider sends, and none occurred |
| report_exports, payment_proofs | Not seeded: they need real files or share links that would not exist |
| user_hidden_categories, remember_me_tokens, password_reset_tokens, dynamic_translations | Not seeded: nothing in the data calls for them |
| lessons | Untouched |

## 6. Assumptions

- Seeded transactions are identified by `DEMO` in `reference_code` (for example `TXN-20261015-DEMO-K04`). Descriptions stay clean.
- Your app does not record savings contributions or debt payments as transactions, so savings contributions do not reduce the dashboard balance. Real cash would be slightly lower.
- A debt's total is its balance when added on 12 Oct. No interest is added within the two weeks.
- Rotondwa's phone number is randomly generated; the other numbers are as supplied.
- Each user's seeded rows are matched by email plus the seed registration timestamp, which prevents a real account being given demo data.

## 7. Validation and rollback

The bottom of `seed_demo_users.sql` contains 11 commented, read-only validation queries (V1–V11) and a commented rollback block. The queries labelled as problem queries should return zero rows.

The rollback removes only the three demo accounts, their audit events and the two demo scam reports. Everything else cascades from the user rows. Real users are never touched.

## 8. Test status

The SQL was run twice against a SQLite copy of `schema.sql`. The second run added nothing, the totals matched an independent calculation, and the email-conflict case behaved as described. The password hashes were verified with PHP's `password_verify`. It has not been run on a real MySQL or MariaDB server, so run the validation queries after your first import.
