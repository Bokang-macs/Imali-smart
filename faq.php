<?php
// Frequently Asked Questions only
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Frequently Asked Questions · Imali Smart';
$active_page = 'faq.php';
require_once __DIR__ . '/includes/header.php';
?>

<style>
@keyframes faqPulse {
    0%, 100% { opacity: 0.3; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1.15); }
}
.faq-quick-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    font-size: 0.82rem;
    font-weight: 500;
    border-radius: 9999px;
    background: var(--paper-deep, #F1F5F9);
    color: var(--ink, #172033);
    border: 1px solid var(--line, #E2E8F0);
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}
.faq-quick-chip:hover {
    background: var(--brand-navy, #12305A);
    color: #FFFFFF;
    border-color: var(--brand-navy, #12305A);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(18, 48, 90, 0.12);
}
.faq-chat-box {
    max-height: 480px;
    min-height: 180px;
    overflow-y: auto;
    padding: 20px;
    background: var(--paper, #F8FAFC);
    border-radius: 14px;
    border: 1px solid var(--line, #E2E8F0);
}
.faq-details-item {
    padding: 16px 0;
    border-bottom: 1px solid var(--line, #E2E8F0);
}
.faq-details-item:last-child {
    border-bottom: none;
}
.faq-details-item summary {
    font-weight: 700;
    font-size: 0.98rem;
    color: var(--ink, #172033);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    list-style: none;
}
.faq-details-item summary::-webkit-details-marker {
    display: none;
}
.faq-details-item summary::after {
    content: "+";
    font-size: 1.3rem;
    color: var(--ink-faint, #64748B);
    font-weight: 400;
    transition: transform 0.2s ease;
}
.faq-details-item[open] summary::after {
    content: "−";
    color: var(--brand-green, #16A34A);
}
.faq-details-item p, .faq-details-item ul {
    margin-top: 10px;
    font-size: 0.9rem;
    line-height: 1.65;
    color: var(--ink-soft, #475569);
}
</style>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">INTELLIGENT KNOWLEDGE &amp; HELP</span>
        <h1>Frequently Asked Questions</h1>
        <p>Find clear answers about recording transactions, budgeting, savings, reports, security, and using Imali Smart.</p>
    </div>
    <div class="page-heading__actions">
        <a class="btn btn--quiet btn--small" href="analytics.php">Reports Hub</a>
        <a class="btn btn--primary btn--small" href="transactions.php">Record Expense</a>
    </div>
</div>

<!-- Static Categorized FAQ Sections -->
<div class="card" style="padding: 30px; background: var(--panel, #FFFFFF); border: 1px solid var(--line, #E2E8F0); border-radius: 18px; box-shadow: var(--shadow-card);">
    <div style="border-bottom: 2px solid var(--line, #E2E8F0); padding-bottom: 12px; margin-bottom: 20px;">
        <h2 style="color: var(--ink, #172033); margin: 0; font-size: 1.35rem;">Comprehensive Knowledge Base</h2>
        <p style="margin: 4px 0 0; font-size: 0.88rem; color: var(--ink-soft, #475569);">
            Browse detailed answers categorized across all core features of the Imali Smart platform.
        </p>
    </div>

    <!-- Section 1: Financial Graphs & Large Amounts -->
    <div style="margin-bottom: 28px;">
        <h3 style="color: var(--brand-navy, #12305A); font-size: 1.1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2rem;">📈</span> Financial Graphs &amp; Large Financial Amounts
        </h3>

        <details class="faq-details-item" open>
            <summary>How does Imali Smart handle large financial amounts like R1,500,000 or R25,000,000?</summary>
            <p>
                Imali Smart is built without arbitrary financial caps. The system supports full 64-bit precision for monetary values, accommodating micro-expenses (e.g. R0.50, R15.00) up to corporate transactions in the hundreds of millions of Rand. Chart axes calculate nice round maximums dynamically, using compact notation (k, M, B) on axis ticks so labels never clip against the chart boundaries, while full unrounded amounts are displayed upon hovering over data points.
            </p>
        </details>

        <details class="faq-details-item">
            <summary>Can I input amounts with commas or the Rand currency symbol?</summary>
            <p>
                Yes. The application's input parser automatically sanitizes formatted strings like <code>R 1,500,000.00</code>, <code>R250,000</code>, or plain <code>1500000</code>. Comma thousands separators and decimal cent fractions are processed with accurate precision to prevent truncation errors.
            </p>
        </details>

        <details class="faq-details-item">
            <summary>Are chart figures calculated from my real database records?</summary>
            <p>
                Always. Every bar chart, cashflow line, donut breakdown, and net balance visual is calculated directly from your authenticated database transactions. There is zero fabricated demonstration data once you begin recording your financial activities.
            </p>
        </details>
    </div>

    <!-- Section 2: Expense Recording & Data Protection -->
    <div style="margin-bottom: 28px;">
        <h3 style="color: var(--brand-navy, #12305A); font-size: 1.1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2rem;">📝</span> Expense Recording &amp; Financial Inputs
        </h3>

        <details class="faq-details-item">
            <summary>How does Imali Smart prevent accidental duplicate transactions?</summary>
            <p>
                The transaction recording engine features both client-side and server-side safeguards. Upon clicking "Record Transaction", the submit button is immediately locked with visual feedback. The server verifies transaction uniqueness within a tight time window, preventing duplicate ledger entries even if a user double-clicks or experiences a laggy mobile connection.
            </p>
        </details>

        <details class="faq-details-item">
            <summary>Can I record decimal amounts with cents?</summary>
            <p>
                Yes. The application accepts two decimal places (e.g. <code>R 125.50</code>, <code>R 4,899.95</code>) across all transactions, budgets, savings deposits, debt payments, and till reconciliations.
            </p>
        </details>
    </div>

    <!-- Section 3: Executive Reports & Downloads -->
    <div style="margin-bottom: 28px;">
        <h3 style="color: var(--brand-navy, #12305A); font-size: 1.1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2rem;">📑</span> Executive Reports &amp; Downloads
        </h3>

        <details class="faq-details-item">
            <summary>What report formats can I download from Imali Smart?</summary>
            <p>
                Under the <strong>Reports Hub</strong> (<code>analytics.php</code>), you can download three user-friendly formats:
            </p>
            <ul>
                <li><strong>Executive PDF Financial Statements:</strong> High-resolution, professionally formatted statements ready for print, bank submissions, or your own records.</li>
                <li><strong>Excel-Compatible Worksheets (.xls):</strong> Formatted spreadsheets with headers, category totals, and currency columns ready for Microsoft Excel and Google Sheets.</li>
                <li><strong>Clean CSV Transaction History:</strong> Lightweight raw data export for accountants, bookkeepers, and tax advisors.</li>
            </ul>
        </details>

        <details class="faq-details-item">
            <summary>Are downloaded reports suitable for SARS tax filing?</summary>
            <p>
                Yes. Reports feature clear date ranges, categorized income and personal expenses, and reconciled totals. You can export an entire tax year (e.g., 1 March to 28 February) in PDF or Excel format to hand directly to your registered tax practitioner or to assist with your SARS eFiling submission.
            </p>
        </details>

        <details class="faq-details-item">
            <summary>Can another user access or download my financial statements?</summary>
            <p>
                Strictly no. All report generation queries are secured to your authenticated user account ID. Download links reject unauthorized access attempts and never expose sensitive database configurations or system credentials.
            </p>
        </details>
    </div>

    <!-- Section 4: Budgeting, Debt & Savings -->
    <div style="margin-bottom: 28px;">
        <h3 style="color: var(--brand-navy, #12305A); font-size: 1.1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2rem;">💡</span> Budgeting, Debt Snowball &amp; Savings
        </h3>

        <details class="faq-details-item">
            <summary>How does the 50/30/20 budget framework operate?</summary>
            <p>
                The 50/30/20 guideline divides your net take-home income into three functional baskets:
            </p>
            <ul>
                <li><strong>50% Needs:</strong> Essential non-negotiables (rent, home loan, groceries, electricity, municipal rates, basic transport).</li>
                <li><strong>30% Wants:</strong> Discretionary choices (dining out, entertainment, holidays, hobbies).</li>
                <li><strong>20% Financial Cushion:</strong> Wealth-building and safety moves (emergency savings, stokvel contributions, debt snowball acceleration).</li>
            </ul>
        </details>

        <details class="faq-details-item">
            <summary>How does the Debt Snowball payoff method work?</summary>
            <p>
                You list all your debts from smallest balance to largest. You pay minimum contractual amounts on all accounts to safeguard your credit score, while channeling any surplus discretionary funds into wiping out the smallest debt first. Once settled, that freed-up installment rolls over into attacking the next debt, compounding your payment velocity.
            </p>
        </details>

        <details class="faq-details-item">
            <summary>Can I track stokvel savings in Imali Smart?</summary>
            <p>
                Yes. You can create a dedicated savings milestone under <strong>Savings</strong> for your monthly stokvel or savings club contributions. You can track cumulative deposits, milestone targets, and rotational payout distributions.
            </p>
        </details>
    </div>

    <!-- Section 5: Business Operations & Till Management -->
    <div style="margin-bottom: 28px;">
        <h3 style="color: var(--brand-navy, #12305A); font-size: 1.1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2rem;">🏪</span> Personal Money Tools
        </h3>

        <details class="faq-details-item">
            <summary>How does the personal transaction ledger prevent cashier shortages?</summary>
            <p>
                At the end of a shift, the cashier or store manager enters the physical cash drawer contents (count of R200, R100, R50, R20, R10 notes and coins) alongside the Speedpoint batch settlement slip. The tool calculates the total counted money and instantly compares it with shift POS sales. Any discrepancy (shortage in red, surplus in blue) is flagged on the spot with a permanent shift log.
            </p>
        </details>

        <details class="faq-details-item">
            <summary>How do I manage customer credit for informal trading?</summary>
            <p>
                Under the <strong>Customer Credit</strong> book (<code>customer-credit.php</code>), shopkeepers can record trusted customer credit purchases ("papgeld / on account"), track repayment installments, and maintain customer credit limits.
            </p>
        </details>
    </div>

    <!-- Section 6: POPIA, Security & Privacy -->
    <div>
        <h3 style="color: var(--brand-navy, #12305A); font-size: 1.1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2rem;">🛡️</span> Privacy, Security &amp; POPIA Compliance
        </h3>

        <details class="faq-details-item">
            <summary>How is my personal financial data protected under South African law?</summary>
            <p>
                Imali Smart strictly complies with South Africa's <strong>Protection of Personal Information Act (POPIA Act 4 of 2013)</strong>. Your financial ledger is strictly isolated to your user account. We never sell or share user data, and our AI coaching system only reads aggregate 30-day totals with your explicit consent.
            </p>
        </details>

        <details class="faq-details-item">
            <summary>Does Imali Smart ask for banking passwords or card PINs?</summary>
            <p>
                <strong>Never.</strong> Imali Smart will never ask for your bank login details, card PINs, ATM cards, CVV numbers, or one-time pins (OTPs). The application is an independent financial management ledger and coaching platform.
            </p>
        </details>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
