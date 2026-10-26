<?php
// Terms of use page.
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = is_authenticated() ? current_user() : null;
$page_title = 'Terms of Service · Imali Smart';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">USER AGREEMENT &amp; PLATFORM POLICY</span>
        <h1>Terms of Service</h1>
        <p>Clear, transparent principles governing the use of your Imali Smart workspace.</p>
    </div>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <h2 style="color: var(--ink);">1. Financial Record-Keeping Platform</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            Imali Smart is a self-directed wealth planning, cash flow management, and till reconciliation workspace. Calculations, budget summaries, and educational materials are designed to facilitate personal financial awareness and do not constitute registered financial, legal, or investment advice under the FAIS Act.
        </p>

        <h2 style="color: var(--ink); margin-top: 24px;">2. User Responsibility &amp; Credential Protection</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            You are responsible for keeping your login credentials confidential. Always verify external payment destinations independently and never disclose banking passwords, OTPs, or card security codes to third parties.
        </p>

        <h2 style="color: var(--ink); margin-top: 24px;">3. Invoicing and Till Reconciliation</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            The personal records you create in Imali Smart reflect the information you enter. Keep your records accurate and seek qualified advice for tax or legal matters.
        </p>

        <h2 style="color: var(--ink); margin-top: 24px;">4. Support &amp; Governing Law</h2>
        <p style="color: var(--ink-soft); line-height: 1.6; font-size: 13.5px;">
            These terms are governed by the laws of the Republic of South Africa. For questions or support, reach out via our <a href="contact.php" style="color: var(--marigold-deep); font-weight: 700;">Contact Center</a> or email <a href="mailto:support@imalismart.co.za" style="color: var(--marigold-deep); font-weight: 700;">support@imalismart.co.za</a>.
        </p>
    </section>

    <aside class="panel">
        <div class="panel__heading">
            <div>
                <h2>Summary of Terms</h2>
                <p>Key highlights</p>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13px; color: var(--ink-soft);">
            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 10px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">✓ User Independence</strong>
                <span>You maintain sole ownership of your numbers and records.</span>
            </div>
            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 10px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">✓ No Hidden Fees</strong>
                <span>Clear, predictable platform usage without unexpected charges.</span>
            </div>
            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 10px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">✓ Safe &amp; Confidential</strong>
                <span>Designed with strict data isolation for every account.</span>
            </div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
