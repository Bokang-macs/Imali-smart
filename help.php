<?php
// Help page.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Help Center & Knowledge Base';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">USER SUPPORT &amp; GUIDANCE</span>
        <h1>Help Center &amp; User Guides</h1>
        <p>Learn how to navigate your workspace, configure budgets, track personal money, and find support.</p>
    </div>
    <div class="page-heading__actions">
        <a class="btn btn--quiet btn--small" href="faq.php">Read FAQs</a>
        <a class="btn btn--primary btn--small" href="contact.php">Contact Support</a>
    </div>
</div>

<div class="stat-grid" style="margin-bottom: 24px;">
    <div class="stat-card stat-card--mint">
        <span class="stat-card__label">Platform Security</span>
        <strong class="stat-card__value">Bank-Grade Privacy</strong>
        <span class="stat-card__note">POPIA Act 4 compliant &amp; fully confidential</span>
    </div>
    <div class="stat-card stat-card--gold">
        <span class="stat-card__label">Active Workspace</span>
        <strong class="stat-card__value">4 Specialized Hubs</strong>
        <span class="stat-card__note">Personal Finance, Education, AI Advisor</span>
    </div>
    <div class="stat-card stat-card--blue">
        <span class="stat-card__label">Support Availability</span>
        <strong class="stat-card__value">24/7 Guidance</strong>
        <span class="stat-card__note">Self-paced guides, calendar sync &amp; support desk</span>
    </div>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Workspace User Playbook</h2>
                <p>Four simple steps to getting maximum value from your Imali Smart platform</p>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px; font-size: 13px; color: var(--ink-soft); line-height: 1.6;">
            <div style="padding: 16px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                <strong style="color: var(--ink); font-size: 14px;">1. Establish Your Monthly Budget Envelopes</strong>
                <p style="margin: 6px 0 0 0;">
                    Visit the <strong>Budgets</strong> tab to allocate your income into essential categories (Groceries, Rent, Transport, Electricity). Set monthly thresholds so the system alerts you before you overspend.
                </p>
            </div>

            <div style="padding: 16px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                <strong style="color: var(--ink); font-size: 14px;">2. Accelerate Debt Payoff with the Snowball Method</strong>
                <p style="margin: 6px 0 0 0;">
                    Navigate to <strong>Debt Snowball</strong> to log your store accounts, credit cards, or personal loans. The system ranks debts by balance, calculating exact repayment dates so you eliminate interest faster.
                </p>
            </div>

            <div style="padding: 16px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                <strong style="color: var(--ink); font-size: 14px;">3. Keep personal money records current</strong>
                <p style="margin: 6px 0 0 0;">
                    Record income, essentials, savings and debt payments as they happen so your dashboard stays useful.
                </p>
            </div>

            <div style="padding: 16px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                <strong style="color: var(--ink); font-size: 14px;">4. Keep personal records current</strong><p>Record income, essentials and debt payments as they happen so your dashboard stays useful.</p>
            </div>
        </div>
    </section>

    <aside class="panel">
        <div class="panel__heading">
            <div>
                <h2>South African Helplines</h2>
                <p>Free national financial &amp; consumer advisory</p>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13px; color: var(--ink-soft);">
            <div style="padding: 14px; background: var(--paper-deep); border-left: 3px solid #22c55e; border-radius: 8px; border-top: 1px solid var(--line); border-right: 1px solid var(--line); border-bottom: 1px solid var(--line);">
                <strong style="color: var(--ink);">National Credit Regulator (NCR)</strong>
                <p style="margin: 4px 0 0 0; font-size: 12px;">Debt counseling accreditation, reckless lending investigations, and credit rights protection.<br>Toll-Free: <strong>0860 627 627</strong></p>
            </div>

            <div style="padding: 14px; background: var(--paper-deep); border-left: 3px solid #3b82f6; border-radius: 8px; border-top: 1px solid var(--line); border-right: 1px solid var(--line); border-bottom: 1px solid var(--line);">
                <strong style="color: var(--ink);">National Financial Ombud Scheme (NFO)</strong>
                <p style="margin: 4px 0 0 0; font-size: 12px;">Independent dispute resolution for commercial bank accounts, insurance, and loan providers.<br>Tel: <strong>0860 800 900</strong></p>
            </div>

            <div style="padding: 14px; background: var(--paper-deep); border-left: 3px solid #e8a33a; border-radius: 8px; border-top: 1px solid var(--line); border-right: 1px solid var(--line); border-bottom: 1px solid var(--line);">
                <strong style="color: var(--ink);">SARS Contact Centre</strong>
                <p style="margin: 4px 0 0 0; font-size: 12px;">Personal income tax and annual filing queries.<br>Tel: <strong>0800 00 7277</strong></p>
            </div>

            <div style="padding: 14px; background: var(--paper-deep); border-left: 3px solid #a855f7; border-radius: 8px; border-top: 1px solid var(--line); border-right: 1px solid var(--line); border-bottom: 1px solid var(--line);">
                <strong style="color: var(--ink);">Personal financial support</strong>
                <p style="margin: 4px 0 0 0; font-size: 12px;">Guidance on budgeting, debt support and consumer financial wellbeing.<br>Tel: <strong>0860 103 703</strong></p>
            </div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
