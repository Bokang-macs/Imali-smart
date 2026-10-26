# Imali Smart

**Imali Smart** (also styled *iMali Smart*) is a personal money confidence workspace built for South Africans. It helps people track income and expenses, set budgets, plan savings goals, pay off debts, understand their cash flow, produce reports and statements, and get plain-language help from an AI assistant. It also lets users upload proof of payment (a receipt, bank notification or payslip) and have Gemini AI read it and turn it into transactions.

The app is written mainly in PHP with a MySQL/MariaDB database. A small Node.js/Express server sits in front of it for local development and handles the server-side Gemini AI features.

---

## Table of contents

1. [Key features](#key-features)
2. [Who it is for](#who-it-is-for)
3. [Technology stack](#technology-stack)
4. [Architecture overview](#architecture-overview)
5. [Project structure](#project-structure)
6. [Requirements](#requirements)
7. [Installation and setup](#installation-and-setup)
8. [Configuration (.env)](#configuration-env)
9. [Database](#database)
10. [Demo data](#demo-data)
11. [Running the app](#running-the-app)
12. [Feature guide](#feature-guide)
13. [AI features (Gemini)](#ai-features-gemini)
14. [Payment proof scanning](#payment-proof-scanning)
15. [Reports, statements and sharing](#reports-statements-and-sharing)
16. [Authentication and security](#authentication-and-security)
17. [Languages and localisation](#languages-and-localisation)
18. [Currencies and reference rates](#currencies-and-reference-rates)
19. [Email and notifications](#email-and-notifications)
20. [SEO, PWA and offline support](#seo-pwa-and-offline-support)
21. [Deployment](#deployment)
22. [Troubleshooting](#troubleshooting)
23. [Security checklist before publishing](#security-checklist-before-publishing)
24. [Contributing](#contributing)
25. [Licence](#licence)

---

## Key features

- **Dashboard**: a summary of the user's money position for the current period.
- **Transactions**: record income and expenses, assign categories, and change dates.
- **Income plan**: a planning tool for irregular or variable income, so people with uneven earnings can budget from their lowest expected month.
- **Budgets**: monthly budgets per category, with personal and other scopes.
- **Savings goals**: set a target, make deposits and follow progress.
- **Debt payoff**: track debts, record payments and watch the balance fall.
- **Cash flow**: see money coming in and going out over time.
- **Analytics**: charts and insights (bar, line or pie) with a per-user preference for chart style.
- **Reports and statements**: export records as PDF, Excel or CSV, and generate a statement that can be shared through a QR code.
- **Ask Imali (AI assistant)**: a Gemini-powered assistant that answers money questions in plain language.
- **Financial quiz and lessons**: short curated questions and lessons on South African personal finance.
- **Proof of payment scanning**: upload a document, have it read by AI, and import the extracted transactions.
- **Scam awareness and reporting**: information on common financial scams, plus a form to report one.
- **Help centre, FAQ and contact page**: self-service support inside the app.
- **Themes and accessibility**: light and dark themes, a low-data mode, and an accessibility helper script.
- **Multilingual interface**: translations for several South African and regional languages.
- **Installable web app**: a web app manifest and service worker so key files can be cached on the device.

---

## Who it is for

Imali Smart is designed for a wide range of South African users, including:

- Students living on family support or irregular freelance income.
- Salaried employees who want to plan around pay dates, rent and debt repayments.
- Households managing shared or family-supported budgets.
- People who want a simple, low-data tool in their own language.

The demo accounts (see [Demo data](#demo-data)) show a student with irregular income and a salaried employee, which reflects these target users.

---

## Technology stack

| Layer | Technology |
|---|---|
| Main application | PHP (procedural, with helper modules in `includes/`) |
| Database | MySQL 8 / MariaDB (utf8mb4), accessed with PDO |
| Local development server | Node.js with Express 4, proxying to PHP's built-in server |
| Front-end build tooling | Vite 8, Tailwind CSS 4, React 19 (shell), TypeScript |
| AI | Google Gemini via `@google/genai` (Node) and the REST API (PHP) |
| Icons and motion | lucide-react, motion |
| Email | Resend and Brevo (transactional email, with fallback) |
| Charts | Custom `js/charts.js` and `css/charts.css` |
| Hosting target | InfinityFree (shared PHP/MySQL hosting), also runnable on XAMPP |

> **Note on the React layer:** `src/App.tsx` currently renders an empty placeholder. The live user interface is the PHP pages and the JavaScript in `js/`. The Vite and React setup is present so the project can be built and served through the standard Node tooling, and so the shell can grow over time.

---

## Architecture overview

```
            Browser
               │
               ▼
   ┌───────────────────────┐        ┌──────────────────────────┐
   │ Node / Express server │───────▶│ Gemini API (Google)      │
   │ (server.js, port 3000)│  AI    │ quiz and chat endpoints  │
   └──────────┬────────────┘        └──────────────────────────┘
              │ proxies all other requests
              ▼
   ┌───────────────────────┐
   │ PHP built-in server   │
   │ (port 8088) or Apache │
   │ on InfinityFree       │
   └──────────┬────────────┘
              │ PDO
              ▼
   ┌───────────────────────┐        ┌──────────────────────────┐
   │ MySQL / MariaDB       │        │ External services        │
   │ (schema.sql)          │        │ SARB rates, Frankfurter, │
   └───────────────────────┘        │ weather, holidays,       │
                                    │ QR code, email APIs      │
                                    └──────────────────────────┘
```

**Request flow in development:**

1. `npm run dev` starts Vite on port 3000. Vite's config also starts a PHP server on `127.0.0.1:8088`.
2. Requests to `/`, `*.php`, `/api/*`, `/actions/*` and `/uploads/*` are proxied from Vite to PHP.
3. `server.js` (run with `node server.js`) does the same job for production-style runs and adds the Gemini quiz and chat endpoints at `/api/gemini/quiz` and `/api/gemini/chat`.
4. PHP pages load shared code from `includes/`, configuration from `config/`, and connect to MySQL through `config/database.php`.

**Page pattern:** each top-level page (for example `dashboard.php` or `budgets.php`) loads `includes/config.php`, checks authentication with `require_auth()`, and renders the page using `includes/header.php`, `includes/navigation.php` and `includes/footer.php`. Form submissions are sent to `actions/`, and JSON endpoints live in `api/`.

---

## Project structure

```
imali-smart/
├── index.php                  Public landing page
├── index.html                 Static fallback page
├── dashboard.php              Main signed-in overview
├── transactions.php           Record and review income and expenses
├── irregular-income.php       Income plan for variable earnings
├── budgets.php                Monthly budgets by category
├── savings.php                Savings goals and deposits
├── debts.php                  Debt tracking and payoff
├── cashflow.php               Cash flow view
├── analytics.php              Charts and insights
├── reports.php                Reports and statement exports
├── statement.php              Public, shareable statement view (via token)
├── ai-assistant.php           "Ask Imali" AI assistant page
├── settings.php               Profile, preferences, theme, chart type
├── register.php               Account registration
├── login.php                  Sign-in (email/password, remember-me, Google)
├── logout.php                 Sign-out
├── forgot-password.php        Start a password reset
├── reset-password.php         Complete a password reset
├── google-signin.php          Google sign-in callback
├── help.php                   Help centre
├── faq.php                    Frequently asked questions
├── contact.php                Contact form
├── privacy-policy.php         Privacy policy
├── terms.php                  Terms of use
├── sitemap.php                Generates sitemap.xml
├── robots.php                 Generates robots.txt
├── server.js                  Express server with Gemini endpoints (dev and production)
├── vite.config.js             Vite configuration and PHP dev-server launcher
├── package.json               Node dependencies and scripts
├── tsconfig.json              TypeScript configuration
├── .env.example               Template for environment variables
├── .htaccess                  Apache rules (HTTPS redirect, blocks sensitive files)
├── manifest.webmanifest       Web app manifest
├── service-worker.js          Offline caching of key shell files
│
├── actions/                   Form handlers (POST)
│   ├── budgets/               save.php, delete.php
│   ├── debts/                 save.php, pay.php
│   ├── savings/               save.php, deposit.php
│   └── transactions/          add.php, delete.php
│
├── api/                       JSON endpoints
│   ├── ai/                    ask.php (assistant), status.php (AI availability)
│   ├── charts/                insight.php (chart insights)
│   ├── profile/               chart-type.php, theme.php
│   ├── proofs/                upload.php, import.php (payment proofs)
│   ├── reports/               download.php (export files)
│   ├── transactions/          set-date.php
│   ├── translate.php          Single-string translation
│   └── translate-batch.php    Batch translation
│
├── includes/                  Shared PHP modules
│   ├── auth.php               Login, logout, remember-me, current user
│   ├── csrf.php               CSRF token creation and checks
│   ├── database.php           Connection helper (db(), database_ready())
│   ├── functions.php          Core financial helpers
│   ├── gemini.php             Gemini REST client with model fallback
│   ├── i18n.php               Translation catalogue and language helpers
│   ├── page_translator.php    Translates page content on the fly
│   ├── proof_import.php       Turns extracted proof data into transactions
│   ├── reports_engine.php     Builds PDF, Excel and CSV exports
│   ├── statement_view.php     Renders statements
│   ├── chart_details.php      Chart data preparation
│   ├── categories_service.php Expense category management
│   ├── currencies.php         Supported currency list
│   ├── rates.php              Reference exchange rates
│   ├── external_api.php       Cached HTTP JSON requests
│   ├── cache.php              File-based cache
│   ├── scam_reports.php       Scam report handling
│   ├── seo.php                Page metadata, JSON-LD, sitemap data
│   ├── brevo.php              Brevo email sending
│   ├── resend.php             Resend email sending
│   ├── email-validation.php   Email address validation
│   ├── header.php / footer.php / navigation.php / sidebar.php
│   └── config.php / response.php
│
├── config/
│   ├── app.php                Reads .env, starts the session, shared helpers
│   ├── database.php           MySQL connection settings
│   └── .htaccess              Blocks direct access
│
├── database/
│   ├── schema.sql             Full schema for a new database
│   ├── migrations/            Upgrade scripts for existing databases
│   ├── seed_demo_users.sql    Synthetic demo accounts and data
│   └── .htaccess
│
├── storage/
│   ├── i18n/                  Translation JSON files (af, ar, nso, ss, xh, zu)
│   └── cache/                 Runtime cache (blocked from the web)
│
├── uploads/
│   └── payment-proofs/        Uploaded proof files (blocked from the web)
│
├── css/                       Stylesheets (variables, layout, components, charts, auth, etc.)
├── js/                        Front-end scripts (app, charts, dashboard, proof scanner, QR, etc.)
├── assets/logo/               Logos and statement emblem
├── public/favicon.svg
├── src/                       React/TypeScript shell (App.tsx, main.tsx, index.css)
└── docs/
    └── demo_users.md          Guide to the synthetic demo accounts
```

---

## Requirements

**To run locally with Node (recommended for development):**

- Node.js 20 or newer, with npm
- PHP 8.1 or newer, available on the command line (`php -v`). The PHP code uses typed parameters, nullable types and `str_contains`, so PHP 8.0 at minimum is required, and 8.1+ is recommended.
- PHP extensions: `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl`, `fileinfo`
- MySQL 8.0.29+ or MariaDB 10.5+ (the migration script refers to MySQL 8.0.29+ for `ADD COLUMN IF NOT EXISTS` behaviour)

**To run on XAMPP or a shared host:**

- Apache with `mod_rewrite` enabled
- PHP 8.1+ with the extensions above
- MariaDB or MySQL, and phpMyAdmin for importing SQL files

---

## Installation and setup

### 1. Get the code

Clone or extract the project into a working folder:

```bash
git clone <your-repository-url> imali-smart
cd imali-smart
```

Or unzip the archive:

```bash
unzip reports.zip -d imali-smart
cd imali-smart
```

### 2. Install Node dependencies

```bash
npm install
```

### 3. Create your environment file

```bash
cp .env.example .env
```

Open `.env` and fill in the values described in [Configuration](#configuration-env). Keep this file private. It must never be committed to version control or served publicly.

### 4. Create the database

Create an empty database in MySQL or MariaDB, for example `imali_smart`, then import the schema:

```bash
mysql -u <user> -p imali_smart < database/schema.sql
```

Or in phpMyAdmin: select the database, open **Import**, choose `database/schema.sql`, and click **Go**.

The schema uses `CREATE TABLE IF NOT EXISTS`, so it is safe to run more than once.

### 5. (Optional) Load demo data

See [Demo data](#demo-data).

### 6. Start the app

```bash
npm run dev
```

Open `http://localhost:3000` in your browser.

---

## Configuration (.env)

The file `.env.example` lists every setting. Copy it to `.env` and fill in the values you need. Settings you do not use can be left blank.

### Application

| Variable | Required | Description |
|---|---|---|
| `APP_URL` | Recommended | Public base URL, for example `https://example.com`. Used for canonical links, sitemaps and email links. If blank, the app works it out from the request. |
| `APP_TIMEZONE` | Yes | Time zone for dates. Defaults to `Africa/Johannesburg`. |
| `APP_DEBUG` | Yes | Set to `false` in production so errors are not shown to users. |
| `PREVIEW_HTTPS` | No | Set to `true` when previewing over HTTPS so session cookies are marked secure. |

### Database

| Variable | Required | Description |
|---|---|---|
| `DB_HOST` | Yes | Database host, for example `127.0.0.1` or your hosting provider's MySQL host. |
| `DB_PORT` | Yes | Usually `3306`. |
| `DB_NAME` | Yes | Database name. The code falls back to `imali_smart` if empty. |
| `DB_USER` | Yes | Database user. The code falls back to `root` if empty, which should not be used in production. |
| `DB_PASSWORD` | Yes | Database password. |
| `DATABASE_URL` | Optional | A full connection string, for example `mysql://user:pass@host:3306/dbname`. If set, it is used instead of the separate `DB_*` values. |

### Gemini AI

| Variable | Required | Description |
|---|---|---|
| `GEMINI_API_KEY` | For AI features | Your Google Gemini API key. Without it, AI features show as unavailable. |
| `GEMINI_MODEL` | No | Primary model. Defaults to `gemini-flash-lite-latest`. The `-latest` aliases are kept current by Google. |
| `GEMINI_FALLBACK_MODELS` | No | Comma-separated list of backup models, tried in order if the primary fails. |
| `GEMINI_API_BASE` | No | Overrides the Gemini REST base URL. Rarely needed. |

### Weather, email and validation

| Variable | Required | Description |
|---|---|---|
| `WEATHER_API_KEY` | Optional | Key for the weather feature. |
| `RESEND_API_KEY` | Optional | Resend API key for transactional email. |
| `RESEND_FROM_EMAIL` | Optional | Sender address used with Resend. |
| `BREVO_API_KEY` | Optional | Brevo API key (REST). |
| `BREVO_SENDER_EMAIL` | Optional | Sender address used with Brevo. |
| `BREVO_SENDER_NAME` | Optional | Sender name used with Brevo. |
| `BREVO_SMTP_KEY` | Optional | Brevo SMTP key. |
| `EMAIL_VALIDATION_URL` | Optional | Endpoint of an email validation service. |
| `EMAIL_VALIDATION_API_KEY` | Optional | API key for the email validation service. |

### Google sign-in

| Variable | Required | Description |
|---|---|---|
| `GOOGLE_CLIENT_ID` | Optional | OAuth client ID. Enables "Sign in with Google". |
| `GOOGLE_CLIENT_SECRET` | Optional | OAuth client secret. |

---

## Database

The database is MySQL or MariaDB with the `utf8mb4` character set. All access goes through PDO with prepared statements (`ATTR_EMULATE_PREPARES = false`) and exceptions enabled.

### Main tables

| Table | Purpose |
|---|---|
| `users` | Accounts: name, email, password hash, phone, verification and status fields |
| `user_settings` | Per-user preferences, including currency, theme, chart type and low-data mode |
| `personal_profiles` | Optional personal details |
| `student_profiles` | Optional student details (institution, programme, year, funding source) |
| `expense_categories` | Income and expense categories |
| `user_hidden_categories` | Categories a user has hidden |
| `budgets` | Monthly budgets per category, with a `scope` column |
| `transactions` | Income and expense records |
| `savings_goals` | Savings targets |
| `savings_contributions` | Deposits into savings goals |
| `debts` | Debts and their balances |
| `debt_payments` | Repayments against debts |
| `notification_preferences` | Which notifications a user has chosen |
| `notification_delivery_history` | Log of sent notifications |
| `remember_me_tokens` | Hashed tokens for "remember me" sessions |
| `password_reset_tokens` | Single-use password reset tokens |
| `audit_events` | Audit trail of important account actions |
| `dynamic_translations` | Stored translations for user-generated text |
| `report_exports` | Generated reports, share tokens, snapshots and view counts |
| `payment_proofs` | Metadata for uploaded proof files and their extracted data |
| `scam_reports` | Reports submitted through the scam reporting form |
| `lessons` | Financial education lessons |

### Migrations

For a database created before the latest changes, run:

```bash
mysql -u <user> -p imali_smart < database/migrations/2026-10-26-chart-style-and-statement-links.sql
```

This migration:

1. Updates the chart preference to `bar`, `line` or `pie` (older databases allowed `graph`).
2. Adds share columns to `report_exports` for shareable statements.
3. Adds a `scope` column to `budgets`.
4. Creates the `payment_proofs` table.

The app also applies several of these column changes itself the first time they are needed, but running the migration is the reliable route.

---

## Demo data

`database/seed_demo_users.sql` adds three synthetic accounts with about two weeks of realistic transactions (12 to 26 October 2026). All data is fictional.

- **Rotondwa Given Machete**: a student with irregular income (Mbombela, Mpumalanga).
- **Khutso Mboweni**: a salaried employee (Emalahleni, Mpumalanga).
- **Tshepiso Masingi**: a second demo profile.

The seed file stores bcrypt password hashes, not plain passwords. It can be imported safely more than once, because rows are matched by email and by transaction reference code rather than by auto-increment ID. If an email already belongs to a real account, that account is skipped and left unchanged.

See `docs/demo_users.md` for the full profiles and how to use them.

> **Important:** the demo documentation lists plaintext passwords for the demo accounts. Treat that file as private, remove it from any public copy of the project, and change the demo passwords before any shared or public deployment.

---

## Running the app

| Command | What it does |
|---|---|
| `npm run dev` | Starts Vite on port 3000, with hot reload and the PHP backend on port 8088 |
| `npm run build` | Builds the front-end shell into `dist/` |
| `npm run preview` | Serves the built output locally |
| `npm run lint` | Runs TypeScript type checking (`tsc --noEmit`) |
| `npm run clean` | Deletes `dist/` |
| `node server.js` | Runs the Express server on port 3000 with Gemini endpoints (set `PORT` or pass `--port` to change it) |

To run PHP alone for quick testing:

```bash
php -S 127.0.0.1:8088 -t .
```

Then open `http://127.0.0.1:8088`.

---

## Feature guide

### Accounts and onboarding

- **Register** (`register.php`): create an account with name, email, phone, password and preferred currency. Email is validated before the account is created.
- **Sign in** (`login.php`): email and password, with optional "remember me" and optional Google sign-in.
- **Forgot and reset password** (`forgot-password.php`, `reset-password.php`): time-limited, single-use reset links.
- **Sign out** (`logout.php`): ends the session and clears the remember-me token.

### Dashboard

The dashboard (`dashboard.php`) is the first screen after sign-in. It summarises the current month's income, spending, budget use, savings progress and debt, and links to the areas that need attention.

### Transactions

`transactions.php` lists income and expenses. Users can add an entry (`actions/transactions/add.php`), delete one (`actions/transactions/delete.php`), or change its date with `api/transactions/set-date.php`. Each transaction has a category, amount, date, direction (income or expense) and an optional note.

### Income plan (irregular income)

`irregular-income.php` is for people whose income changes from month to month, such as freelancers, students who rely on family support, or casual workers. It helps the user plan from a conservative base income rather than from their best month.

### Budgets

`budgets.php` lets users set a monthly limit for each category. Save and delete actions live in `actions/budgets/`. Budgets have a scope (personal or another scope), so a user can keep separate plans.

### Savings goals

`savings.php` lets users create a goal with a name, target amount and optional target date. Deposits are recorded with `actions/savings/deposit.php`, and progress is shown as a percentage of the target.

### Debt payoff

`debts.php` tracks each debt with its starting balance and current balance. Payments are recorded with `actions/debts/pay.php`, and the page shows how the balance is falling over time.

### Cash flow

`cashflow.php` shows money coming in and going out across the period, so users can see whether they are spending more than they earn.

### Analytics

`analytics.php` shows charts built with `js/charts.js`. Users choose bar, line or pie charts in settings. `api/charts/insight.php` can generate a short written insight about a chart.

### Settings

`settings.php` covers profile details, currency, theme (light or dark), preferred chart type, low-data mode, AI assistance on or off, and document retention (for example, how long uploaded proofs are kept). Theme and chart type are saved through `api/profile/theme.php` and `api/profile/chart-type.php`.

### Help, FAQ and contact

`help.php` and `faq.php` give self-service guidance. `js/faq-assistant.js` adds a search-style helper to the FAQ. `contact.php` sends a message to the team.

### Scam awareness

`js/scam-awareness.js` and `includes/scam_reports.php` provide information on common scams and a form for reporting one. Reports are stored in the `scam_reports` table.

### Lessons and quiz

The Node server provides `/api/gemini/quiz`, which returns a financial quiz for a chosen level (beginner, intermediate or advanced). It uses a curated question bank with South African financial content, and can ask Gemini for more questions when the bank runs low.

---

## AI features (Gemini)

Imali Smart uses Google Gemini for three things: the **Ask Imali** assistant, the **quiz**, and **payment proof reading**.

### How the Gemini client works

`includes/gemini.php` calls the Gemini REST API directly from PHP. It:

- Builds an ordered list of models: the primary model from `GEMINI_MODEL`, then any fallbacks, with the last working model tried first.
- Remembers models that fail, for a short time, so that dead model names are not retried on every question.
- Keeps within a total time budget, so a slow model cannot hold a page open indefinitely.

`server.js` uses the official `@google/genai` SDK for the quiz and chat endpoints when running under Node.

### Ask Imali

`ai-assistant.php` and `api/ai/ask.php` let the user ask money questions in plain language. `api/ai/status.php` tells the page whether AI is available. If `GEMINI_API_KEY` is not set, or the user has turned AI assistance off in settings, the assistant shows a clear message instead of failing silently.

The assistant is designed to give general, educational guidance. It is not a licensed financial adviser, and it should not be treated as one.

### Limits and costs

Gemini calls are made from the server, so the API key never reaches the browser. Usage is billed by Google according to the model and your account's terms. Review Google's current pricing and quotas before a public launch.

---

## Payment proof scanning

This feature lets a user photograph or upload proof of a payment, have it read by AI, and turn the result into transactions.

**Workflow:**

1. The user uploads an image or PDF through `api/proofs/upload.php`. The file is stored in `uploads/payment-proofs/` and a row is created in `payment_proofs`.
2. The browser-side scanner (`js/proof-scanner.js`) can show a preview and collect the user's corrections.
3. Gemini extracts fields such as amount, date, description, direction (money in or out) and document type.
4. `includes/proof_import.php` normalises the data. It converts relative dates ("yesterday", "last Friday") to real dates, parses amounts, works out whether money was paid or received using the account holder's name, and maps the document to a category.
5. `api/proofs/import.php` creates the matching transactions, with a summary of what was imported and any date notices.

**Privacy and retention:** uploaded proofs are kept only for the retention period set in settings. The uploads folder is blocked from direct web access by its own `.htaccess`. Users should be told clearly what is stored and for how long.

---

## Reports, statements and sharing

`reports.php` produces exports in three formats, all built in pure PHP with no extra libraries:

- **PDF** (`generate_pure_pdf_document` and `generate_readable_pdf_document` in `includes/reports_engine.php`)
- **Excel** (`generate_excel_spreadsheet`)
- **CSV** (`generate_csv_spreadsheet`)

Downloads go through `api/reports/download.php`, and every export is logged in `report_exports`.

### Statements

`includes/statement_view.php` renders a statement for a period. Users can share a statement through a **QR code** that opens `statement.php?t=<share_token>`. The QR image is generated through the QR code service, and `js/qr.js` handles the display.

Shared statements have controls:

- A **share token** that is unique and hard to guess.
- An optional **expiry** (`share_expires_at`).
- **Revocation** (`share_revoked_at`), so the owner can cancel a link.
- A **view counter** and last-viewed time.
- A **snapshot** of the data at the time of sharing (`snapshot_json`), so the shared view does not change if the owner's records change later.

`js/export-months.js` lets users pick a range of months to export.

---

## Authentication and security

- **Passwords** are hashed with `password_hash()` and checked with `password_verify()`.
- **Sessions** use the cookie name `imali_session`, with `HttpOnly` set. The `Secure` flag and `SameSite` setting depend on whether the request is over HTTPS.
- **CSRF protection** (`includes/csrf.php`): every form and JSON request must carry a valid token. Use `csrf_field()` in forms and `require_csrf()` in handlers.
- **Remember-me tokens** are stored hashed in `remember_me_tokens`.
- **Password reset tokens** are single-use and stored in `password_reset_tokens`.
- **Protected pages** call `require_auth()` (or `require_auth(true)` for JSON endpoints, which return an error instead of redirecting).
- **Audit trail**: important account actions are written to `audit_events`.
- **Blocked files**: `.htaccess` files in `config/`, `database/`, `storage/`, `uploads/` and the root block direct access to `.env`, `.sql`, `.md`, `.log`, `.sh` and similar files.
- **Sanitised canonical URLs**: `includes/seo.php` checks the Host header before building links, to prevent host-header injection.
- **Secrets stay on the server**: API keys are read from `.env` and are never sent to the browser.

---

## Languages and localisation

The interface supports multiple languages. The translation files in `storage/i18n/` are JSON and cover:

- `af` (Afrikaans)
- `ar` (Arabic)
- `nso` (Sepedi)
- `ss` (siSwati)
- `xh` (isiXhosa)
- `zu` (isiZulu)

English is built into `includes/i18n.php` and is the fallback language.

Supporting code:

- `includes/i18n.php` holds the catalogue and language helpers. Missing strings fall back to English.
- `includes/page_translator.php` translates whole pages where needed.
- `api/translate.php` and `api/translate-batch.php` translate individual strings or batches, and store results in `dynamic_translations`.
- `js/settings-language.js` lets users change language from settings.

**Adding a language:** copy `storage/i18n/zu.json` to a new file named with the language code, translate the values, then add the language to the list in `includes/i18n.php` and to the language selector in settings.

---

## Currencies and reference rates

- **Currencies**: `includes/currencies.php` lists supported currencies grouped by region. South African Rand (ZAR) is the default. The list also includes currencies from Southern, East, West and Central Africa. Users choose their currency at registration or in settings.
- **Reference rates**: `includes/rates.php` fetches ZAR to USD reference data from the South African Reserve Bank (SARB) and falls back to the Frankfurter API when SARB is unavailable. Rates are cached for a short period by `includes/external_api.php` and `includes/cache.php`.

Reference rates are for information only. They are not live trading rates and should not be used for payments.

---

## Email and notifications

Transactional email can be sent through **Resend** (`includes/resend.php`) or **Brevo** (`includes/brevo.php`), depending on which keys are configured. Email addresses are checked with `includes/email-validation.php` and an optional external validation service.

Users control notifications in settings. Delivery attempts are logged in `notification_delivery_history`.

If no email provider is configured, email features are skipped and the app continues to work.

---

## SEO, PWA and offline support

### Search engine optimisation

- `includes/seo.php` gives every public page a title, description, canonical URL, Open Graph and social card tags, and JSON-LD structured data (organisation, website, WebPage and breadcrumbs).
- Only pages listed in `seo_pages()` are indexable. All other pages are marked `noindex`, which keeps private areas out of search results.
- `sitemap.php` generates `sitemap.xml` from the same page list.
- `robots.php` generates `robots.txt`. It allows public pages and disallows `api/`, `actions/`, `includes/`, `config/`, `database/`, `storage/`, `uploads/` and `statement.php`.

### Installable app

`manifest.webmanifest` lets users install Imali Smart to their home screen. `service-worker.js` caches the core shell (`index.php`, the main stylesheets, `js/app.js` and the manifest) so the app can still open on a weak connection. API requests are never cached.

---

## Deployment

### Shared hosting (InfinityFree or similar)

1. Create a MySQL database in the hosting control panel and note the host, name, user and password.
2. Import `database/schema.sql` in phpMyAdmin.
3. Upload all PHP, CSS, JS, asset, config, includes and storage files. Do not upload `node_modules/`, `.git/` or your local `.env`.
4. Create a `.env` file on the server with your production values. Set `APP_URL` to your public HTTPS address and `APP_DEBUG=false`.
5. Confirm that `.htaccess` is active (the HTTPS redirect and blocked files depend on it).
6. Visit the site, register a test account, and check that `robots.txt` and `sitemap.xml` load.

### Local XAMPP

1. Place the project in `htdocs/imali-smart/`.
2. Start Apache and MySQL from XAMPP.
3. Import the schema in phpMyAdmin.
4. Set `APP_URL` blank (the app will work it out) or to `http://localhost/imali-smart`.
5. Open `http://localhost/imali-smart/`.

### Production build of the front-end shell

```bash
npm run build
```

The build output in `dist/` is for the React shell. The PHP application is deployed as plain files.

---

## Troubleshooting

**"MySQL connection failed" in the logs**
- *Unknown database*: check `DB_NAME` in `.env`, and import `database/schema.sql`.
- *Access denied*: check `DB_USER` and `DB_PASSWORD`. Make sure the user has access to the database.
- Confirm the host and port. On shared hosting, use the MySQL host shown in your control panel, not `localhost`.

**AI features say they are unavailable**
- Check that `GEMINI_API_KEY` is set and that the server can reach `generativelanguage.googleapis.com`.
- Check that AI assistance is turned on in settings.
- Try a different model by setting `GEMINI_MODEL` and `GEMINI_FALLBACK_MODELS`.

**Shared statement link or QR code does not work**
- Confirm `APP_URL` is correct (or blank so it is detected).
- Check the share has not expired or been revoked.

**Page styles look old after an update**
- The app adds file timestamps to asset links, so a hard refresh usually fixes it. If the service worker is still serving old files, unregister it in the browser's developer tools and reload.

**Language does not change**
- Check that the language code is in `storage/i18n/` and is listed in `includes/i18n.php`.

**Session keeps logging out over HTTPS**
- Set `PREVIEW_HTTPS=true` when testing over HTTPS locally, so cookies are marked secure correctly.

**Uploads fail**
- Check that `uploads/payment-proofs/` exists and is writable by PHP. Check PHP's `upload_max_filesize` and `post_max_size` limits.

---

## Security checklist before publishing

Before making the project public or deploying it:

- [ ] Remove `.env` from the project folder, and never commit it. Rotate any keys that have been shared or stored in an archive.
- [ ] Remove `docs/demo_users.md` and the demo seed file from any public copy, or change every demo password.
- [ ] Set `APP_DEBUG=false`.
- [ ] Use a dedicated database user with only the privileges the app needs, not `root`.
- [ ] Confirm HTTPS is enforced and `.htaccess` is active.
- [ ] Confirm `config/`, `database/`, `storage/` and `uploads/` cannot be read from the browser.
- [ ] Add a `.gitignore` that excludes `.env`, `node_modules/`, `dist/`, `storage/cache/` and `uploads/`.
- [ ] Review the privacy policy and terms (`privacy-policy.php`, `terms.php`) and make sure they match how proofs and data are stored and retained.
- [ ] Set a sensible Gemini usage limit or budget in Google Cloud.

---

## Contributing

1. Fork the repository and create a feature branch.
2. Keep changes focused. Follow the existing patterns: pages in the root, form handlers in `actions/`, JSON endpoints in `api/`, shared logic in `includes/`.
3. Use the CSRF helpers for every form and POST request, and `PDO` prepared statements for every query.
4. Add any database change as a new migration in `database/migrations/` and update `schema.sql`.
5. Test with the demo data, in at least one language other than English where the change touches text.
6. Open a pull request describing what changed and how you tested it.

---

## Licence

No licence file is included in the project as supplied. Add a `LICENSE` file before publishing or sharing the code, so others know how they may use it. The React shell's source header uses `SPDX-License-Identifier: Apache-2.0`, which should be reconciled with whatever licence you choose for the project as a whole.

---

*Imali Smart: money confidence for everyone.*
