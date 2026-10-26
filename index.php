<?php
// Public home page. Also where a visitor picks the language for the whole app.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';

$landingUser = is_authenticated() ? current_user() : null;
$page_title = 'Smarter Money & Cash Flow Intelligence';
$app_url = app_config()['app_url'] ?: 'http://localhost:3000';
$currentLang = current_lang();
$isRtl = is_rtl();
$languages = available_languages();
?>
<!doctype html>
<html lang="<?= e($currentLang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>" data-theme="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#18221f" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#fbfaf6" media="(prefers-color-scheme: light)">
    
    <!-- SEO (includes/seo.php) -->
    <?php seo_render_head((string) $page_title); ?>
    <meta name="keywords" content="south africa budget app, cash flow, debt payoff, student allowance, financial education, ai assistant">

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/png" href="assets/logo/imali-logo.png">
    <link rel="apple-touch-icon" href="assets/logo/imali-logo.png">
    <link rel="manifest" href="manifest.webmanifest">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,900&family=Noto+Sans+Arabic:wght@400..800&family=Noto+Sans+Devanagari:wght@400..800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/variables.css?v=<?= asset_v('css/variables.css') ?>">
    <link rel="stylesheet" href="css/global.css?v=<?= asset_v('css/global.css') ?>">
    <link rel="stylesheet" href="css/layout.css?v=<?= asset_v('css/layout.css') ?>">
    <link rel="stylesheet" href="css/components.css?v=<?= asset_v('css/components.css') ?>">
    <link rel="stylesheet" href="css/responsive.css?v=<?= asset_v('css/responsive.css') ?>">
    <link rel="stylesheet" href="css/landing.css?v=<?= asset_v('css/landing.css') ?>">
    
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
<main class="landing">
    <!-- Navigation Header -->
    <header class="landing-nav" data-tour="navigation">
        <a class="public-brand" href="index.php" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
            <img src="assets/logo/imali-logo.png" alt="Imali Smart" style="height: 48px; width: auto; object-fit: contain; border-radius: 8px;">
            <span style="font-family: var(--font-display); font-size: 1.3rem; font-weight: 800; color: var(--ink); letter-spacing: -0.02em;">Imali Smart</span>
        </a>

        <button class="icon-button public-menu-button" type="button" data-public-menu aria-label="Open menu" aria-expanded="false" aria-controls="public-nav">
            <span class="hamburger" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>

        <nav id="public-nav" data-public-nav aria-label="Main navigation">
            <a href="contact.php"><?= e(t('nav_contact')) ?></a>

            <!-- Language Selector Dropdown (Clear, High-Contrast Categorized Options) -->
            <div style="position: relative; display: inline-flex; align-items: center; background: var(--paper-deep); border: 1.5px solid var(--line); border-radius: 10px; padding: 4px 10px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); transition: all 0.2s ease;">
                <span style="font-size: 14px; margin-right: 4px; flex-shrink: 0;" aria-hidden="true">Language</span>
                <select class="lang-select-native" onchange="window.location.href=this.value;" style="background: transparent; border: none; font-size: 13px; font-weight: 700; color: var(--ink); cursor: pointer; padding: 4px 22px 4px 2px; outline: none; appearance: none; -webkit-appearance: none; -moz-appearance: none;" aria-label="<?= e(t('select_language')) ?>">
                    <optgroup label="Languages">
                        <?php
                        $saDefinitions = [
                            'en' => ['native' => 'English', 'desc' => 'English (South Africa)'],
                            'zu' => ['native' => 'isiZulu', 'desc' => 'Zulu'],
                            'xh' => ['native' => 'isiXhosa', 'desc' => 'Xhosa'],
                            'af' => ['native' => 'Afrikaans', 'desc' => 'Afrikaans'],
                            'nso' => ['native' => 'Sepedi', 'desc' => 'Sesotho sa Leboa  ·  Northern Sotho'],
                            'st' => ['native' => 'Sesotho', 'desc' => 'Southern Sotho'],
                            'tn' => ['native' => 'Setswana', 'desc' => 'Tswana'],
                            'ts' => ['native' => 'Xitsonga', 'desc' => 'Tsonga'],
                            've' => ['native' => 'Tshivenḓa', 'desc' => 'Venda'],
                            'ss' => ['native' => 'siSwati', 'desc' => 'Swati'],
                            'nr' => ['native' => 'isiNdebele', 'desc' => 'Ndebele'],
                        ];
                        foreach ($saDefinitions as $code => $meta) :
                            $isSelected = ($currentLang === $code);
                        ?>
                            <option value="<?= e(language_switch_url($code)) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= e($meta['native']) ?>  —  <?= e($meta['desc']) ?><?= $isSelected ? '  ✓ Active' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="International">
                        <?php
                        $intlDefinitions = [
                            'fr' => ['native' => 'Français', 'desc' => 'French'],
                            'pt' => ['native' => 'Português', 'desc' => 'Portuguese'],
                            'es' => ['native' => 'Español', 'desc' => 'Spanish'],
                            'ar' => ['native' => 'العربية', 'desc' => 'Arabic'],
                            'hi' => ['native' => 'हिन्दी', 'desc' => 'Hindi'],
                            'zh-Hans' => ['native' => '简体中文', 'desc' => 'Simplified Chinese'],
                            'zh-Hant' => ['native' => '繁體中文', 'desc' => 'Traditional Chinese'],
                        ];
                        foreach ($intlDefinitions as $code => $meta) :
                            $isSelected = ($currentLang === $code);
                        ?>
                            <option value="<?= e(language_switch_url($code)) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= e($meta['native']) ?>  —  <?= e($meta['desc']) ?><?= $isSelected ? '  ✓ Active' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <span class="lang-dropdown-arrow" aria-hidden="true" style="position: absolute; right: 10px; pointer-events: none; color: var(--ink-soft); font-size: 10px; line-height: 1;">
                    ▼
                </span>
            </div>
            
            <!-- Theme Toggle Button -->
            <button class="theme-quick-btn" type="button" data-theme-toggle aria-label="<?= e(t('toggle_theme')) ?>" title="<?= e(t('toggle_theme')) ?>">
                <span class="theme-icon">☀</span>
                <span class="public-nav__label"><?= e(t('toggle_theme')) ?></span>
            </button>

            <div class="public-nav-actions">
            <?php if ($landingUser) : ?>
                <a class="btn btn--accent btn--small" href="dashboard.php" style="width: 100%;">
                    <?= e(t('nav_dashboard')) ?> (<?= e((string) strtok((string) $landingUser['full_name'], ' ')) ?>) <span>→</span>
                </a>
            <?php else : ?>
                <a class="btn btn--quiet btn--small" href="login.php"><?= e(t('login')) ?></a>
                <a class="btn btn--primary btn--small" href="register.php"><?= e(t('register')) ?></a>
            <?php endif; ?>
            </div>
        </nav>
    </header>


    <!-- Creative Hero Section -->
    <section class="landing-hero">
        <div class="landing-hero__copy" data-tour="money-picture">
            <div class="ledger-line"></div>
            <span class="eyebrow" style="color: var(--marigold-deep); font-weight: 800; letter-spacing: 0.12em;">
                <?= e(t('hero_platform_eyebrow')) ?>
            </span>
            <h1>
                <?= e(t('hero_title_line1')) ?><br>
                <em><?= e(t('hero_title_line2')) ?></em>
            </h1>
            <p>
                <?= e(t('hero_description')) ?>
            </p>

            <div class="landing-hero__actions">
                <?php if ($landingUser) : ?>
                    <a class="btn btn--accent" href="dashboard.php" style="font-size: 15px; padding: 12px 24px;">
                        <?= e(t('open_dashboard')) ?> <span>→</span>
                    </a>
                <?php else : ?>
                    <a class="btn btn--accent" href="register.php" style="font-size: 15px; padding: 12px 24px;">
                        <?= e(t('start_free_workspace')) ?> <span>→</span>
                    </a>
                    <a class="btn btn--quiet" href="login.php">
                        <?= e(t('login')) ?>
                    </a>
                <?php endif; ?>
                <a class="text-link" href="#simulator"><?= e(t('explore_wealth_strategies')) ?> ↓</a><button type="button" class="text-link tour-link" data-start-tour>Take a guided tour</button>
            </div>

            <div class="landing-trust" data-tour="privacy">
                <span class="trust-mark">✓</span>
                <span><?= e(t('trust_banner')) ?></span>
            </div>
        </div>

        <!-- Interactive Hero Visualizer (Target: main > section:nth-of-type(1) > div:nth-of-type(2) > div:nth-of-type(1)) -->
        <div class="landing-hero__visual" data-tour="confidence">
            <div class="brand-showcase-card">
                <span class="brand-showcase-badge">✦ <?= e(t('official_platform')) ?></span>
                
                <!-- 3D Brand Badge Showcase -->
                <div style="text-align: center; margin-bottom: 12px;">
                    <img src="assets/logo/imali-logo.png" alt="Imali Smart Official Logo" style="width: 140px; height: 140px; object-fit: contain; filter: drop-shadow(0 14px 28px rgba(0,0,0,0.22)); border-radius: 16px;">
                    <h3 style="margin: 10px 0 2px; font-family: var(--font-display); font-size: 1.4rem; color: var(--ink);">
                        <?= e(t('ecosystem_title')) ?>
                    </h3>
                    <p style="margin: 0; font-size: 12px; color: var(--ink-soft);">
                        <?= e(t('ecosystem_subtitle')) ?>
                    </p>
                </div>

                <!-- 4-Pillar Interactive Switcher -->
                <div class="pillar-nav" role="tablist">
                    <button type="button" class="pillar-btn is-active" data-pillar="personal" role="tab" aria-selected="true">
                        <span class="pillar-icon"></span>
                        <span><?= e(t('pillar_personal')) ?></span>
                    </button>
                    <button type="button" class="pillar-btn" data-pillar="education" role="tab" aria-selected="false">
                        <span class="pillar-icon"></span>
                        <span><?= e(t('pillar_academy')) ?></span>
                    </button>
                    <button type="button" class="pillar-btn" data-pillar="ai" role="tab" aria-selected="false">
                        <span class="pillar-icon">☀</span>
                        <span><?= e(t('pillar_ai')) ?></span>
                    </button>
                </div>

                <!-- Interactive Dynamic Feature Preview (Zero Balances) -->
                <div class="pillar-content-box" id="pillar-content">
                    <div class="pillar-feature-title" id="pillar-title">
                        <span></span> Personal Finance &amp; Everyday Money
                    </div>
                    <p class="pillar-feature-desc" id="pillar-desc">
                        Turn irregular income into a steady plan, stay ahead of essentials, and build an emergency cushion one step at a time.
                    </p>
                    <div class="pillar-tag-row" id="pillar-tags">
                        <span class="pillar-tag">✓ 50/30/20 Rule</span>
                        <span class="pillar-tag">✓ Debt Snowball Queue</span>
                        <span class="pillar-tag">✓ Emergency Cushions</span>
                        <span class="pillar-tag">✓ Spending plan</span>
                    </div>
                </div>

                <!-- Card Footer Action -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--line); font-size: 12px; color: var(--ink-soft);">
                    <span><strong>Interactive Preview</strong>  ·  No setup fee</span>
                    <a href="register.php" class="btn btn--accent btn--small" style="padding: 6px 14px; font-size: 12px; text-decoration: none;">
                        Get Started →
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Creative Interactive Financial Strategy Simulator (No balances) -->
    <section class="simulator-section" id="simulator">
        <div class="sim-wrapper">
            <div style="text-align: center; margin-bottom: 24px;">
                <span class="eyebrow" style="color: var(--marigold-deep);">FINANCIAL STRATEGY LAB</span>
                <h2 style="font-size: 2.2rem; font-family: var(--font-display); margin: 8px 0 10px 0; color: var(--ink);">
                    Interactive Wealth Principles Explorer
                </h2>
                <p style="color: var(--ink-soft); max-width: 650px; margin: 0 auto; font-size: 14px; line-height: 1.6;">
                    Select a core financial discipline below to see how Imali Smart structures your cash flow, reduces financial stress, and builds generational independence:
                </p>
            </div>

            <div class="sim-grid">
                <!-- Strategy Selection List -->
                <div class="sim-btn-group">
                    <div class="sim-card-choice is-active" data-strategy="blueprint">
                        <strong>50 / 30 / 20 Prosperity Blueprint</strong>
                        <small>Balanced allocation across Needs, Wants, and Wealth Accumulation.</small>
                    </div>
                    <div class="sim-card-choice" data-strategy="snowball">
                        <strong>The Debt Snowball Momentum</strong>
                        <small>Systematic payoff of credit cards, store accounts, and loans.</small>
                    </div>
                    <div class="sim-card-choice" data-strategy="till">
                        <strong>Daily Till &amp; Shift Reconciliation</strong>
                        <small>Build a steady spending plan for irregular income.</small>
                    </div>
                    <div class="sim-card-choice" data-strategy="cushion">
                        <strong>3-Tier Emergency Fortress</strong>
                        <small>Step-by-step security cushion against life disruptions.</small>
                    </div>
                </div>

                <!-- Strategy Visual Demonstration Box -->
                <div class="sim-preview-box">
                    <h3 class="sim-preview-heading" id="sim-heading">50 / 30 / 20 Prosperity Blueprint</h3>
                    <p class="sim-preview-body" id="sim-body">
                        The gold standard of balanced budgeting. Automatically partitions your earnings so your essentials are always covered while your wealth grows steadily in the background.
                    </p>
                    <ol class="sim-steps-list" id="sim-steps">
                        <li><strong>50% Essential Foundations:</strong> Shelter, groceries, utilities, and transport.</li>
                        <li><strong>30% Lifestyle &amp; Culture:</strong> Personal development, family, and entertainment.</li>
                        <li><strong>20% Wealth Building:</strong> Debt acceleration, emergency funds, and future assets.</li>
                    </ol>
                    <div style="margin-top: 20px;">
                        <a href="register.php" class="btn btn--primary btn--small" style="width: 100%; justify-content: center;">
                            Apply This Strategy in Your Workspace <span>→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Three Purpose-Built Financial Identity Portals -->
    <section class="persona-section">
        <div class="section-intro">
            <span class="eyebrow" style="color: var(--marigold-deep);">TAILORED WORKSPACES</span>
            <h2>One System. Built for Every Reality.</h2>
            <p>
                You don't need three separate apps. Imali Smart provides dedicated modes for households, entrepreneurs, and students.
            </p>
        </div>

        <div class="persona-grid">
            <!-- 1. Personal Finance User -->
            <div class="persona-card">
                <div class="persona-card__icon"></div>
                <h3>Personal Finance &amp; Family</h3>
                <p>Gain calm command over living costs, emergency reserves, and credit commitments without shame.</p>
                <ul>
                    <li>Envelope budgets with real-time limits</li>
                    <li>Debt Snowball payoff priority queue</li>
                    <li>Automatic 3-tier emergency buffer goals</li>
                    <li>Private statement CSV spreadsheet imports</li>
                    <li>Verified financial profile social sharing</li>
                </ul>
                <div class="persona-card__btn">
                    <a href="register.php" class="btn btn--quiet btn--small" style="width: 100%; justify-content: center;">
                        Start Personal Mode <span>→</span>
                    </a>
                </div>
            </div>

            <!-- 3. Student & Financial Learner -->
            <div class="persona-card">
                <div class="persona-card__icon"></div>
                <h3>Student &amp; Young Adult</h3>
                <p>Learn genuine money discipline, divide monthly allowances prudently, and build resilience early.</p>
                <ul>
                    <li>NSFAS &amp; bursary allowance allocator</li>
                    <li>Campus tuition &amp; textbook milestone savings</li>
                    <li>Ask Imali (AI) financial advisory coach</li>
                </ul>
                <div class="persona-card__btn">
                    <a href="register.php" class="btn btn--quiet btn--small" style="width: 100%; justify-content: center;">
                        Start Student Mode <span>→</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Core 3-Column Summary Strip -->
    <section class="landing-strip">
        <div>
            <span class="eyebrow">ONE HOME FOR</span>
            <strong>Personal Money</strong>
            <span>Income, expenses, debt payoff snowball, emergency cushions, and daily envelope budgets.</span>
        </div>
        <div>
            <span class="eyebrow">A CLEAR VIEW OF</span>
            <strong>Your personal money plan</strong>
            <span>Income, essentials, savings and debt payoff in one calm view.</span>
        </div>
        <div>
            <span class="eyebrow">A PLACE TO</span>
            <strong>Understand &amp; Protect</strong>
            <span>Personal privacy controls, local language support, and a clear record of your money.</span>
        </div>
    </section>

    <!-- Site Footer with Code Execution Credit -->
    <footer class="site-footer">
        <div>
            <div class="footer-brand" style="display: flex; align-items: center; gap: 10px;">
                <img src="assets/logo/imali-logo.png" alt="Imali Smart" style="height: 36px; width: auto; object-fit: contain; border-radius: 6px;">
                <strong style="font-size: 1.1rem; color: var(--ink);">Imali Smart</strong>
            </div>
            <p>Smart personal money clarity built for individuals and learners across South Africa.</p>
        </div>
        <div class="footer-links">
            <a href="contact.php">Contact Support</a>
            <a href="privacy-policy.php">Privacy &amp; POPIA</a>
            <a href="terms.php">Terms of Service</a>
            <a href="faq.php">FAQs</a>
            <a href="help.php">Help Center</a>
            <?php if ($landingUser) : ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="settings.php">Settings</a>
            <?php else : ?>
                <a href="login.php">Sign In</a>
                <a href="register.php">Create Account</a>
            <?php endif; ?>
        </div>
        <div class="footer-meta">
            <span>© <?= date('Y') ?> Imali Smart  ·  Financial Intelligence Workspace</span>
            <span style="color: var(--color-accent, #e8a33a); font-weight: 700;">Designed and developed by Code Execution</span>
        </div>
    </footer>
</main>

<!-- Interactive Showcase and Strategy Scripts -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Pillar Showcase Switcher
    const pillarButtons = document.querySelectorAll('.pillar-btn');
    const pTitle = document.getElementById('pillar-title');
    const pDesc = document.getElementById('pillar-desc');
    const pTags = document.getElementById('pillar-tags');

    const pillarData = {
        personal: {
            title: 'Personal Finance & Wealth Architecture',
            desc: 'Allocate monthly earnings with precision. Eliminate high-interest store debt with the systematic snowball method and grow automated multi-tier emergency reserves.',
            tags: ['✓ 50/30/20 Rule', '✓ Debt Snowball Queue', '✓ Emergency Cushions', '✓ Category Envelopes']
        },
        education: {
            title: 'Financial Academy & Fraud Radar',
            desc: 'Empower yourself with practical money knowledge. Verify suspicious SMSs and WhatsApp scams with real-time scam detection and reference SARS tax brackets.',
            tags: ['✓ Interactive Quizzes', '✓ Scam Radar Filter', '✓ SARS Tax Calculators', '✓ Bursary Management']
        },
        ai: {
            title: 'Ask Imali: 24/7 Intelligent Financial Coach',
            desc: 'Receive immediate, context-aware financial guidance. Clarify debt priorities, balance budget envelopes, and receive calendar sync reminders.',
            tags: ['✓ Contextual Advice', '✓ Calendar Sync Alerts', '✓ Plain-English Answers', '✓ Zero Jargon']
        }
    };

    pillarButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const pillarKey = btn.dataset.pillar;
            if (!pillarKey || !pillarData[pillarKey]) return;

            pillarButtons.forEach(b => {
                b.classList.remove('is-active');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('is-active');
            btn.setAttribute('aria-selected', 'true');

            const item = pillarData[pillarKey];
            pTitle.innerHTML = item.title;
            pDesc.textContent = item.desc;
            pTags.innerHTML = item.tags.map(t => `<span class="pillar-tag">${t}</span>`).join(' ');
        });
    });

    // 2. Financial Strategy Simulator Switcher
    const strategyChoices = document.querySelectorAll('.sim-card-choice');
    const simHeading = document.getElementById('sim-heading');
    const simBody = document.getElementById('sim-body');
    const simSteps = document.getElementById('sim-steps');

    const strategyData = {
        blueprint: {
            heading: '50 / 30 / 20 Prosperity Blueprint',
            body: 'The gold standard of balanced budgeting. Automatically partitions your earnings so your essentials are always covered while your wealth grows steadily in the background.',
            steps: [
                '<strong>50% Essential Foundations:</strong> Shelter, groceries, utilities, and transport.',
                '<strong>30% Lifestyle & Culture:</strong> Personal development, family, and entertainment.',
                '<strong>20% Wealth Building:</strong> Debt acceleration, emergency funds, and future assets.'
            ]
        },
        snowball: {
            heading: 'The Debt Snowball Momentum Engine',
            body: 'A proven psychological approach that builds rapid victory momentum by attacking the smallest balance first while paying minimums on everything else.',
            steps: [
                '<strong>List & Rank:</strong> Order accounts from smallest balance to largest regardless of interest rate.',
                '<strong>Attack Smallest:</strong> Direct every spare rand toward eliminating the #1 target.',
                '<strong>Snowball Roll:</strong> Once cleared, roll its full repayment payment into target #2.'
            ]
        },
        till: {
            heading: 'Everyday spending check-in',
            body: 'The foundational discipline of successful retail. Ensures that physical cash in the drawer matches recorded sales and merchant card receipts every single day.',
            steps: [
                '<strong>Count Physical Cash:</strong> Total notes, coins, and register float at closing.',
                '<strong>Reconcile Card Swipes:</strong> Match POS terminal batch reports against register entries.',
                '<strong>Spot Discrepancies Instantly:</strong> Catch cashier shortages and log customer book credits.'
            ]
        },
        cushion: {
            heading: 'The 3-Tier Emergency Fortress',
            body: 'Eliminates reliance on predatory payday lenders and micro-loan sharks by erecting systematic, milestone-driven financial buffers.',
            steps: [
                '<strong>Tier 1 - Starter Cushion:</strong> Initial safety buffer preserving liquidity for unexpected minor emergencies.',
                '<strong>Tier 2 - Operational Month:</strong> 1 full month of essential living costs banked.',
                '<strong>Tier 3 - Full Independence:</strong> 3 to 6 months of living expenses safely preserved.'
            ]
        }
    };

    strategyChoices.forEach(card => {
        card.addEventListener('click', () => {
            const key = card.dataset.strategy;
            if (!key || !strategyData[key]) return;

            strategyChoices.forEach(c => c.classList.remove('is-active'));
            card.classList.add('is-active');

            const item = strategyData[key];
            simHeading.textContent = item.heading;
            simBody.textContent = item.body;
            simSteps.innerHTML = item.steps.map(s => `<li>${s}</li>`).join('');
        });
    });
});
</script>

<script src="js/theme-and-share.js?v=<?= asset_v('js/theme-and-share.js') ?>" defer></script>
<script src="js/app.js?v=<?= asset_v('js/app.js') ?>" defer></script>
<script src="js/experience.js?v=<?= asset_v('js/experience.js') ?>" defer></script>
</body>
</html>
