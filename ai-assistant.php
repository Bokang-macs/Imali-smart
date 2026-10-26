<?php
// Ask Imali (AI) - Financial Advisor & Knowledge Center
// Powered by Google Gemini AI with South African financial intelligence & local language translation.
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/gemini.php';

require_auth();
$user = current_user();

$page_title = 'Ask Imali (AI) · Financial Advisor';
$page_js = 'faq-assistant.js';
$active_page = 'ai-assistant.php';

// Safe 30-day aggregate financial totals for contextual advice
$summary = [
    'income' => 0.0,
    'expenses' => 0.0,
    'balance' => (float) ($user['opening_balance'] ?? 0),
    'top_category' => null,
];

if (database_ready()) {
    try {
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        $s = db()->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expenses
            FROM transactions 
            WHERE user_id = :id AND deleted_at IS NULL AND transaction_date >= :since
        ");
        $s->execute(['id' => $user['id'], 'since' => $thirtyDaysAgo]);
        $row = $s->fetch() ?: [];
        $summary['income'] = (float) ($row['income'] ?? 0);
        $summary['expenses'] = (float) ($row['expenses'] ?? 0);
        $summary['balance'] += ($summary['income'] - $summary['expenses']);
    } catch (Throwable $e) {}
}

require __DIR__ . '/includes/header.php';
?>

<style>
@keyframes faqPulse {
    0%, 100% { opacity: 0.3; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1.15); }
}
.faq-quick-chip {
    padding: 7px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 20px;
    border: 1px solid var(--line-strong, #CBD5E1);
    background: var(--paper-deep, #F8FAFC);
    color: var(--ink, #172033);
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.faq-quick-chip:hover {
    background: var(--brand-navy, #12305A);
    color: #FFFFFF;
    border-color: var(--brand-navy, #12305A);
    transform: translateY(-1px);
}
.faq-chat-box {
    min-height: 280px;
    max-height: 520px;
    overflow-y: auto;
    padding: 16px;
    background: var(--paper, #FAFAF9);
    border: 1px solid var(--line, #E2E8F0);
    border-radius: 14px;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
}
</style>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">GOOGLE GEMINI FINANCIAL INTELLIGENCE</span>
        <h1>Ask Imali (AI) Advisor</h1>
        <p>Your interactive personal money coach. Chat in English or any South African language with practical, clear answers.</p>
    </div>
    <div class="page-heading__actions">
        <a class="btn btn--accent btn--small" href="transactions.php">Transactions</a>
    </div>
</div>

<!-- Main Interactive AI Assistant Card (Identical engine & capabilities as FAQ) -->
<div class="card" data-faq-assistant style="padding: 28px; background: var(--panel, #FFFFFF); border: 1px solid var(--line, #E2E8F0); border-radius: 20px; box-shadow: var(--shadow-card); margin-bottom: 32px;">
    
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #12305A 0%, #16A34A 100%); display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-size: 1.4rem; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);">
                ✦
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h2 style="margin: 0; font-size: 1.3rem; color: var(--ink, #172033);">Ask Imali AI Advisor</h2>
                    <span style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; background: #DCFCE7; color: #15803D; padding: 2px 8px; border-radius: 6px;">
                        Google Gemini Active
                    </span>
                </div>
                <p style="margin: 2px 0 0; font-size: 0.85rem; color: var(--ink-soft, #475569);">
                    Safe financial guidance tailored to South African households, students, and entrepreneurs.
                </p>
            </div>
        </div>

        <button type="button" class="btn btn--quiet btn--small" data-faq-clear style="font-size: 0.8rem; padding: 6px 14px;">
            ↻ Start New Question
        </button>
    </div>

    <!-- User Financial Context Pill -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: center; background: rgba(18, 48, 90, 0.04); border: 1px solid var(--line); border-radius: 10px; padding: 10px 16px; margin-bottom: 18px; font-size: 12.5px;">
        <span style="color: var(--ink-soft); font-weight: 700;">Account Context:</span>
        <span>Income (30d): <strong><?= money($summary['income']) ?></strong></span>
        <span>Expenses (30d): <strong><?= money($summary['expenses']) ?></strong></span>
        <span>Current Balance: <strong style="color: #16a34a;"><?= money($summary['balance']) ?></strong></span>
        <span class="badge" style="margin-left: auto; font-size: 10.5px; background: var(--paper-deep);">Private · Safe Aggregates Only</span>
    </div>

    <!-- Suggested Quick Topic Chips -->
    <div style="margin-bottom: 18px;">
        <span style="display: block; font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-faint, #64748B); margin-bottom: 8px;">
            Suggested Questions &amp; Popular Topics:
        </span>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <button type="button" class="faq-quick-chip" data-faq-prompt="How does the 50/30/20 budgeting rule work for South African households?">
                 50/30/20 Budgeting Rule
            </button>
            <button type="button" class="faq-quick-chip" data-faq-prompt="How do I clear high-interest store cards and loans using the Debt Snowball?">
                 Debt Snowball Strategy
            </button>
            <button type="button" class="faq-quick-chip" data-faq-prompt="How do I calculate food item or kota pricing, markup, and gross profit margin?">
                 Kota Pricing &amp; Profit Margins
            </button>
            <button type="button" class="faq-quick-chip" data-faq-prompt="How should I structure my emergency fund savings across 3 stages?">
                ️ 3-Stage Emergency Savings Cushion
            </button>
            <button type="button" class="faq-quick-chip" data-faq-prompt="What should I do if a caller asks for an OTP or claims to be from my bank's fraud unit?">
                ⚠️ Bank Impersonation Scam Defense
            </button>
        </div>
    </div>

    <!-- Live Conversation Log Container -->
    <div class="faq-chat-box" data-faq-log aria-live="polite">
        <div class="faq-chat-msg faq-chat-msg--assistant" style="margin-bottom: 18px; display: flex; flex-direction: column; align-items: flex-start;">
            <div style="max-width: 85%; padding: 14px 18px; border-radius: 16px 16px 16px 4px; font-size: 0.92rem; line-height: 1.6; background: var(--paper-deep, #F1F5F9); color: var(--ink, #172033); border: 1px solid var(--line, #E2E8F0);">
                <p style="margin: 0;">
                    <strong>Sawubona <?= e($user['full_name'] ?? 'there') ?>! Hello.</strong> I am your Ask Imali AI advisor. 
                    I have your live financial picture loaded (<?= money($summary['balance']) ?> current position). 
                    Ask me any question in English, isiZulu, isiXhosa, Afrikaans, or your preferred language. 
                    I will never ask for PINs, passwords, OTPs, or CVVs.
                </p>
            </div>
        </div>
    </div>

    <!-- Question Input Form -->
    <form data-faq-form style="margin-top: 20px;">
        <?= csrf_field() ?>
        <input type="hidden" name="consent" value="1">

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 280px;">
                    <textarea 
                        name="question" 
                        maxlength="1500" 
                        rows="2" 
                        required 
                        placeholder="Type your money question (e.g. How do I balance irregular income with family expenses?)"
                        style="width: 100%; padding: 12px 16px; border: 1.5px solid var(--line-strong, #CBD5E1); border-radius: 12px; font-family: inherit; font-size: 0.94rem; background: var(--panel, #FFFFFF); color: var(--ink, #172033); resize: vertical; box-sizing: border-box;"
                    ></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="ai-language-select" style="font-size: 0.82rem; font-weight: 600; color: var(--ink-soft, #475569);">
                        Language:
                    </label>
                    <select id="ai-language-select" name="language" style="padding: 6px 12px; border-radius: 8px; border: 1px solid var(--line-strong, #CBD5E1); font-size: 0.85rem; background: var(--panel, #FFFFFF); color: var(--ink, #172033);">
                        <?php foreach (gemini_language_options() as $val => $lbl) : ?>
                            <option value="<?= e($val) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 12px;">
                    <span data-faq-status style="font-size: 0.82rem; color: var(--ink-soft, #475569); font-weight: 500;"></span>
                    <button type="submit" class="btn btn--primary" style="padding: 9px 24px; font-weight: 700; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px;">
                        Send Message <span>➤</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Privacy Boundary Section -->
<section class="panel">
    <h2>Privacy &amp; Security Boundary</h2>
    <p class="muted" style="line-height: 1.6;">
        Ask Imali AI operates under strict POPIA compliance. It receives only aggregate anonymous summaries (total income, expenses, and current balances). 
        It does not have access to account credentials, bank log-ins, or third-party tracking. 
        It provides educational financial explanations and cannot alter your database records.
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>