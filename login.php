<?php
// Login page.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/seo.php';

if (is_authenticated()) {
    redirect_to('dashboard.php');
}

$error = null;
$notice = flash('auth_notice');
$redirectTarget = trim((string) ($_GET['redirect'] ?? $_POST['redirect_to'] ?? 'dashboard.php'));

// Prevent malformed and external redirects while preserving internal page/query links.
$redirectParts = parse_url($redirectTarget);
$redirectPath = (string) ($redirectParts['path'] ?? '');
if ($redirectPath === '' || isset($redirectParts['scheme']) || isset($redirectParts['host']) || (!str_starts_with($redirectPath, '/') && !str_ends_with($redirectPath, '.php'))) {
    $redirectTarget = 'dashboard.php';
}

if (request_method_is('POST')) {
    require_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email address and password.';
    } elseif (!database_ready()) {
        $error = 'The workspace service is temporarily unavailable. Please try again shortly.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, password_hash, full_name FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['email' => $email]);
            $account = $stmt->fetch();

            if (!$account || !password_verify($password, (string) $account['password_hash'])) {
                $error = 'Incorrect email or password. Please verify and try again.';
            } else {
                login_user((int) $account['id']);
                if ($rememberMe) {
                    issue_remember_me_token((int) $account['id']);
                }
                flash('welcome', 'Welcome back, ' . strtok((string) $account['full_name'], ' ') . '!');
                redirect_to($redirectTarget);
            }
        } catch (Throwable $e) {
            $error = 'An error occurred while signing in. Please try again.';
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
    <?php seo_render_head(t('login')); ?>
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
            <span class="eyebrow" style="color: var(--marigold, #f59e0b); font-weight: 800; letter-spacing: 0.1em;">AUTHENTICATION GATEWAY</span>
            <h1 style="color: #ffffff; font-size: 2.1rem; font-weight: 800; line-height: 1.25; margin: 14px 0; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">Sign in to unlock your dashboard.</h1>
            <p style="color: #e2e8f0; font-size: 1.05rem; line-height: 1.6; text-shadow: 0 1px 2px rgba(0,0,0,0.3);"><?= e(t('app_slogan')) ?> Access your personal ledger, debt snowball, and AI money coach.</p>
        </div>
        
        <div style="background: rgba(15, 23, 42, 0.75); border: 1.5px solid rgba(255, 255, 255, 0.2); border-radius: 14px; padding: 18px; margin-top: auto; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.45); backdrop-filter: blur(8px);">
            <div style="font-weight: 800; color: #ffffff; margin-bottom: 8px; font-size: 13.5px; display: flex; align-items: center; gap: 6px;">Privacy &amp; Data Security First</div>
            <p style="font-size: 12.5px; color: #cbd5e1; margin: 0; line-height: 1.5;">Your financial records are encrypted and protected in accordance with POPIA regulations. Dedicated to individual privacy.</p>
        </div>
    </aside>

    <section class="auth-panel">
        <div style="width: 100%; max-width: 490px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--line); padding-bottom: 14px; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px;">
                <a href="login.php" class="btn btn--small btn--accent" style="text-decoration: none;"><?= e(t('login')) ?></a>
                <a href="register.php" class="btn btn--small btn--quiet" style="text-decoration: none;"><?= e(t('register')) ?></a>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <!-- Theme Toggle Button -->
                <button class="theme-quick-btn" type="button" data-theme-toggle aria-label="<?= e(t('toggle_theme')) ?>" title="<?= e(t('toggle_theme')) ?>" style="width: 32px; height: 32px; border-radius: 50%; background: var(--paper-deep); border: 1px solid var(--line); display: grid; place-items: center; cursor: pointer; color: var(--ink);">
                    <span class="theme-icon">◐</span>
                </button>

                <a href="index.php" style="font-size: 12px; color: var(--ink-soft); text-decoration: none; font-weight: 600;">← <?= e(t('nav_dashboard')) ?></a>
            </div>
        </div>

        <form class="auth-form" method="post" action="login.php" data-validate autocomplete="on">
            <div class="auth-form__heading">
                <div class="ledger-line"></div>
                <h2><?= e(t('welcome_back')) ?></h2>
                <p><?= e(t('sign_in_to_workspace')) ?></p>
            </div>

            <?php if ($notice) : ?>
                <div class="alert alert--info" style="margin-bottom: 18px; background: rgba(59, 130, 246, 0.15); border-left: 4px solid #3b82f6; color: var(--ink); padding: 12px 16px; border-radius: 6px;">
                    <strong>Access Notice:</strong> <?= e($notice) ?>
                </div>
            <?php endif; ?>

            <?php if ($error) : ?>
                <div class="alert alert--error" data-auto-dismiss style="margin-bottom: 18px; background: rgba(239, 68, 68, 0.15); border-left: 4px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: 6px;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <input type="hidden" name="redirect_to" value="<?= e($redirectTarget) ?>">
            <?php echo csrf_field(); ?>

            <div class="auth-form__grid">
                <div class="form-field">
                    <label for="email"><?= e(t('email_address')) ?></label>
                    <input id="email" name="email" type="email" autocomplete="username" required placeholder="e.g. name@example.co.za" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-field">
                    <label for="password"><?= e(t('password')) ?></label>
                    <div class="password-input">
                        <input id="password" name="password" type="password" autocomplete="current-password" required minlength="4" placeholder="Enter your password">
                        <button class="password-toggle" type="button" data-password-toggle="password" aria-pressed="false" aria-label="Show password" title="Show password"><span aria-hidden="true">◉</span> <span data-password-toggle-label>Show</span></button>
                    </div>
                </div>

                <button class="btn btn--primary" type="submit" style="margin-top: 8px;">
                    <?= e(t('login')) ?> <span>&rarr;</span>
                </button>

                <div class="auth-help-row">
                    <span><a href="forgot-password.php">Forgot password?</a> · <a href="contact.php?subject=Login%20support">Contact support</a></span>
                </div>

                </div>
            </div>

            <div class="auth-form__footer" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 13px; color: var(--ink-soft);"><?= e(t('dont_have_account')) ?> <a href="register.php" style="color: var(--marigold-deep); font-weight: 700; text-decoration: none;"><?= e(t('register_here')) ?></a></span>
                <a href="contact.php" style="font-size: 13px; color: var(--ink-soft); text-decoration: none;"><?= e(t('nav_help')) ?></a>
            </div>
        <div class="auth-divider" style="display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--ink-soft);font-size:12px;"><span style="height:1px;background:var(--line);flex:1"></span>or continue with</div><a class="btn btn--quiet" href="google-signin.php" style="width:100%;justify-content:center;border:1px solid var(--line);">Continue with Google</a></form>
    </section>
</main>

<script src="js/app.js?v=<?= asset_v('js/app.js') ?>" defer></script>
<script src="js/theme-and-share.js?v=<?= asset_v('js/theme-and-share.js') ?>" defer></script>
<script src="js/password-visibility.js?v=<?= asset_v('js/password-visibility.js') ?>" defer></script>
</body>
</html>
