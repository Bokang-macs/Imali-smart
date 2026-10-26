<?php
// Privacy policy page.
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = is_authenticated() ? current_user() : null;
$page_title = 'Privacy Policy & POPIA Protection';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">YOUR PRIVACY &amp; DATA SECURITY</span>
        <h1>Privacy, in Plain Language</h1>
        <p>Our explicit commitments under the Protection of Personal Information Act (POPIA Act 4 of 2013).</p>
    </div>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <h2 style="color: var(--ink);">1. What Imali Smart Stores</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            We store only the record entries, transactions, budget envelope definitions, personal savings, debt balances, and cash-flow records that you deliberately create within your account. Every query is strictly isolated by your authenticated user ID.
        </p>

        <h2 style="color: var(--ink); margin-top: 24px;">2. What We Will Never Ask For</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            Imali Smart does <strong>not</strong> request, store, or process your commercial bank account passwords, card CVVs, card PINs, or One-Time Passwords (OTPs). Your ledger entries represent your private budgeting records and are never transmitted to unauthorized third parties.
        </p>

        <h2 style="color: var(--ink); margin-top: 24px;">3. Data Ownership &amp; Export</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            You retain 100% ownership of your financial records. You can download a complete CSV spreadsheet or complete JSON data archive at any time via the <strong>Export Data</strong> module.
        </p>

        <h2 style="color: var(--ink); margin-top: 24px;">4. Questions &amp; Data Officer Inquiries</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            For any queries regarding personal data protection, deletion requests, or POPIA verification, contact our compliance desk at <a href="mailto:privacy@imalismart.co.za" style="color: var(--marigold-deep); font-weight: 700;">privacy@imalismart.co.za</a> or submit a message through our <a href="contact.php" style="color: var(--marigold-deep); font-weight: 700;">Contact Support</a> page.
        </p>
    </section>

    <aside class="panel">
        <div class="panel__heading">
            <div>
                <h2>Key Safeguards</h2>
                <p>Security commitments</p>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13px; color: var(--ink-soft);">
            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 10px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">Encrypted Credentials</strong>
                <span>All passwords are hashed with standard Bcrypt algorithms before storage.</span>
            </div>
            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 10px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">POPIA Compliant</strong>
                <span>Complies with South African Information Regulator data principles.</span>
            </div>
            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 10px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">Zero Third-Party Selling</strong>
                <span>We never monetize, broker, or sell personal financial records to advertising networks.</span>
            </div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
