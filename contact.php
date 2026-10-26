<?php
// Contact page.
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$user = is_authenticated() ? current_user() : null;
$messageSent = false;
$error = null;

if (request_method_is('POST')) {
    require_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
        $error = 'Please provide your name, a valid email address, and your message.';
    } else {
        $messageSent = true;
    }
}

$page_title = 'Contact Support · Imali Smart';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">
    <div>
        <div class="ledger-line"></div>
        <span class="eyebrow">CUSTOMER CARE &amp; INQUIRIES</span>
        <h1>We're Here to Support Your Journey</h1>
        <p>Have questions about your workspace, budget setup or security? Reach out to our dedicated support desk.</p>
    </div>
</div>

<?php if ($messageSent) : ?>
    <div class="alert alert--success" data-auto-dismiss style="margin-bottom: 24px;">
        ✓ Thank you, <?= e($name) ?>! Your inquiry has been received. Our support team responds within 24 hours on working days.
    </div>
<?php endif; ?>

<?php if ($error) : ?>
    <div class="alert alert--error" style="margin-bottom: 24px;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel__heading">
            <div>
                <h2>Send Us a Message</h2>
                <p>Fill out the form below and an advisor will assist you</p>
            </div>
        </div>

        <form method="post" data-validate>
            <?= csrf_field() ?>
            <div class="form-field" style="margin-bottom: 14px;">
                <label for="name">Your Full Name</label>
                <input id="name" name="name" type="text" required placeholder="e.g. Sipho Ndlovu" value="<?= e($user['full_name'] ?? $_POST['name'] ?? '') ?>">
            </div>

            <div class="form-field" style="margin-bottom: 14px;">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" required placeholder="e.g. sipho@example.co.za" value="<?= e($user['email'] ?? $_POST['email'] ?? '') ?>">
            </div>

            <div class="form-field" style="margin-bottom: 14px;">
                <label for="subject">Inquiry Subject</label>
                <select id="subject" name="subject" class="select-field">
                    <option value="General Question">General Platform Question</option>
                    <option value="Personal Budgeting">Personal Finance &amp; Debt Snowball</option>
                    <option value="Personal money planning">Personal money planning</option>
                    <option value="Data Security">Security &amp; POPIA Compliance</option>
                    <option value="Technical Support">Technical Support &amp; Feature Feedback</option>
                </select>
            </div>

            <div class="form-field" style="margin-bottom: 20px;">
                <label for="message">How can we help you?</label>
                <textarea id="message" name="message" rows="6" required placeholder="Describe your question or feedback in detail..."><?= e($_POST['message'] ?? '') ?></textarea>
            </div>

            <button class="btn btn--primary" type="submit">
                Submit Support Message <span>→</span>
            </button>
        </form>
    </section>

    <aside class="panel">
        <div class="panel__heading">
            <div>
                <h2>Direct Channels</h2>
                <p>Official communication lines</p>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px; font-size: 13.5px; color: var(--ink-soft); line-height: 1.6;">
            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">Email Support</strong>
                <a href="mailto:support@imalismart.co.za" style="color: var(--marigold-deep); font-weight: 700; text-decoration: none;">support@imalismart.co.za</a>
                <div style="font-size: 11.5px; color: var(--ink-soft); margin-top: 2px;">Typical response time: under 4 hours</div>
            </div>

            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">Telephone &amp; WhatsApp</strong>
                <span style="color: var(--ink); font-weight: 700;">+27 11 555 0199</span>
                <div style="font-size: 11.5px; color: var(--ink-soft); margin-top: 2px;">Monday – Friday: 08:00 – 17:00 SAST</div>
            </div>

            <div style="padding: 14px; background: var(--paper-deep); border: 1px solid var(--line); border-radius: 12px;">
                <strong style="color: var(--ink); display: block; margin-bottom: 4px;">Headquarters</strong>
                <span style="color: var(--ink);">Imali Smart Hub, Rosebank Link, 187 Oxford Rd, Johannesburg, 2196</span>
            </div>

            <div style="padding: 12px 14px; background: rgba(232, 163, 58, 0.1); border-left: 3px solid var(--marigold); border-radius: 8px;">
                <strong style="color: var(--ink); font-size: 12.5px;">Security Reminder</strong>
                <p style="margin: 4px 0 0 0; font-size: 11.5px; color: var(--ink-soft);">
                    Our support team will never ask for your account password, banking details, card numbers, or OTP codes.
                </p>
            </div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>