<?php
// Registration page.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/currencies.php';

if (is_authenticated()) {
    redirect_to('dashboard.php');
}

$error = null;

if (request_method_is('POST')) {
    require_csrf();
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    // Personal finance is the only workspace.
    // The language is chosen on index.php or settings.php. Registration uses that same language.
    $preferredLang = current_lang();
    $currency = normalize_currency((string) ($_POST['currency_code'] ?? 'ZAR'));
    $openingBalance = 0.0;

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide your full name and a valid email address.';
    } elseif ($phone === '') {
        $error = 'Please provide your phone number.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please verify.';
    } elseif (!database_ready()) {
        $error = 'The workspace service is temporarily unavailable. Please try again shortly.';
    } else {
        try {
            // Check if email already exists
            $checkStmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $checkStmt->execute(['email' => $email]);
            if ($checkStmt->fetch()) {
                $error = 'An account with this email address already exists. Please sign in instead.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('
                    INSERT INTO users (full_name, email, phone_number, password_hash, preferred_language, currency_code, opening_balance)
                    VALUES (:name, :email, :phone, :hash, :lang, :curr, :balance)
                ');
                $stmt->execute([
                    'name' => $fullName,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'hash' => $hash,
                    'lang' => $preferredLang,
                    'curr' => $currency,
                    'balance' => $openingBalance,
                ]);

                $newUserId = (int) db()->lastInsertId();

                // Auto-login
                login_user($newUserId);
                set_current_lang($preferredLang);
                flash('welcome', 'Account created successfully! Welcome to your Imali Smart dashboard, ' . strtok($fullName, ' ') . '.');
                redirect_to('dashboard.php');
            }
        } catch (Throwable $e) {
            $error = 'Could not create account: ' . $e->getMessage();
        }
    }
}

$currentLang = current_lang();
$isRtl = is_rtl();
?><!doctype html>
<html lang="<?= e($currentLang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>" data-theme="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php seo_render_head(t('register')); ?>
    <link rel="icon" type="image/png" href="assets/logo/imali-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/variables.css?v=<?= asset_v('css/variables.css') ?>">
    <link rel="stylesheet" href="css/global.css?v=<?= asset_v('css/global.css') ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?= asset_v('css/layout.css') ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?= asset_v('css/auth.css') ?>">
    <script>
      (function(){
        try {
          var t = localStorage.getItem('imali_theme_v1') || 'default';
          if (t === 'default') {
            var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
          } else {
            document.documentElement.setAttribute('data-theme', t);
          }
        } catch(e){}
      })();
    </script>
</head>
<body class="app-body">
<main class="auth-layout">
    <aside class="auth-aside">
        <a class="brand-mark" href="index.php" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
            <img src="assets/logo/imali-logo.png" alt="Imali Smart" style="max-height: 64px; width: auto; object-fit: contain; border-radius: 8px;">
            <span style="font-family: var(--font-display); font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">Imali Smart</span>
        </a>
        <div class="auth-aside__copy" style="max-width: 480px; position: relative; z-index: 2;">
            <span class="eyebrow" style="color: var(--marigold, #f59e0b); font-weight: 800; letter-spacing: 0.1em;">JOIN IMALI SMART</span>
            <h1 style="color: #ffffff; font-size: 2.1rem; font-weight: 800; line-height: 1.25; margin: 14px 0; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">Build genuine financial clarity.</h1>
            <p style="color: #e2e8f0; font-size: 1.05rem; line-height: 1.6; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">From student allowances to household budgets and irregular income, Imali Smart brings your everyday money into one clear view.</p>
        </div>

        <div style="background: rgba(15, 23, 42, 0.75); border: 1.5px solid rgba(255, 255, 255, 0.2); border-radius: 14px; padding: 18px; margin-top: auto; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.45); backdrop-filter: blur(8px);">
            <div style="font-weight: 800; color: #ffffff; margin-bottom: 8px; font-size: 13.5px; display: flex; align-items: center; gap: 6px;">Privacy &amp; Data Security First</div>
            <p style="font-size: 12.5px; color: #cbd5e1; margin: 0; line-height: 1.55;"><?= e(t('popia_compliance')) ?> <?= e(t('no_bank_passwords')) ?></p>
        </div>
    </aside>

    <section class="auth-panel">
        <div style="width: 100%; max-width: 490px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--line); padding-bottom: 14px; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px;">
                <a href="login.php" class="btn btn--small btn--quiet" style="text-decoration: none;"><?= e(t('login')) ?></a>
                <a href="register.php" class="btn btn--small btn--accent" style="text-decoration: none;"><?= e(t('register')) ?></a>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <!-- Theme Toggle Button -->
                <button class="theme-quick-btn" type="button" data-theme-toggle aria-label="<?= e(t('toggle_theme')) ?>" title="<?= e(t('toggle_theme')) ?>" style="width: 32px; height: 32px; border-radius: 50%; background: var(--paper-deep); border: 1px solid var(--line); display: grid; place-items: center; cursor: pointer; color: var(--ink);">
                    <span class="theme-icon"></span>
                </button>

                <a href="index.php" style="font-size: 12px; color: var(--ink-soft); text-decoration: none; font-weight: 600;">← <?= e(t('nav_dashboard')) ?></a>
            </div>
        </div>

        <form class="auth-form" method="post" data-validate>
            <div class="auth-form__heading">
                <div class="ledger-line"></div>
                <h2><?= e(t('register')) ?></h2>
                <p>Takes less than a minute. You'll gain instant access to your customized workspace.</p>
            </div>

            <?php if ($error) : ?>
                <div class="alert alert--error" data-auto-dismiss style="margin-bottom: 18px; background: rgba(239, 68, 68, 0.15); border-left: 4px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: 6px;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php echo csrf_field(); ?>

            <div class="auth-form__grid">
                <div class="form-field">
                    <label for="full_name"><?= e(t('full_name')) ?></label>
                    <input id="full_name" name="full_name" type="text" autocomplete="name" required placeholder="e.g. Thabo Khumalo" value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="email"><?= e(t('email_address')) ?></label>
                    <input id="email" name="email" type="email" autocomplete="email" required placeholder="e.g. thabo@example.co.za" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="phone_number"><?= e(t('phone_number')) ?> *</label>
                    <input id="phone_number" name="phone_number" type="tel" autocomplete="tel" required placeholder="+27 82 123 4567" value="<?= e($_POST['phone_number'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label for="currency_code">Preferred Currency</label>
                    <select id="currency_code" name="currency_code" class="select-field">
                        <?= currency_select_options(normalize_currency((string) ($_POST['currency_code'] ?? 'ZAR'))) ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="password"><?= e(t('password')) ?></label>
                    <div class="password-input">
                        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="6" placeholder="At least 6 characters">
                        <button class="password-toggle" type="button" data-password-toggle="password" aria-pressed="false" aria-label="Show password" title="Show password"><span aria-hidden="true">◉</span> <span data-password-toggle-label>Show</span></button>
                    </div>
                </div>

                <div class="form-field">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-input">
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required minlength="6" placeholder="Re-type your password">
                        <button class="password-toggle" type="button" data-password-toggle="confirm_password" aria-pressed="false" aria-label="Show password" title="Show password"><span aria-hidden="true">◉</span> <span data-password-toggle-label>Show</span></button>
                    </div>
                </div>

                <button class="btn btn--primary" type="submit" style="margin-top: 10px;">
                    <?= e(t('register')) ?> <span>&rarr;</span>
                </button>
            </div>

            <div class="auth-form__footer" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 13px; color: var(--ink-soft);"><?= e(t('already_have_account')) ?> <a href="login.php" style="color: var(--marigold-deep); font-weight: 700; text-decoration: none;"><?= e(t('login')) ?></a></span>
                <a href="faq.php" style="font-size: 13px; color: var(--ink-soft); text-decoration: none;"><?= e(t('nav_faqs')) ?></a>
            </div>
        <div class="auth-divider" style="display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--ink-soft);font-size:12px;"><span style="height:1px;background:var(--line);flex:1"></span>or continue with</div><a class="btn btn--quiet" href="google-signin.php" style="width:100%;justify-content:center;border:1px solid var(--line);">Continue with Google</a></form>
    </section>
</main>
<script src="js/app.js?v=<?= asset_v('js/app.js') ?>" defer></script>
<script src="js/theme-and-share.js?v=<?= asset_v('js/theme-and-share.js') ?>" defer></script>
<script src="js/password-visibility.js?v=<?= asset_v('js/password-visibility.js') ?>" defer></script>
</body>
</html>
