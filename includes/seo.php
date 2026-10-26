<?php
// SEO helper. Builds canonical URLs, robots rules, meta tags, social cards and JSON-LD for every page.
// Used by includes/header.php, index.php, login.php and register.php; also by robots.php and sitemap.php.
declare(strict_types=1);

/**
 * Public base URL of the site, without a trailing slash.
 * Uses APP_URL from .env when it is a real URL; otherwise works it out from the request
 * (so it also works at http://localhost/imali-smart/ on XAMPP).
 */
function seo_base_url(): string
{
    $configured = function_exists('app_config') ? (string) (app_config()['app_url'] ?? '') : '';
    if ($configured !== '' && preg_match('#^https?://[^\s"\']+$#i', $configured)) {
        return rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Only allow characters valid in a host name, to stop Host-header injection into canonical tags.
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
        $host = 'localhost';
    }
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $dir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/**
 * Per-page SEO settings. Pages not listed here are treated as private (noindex).
 * index: allow search engines to list the page.  priority/changefreq: used in sitemap.xml.
 */
function seo_pages(): array
{
    return [
        'index.php' => [
            'index' => true, 'priority' => '1.0', 'changefreq' => 'weekly', 'canonical' => '',
            'description' => 'Imali Smart is a free money management workspace for South Africans: budgets, savings, debt payoff, steady-pay planning and an AI assistant in 11 languages.',
        ],
        'faq.php' => [
            'index' => true, 'priority' => '0.6', 'changefreq' => 'monthly',
            'description' => 'Answers to common questions about Imali Smart: steady pay, debt snowball, data protection and managing your records.',
        ],
        'help.php' => [
            'index' => true, 'priority' => '0.6', 'changefreq' => 'monthly',
            'description' => 'Imali Smart help centre with how-to guidance and South African debt and consumer support contacts.',
        ],
        'contact.php' => [
            'index' => true, 'priority' => '0.5', 'changefreq' => 'yearly',
            'description' => 'Contact the Imali Smart team for support, feedback or partnership questions.',
        ],
        'register.php' => [
            'index' => true, 'priority' => '0.7', 'changefreq' => 'yearly',
            'description' => 'Create a free Imali Smart account to track money and plan budgets.',
        ],
        'privacy-policy.php' => [
            'index' => true, 'priority' => '0.3', 'changefreq' => 'yearly',
            'description' => 'How Imali Smart collects, uses and protects your personal information in line with South Africa\'s POPIA.',
        ],
        'terms.php' => [
            'index' => true, 'priority' => '0.3', 'changefreq' => 'yearly',
            'description' => 'The terms of service for using Imali Smart.',
        ],
        // Public but pointless in search results: crawlers may follow links but must not list them.
        'login.php' => [
            'index' => false, 'description' => 'Sign in to your Imali Smart account.',
        ],
    ];
}

/** File name of the page being served, e.g. "faq.php". */
function seo_current_page(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'index.php'));
}

/**
 * Builds everything the <head> needs.
 *
 * Optional overrides (set before including header.php):
 *   $seo_description  string  custom meta description
 *   $seo_noindex      bool    force noindex
 *   $seo_image        string  absolute URL of a social image
 *   $seo_jsonld       array   extra JSON-LD nodes
 */
function seo_build(string $pageTitle, ?bool $isLoggedIn = null): array
{
    $pages = seo_pages();
    $file = seo_current_page();
    $cfg = $pages[$file] ?? null;
    $base = seo_base_url();
    $siteName = 'Imali Smart';

    // Page titles in the app already end with "· Imali Smart" in places; never repeat the brand.
    $clean = trim(preg_replace('/\s*[·|\-–—]\s*Imali Smart\s*$/u', '', $pageTitle) ?? $pageTitle);
    $title = ($clean === '' || strcasecmp($clean, $siteName) === 0) ? $siteName : $clean . ' · ' . $siteName;

    $description = (string) ($GLOBALS['seo_description'] ?? ($cfg['description'] ?? 'Imali Smart: a private money management workspace for South Africans.'));
    $description = trim(mb_substr(preg_replace('/\s+/', ' ', $description) ?? $description, 0, 300));

    // Private if: not on the public list, flagged noindex, or a signed-in user is viewing it.
    $indexable = $cfg !== null && !empty($cfg['index']) && empty($GLOBALS['seo_noindex']) && !$isLoggedIn;

    // Canonical: the clean URL, no query string (so ?lang= and ?year= do not create duplicate pages).
    $path = $file === 'index.php' ? '/' : '/' . $file;
    $canonical = $base . $path;

    // public/og-image.jpg does not exist in the project, so use the logo that does.
    $image = (string) ($GLOBALS['seo_image'] ?? ($base . '/assets/logo/imali-logo.png'));

    $graph = [
        [
            '@type' => 'Organization',
            '@id' => $base . '/#organization',
            'name' => $siteName,
            'url' => $base . '/',
            'logo' => $base . '/assets/logo/imali-logo.png',
            'founder' => ['@type' => 'Organization', 'name' => 'Code Execution'],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $base . '/#website',
            'url' => $base . '/',
            'name' => $siteName,
            'inLanguage' => 'en-ZA',
            'publisher' => ['@id' => $base . '/#organization'],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $canonical . '#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $base . '/#website'],
        ],
    ];

    if ($file === 'index.php') {
        $graph[] = [
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'url' => $base . '/',
            'applicationCategory' => 'FinanceApplication',
            'operatingSystem' => 'Any (web browser)',
            'description' => $description,
            'creator' => ['@id' => $base . '/#organization'],
            // Intentionally no aggregateRating: ratings markup must reflect real, visible user reviews.
        ];
    } elseif ($indexable) {
        $graph[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $clean !== '' ? $clean : $siteName, 'item' => $canonical],
            ],
        ];
    }

    foreach ((array) ($GLOBALS['seo_jsonld'] ?? []) as $extra) {
        if (is_array($extra)) $graph[] = $extra;
    }

    return [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'robots' => $indexable ? 'index,follow,max-image-preview:large' : 'noindex,nofollow',
        'indexable' => $indexable,
        'image' => $image,
        'site_name' => $siteName,
        'locale' => str_replace('-', '_', (function_exists('current_lang') && current_lang() !== 'en') ? current_lang() : 'en_ZA'),
        'jsonld' => ['@context' => 'https://schema.org', '@graph' => $graph],
    ];
}

/**
 * Prints title, description, robots, canonical, Open Graph, Twitter Card and JSON-LD.
 * Also sends an X-Robots-Tag header for private pages (works even for non-HTML crawlers).
 */
function seo_render_head(string $pageTitle, ?bool $isLoggedIn = null): void
{
    $s = seo_build($pageTitle, $isLoggedIn);
    if (!$s['indexable'] && !headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow', true);
    }
    $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<title>', $h($s['title']), "</title>\n";
    echo '    <meta name="description" content="', $h($s['description']), "\">\n";
    echo '    <meta name="robots" content="', $h($s['robots']), "\">\n";
    echo '    <meta name="author" content="Imali Smart - Designed and developed by Code Execution">', "\n";
    if ($s['indexable']) {
        echo '    <link rel="canonical" href="', $h($s['canonical']), "\">\n";
    }
    echo '    <meta property="og:type" content="website">', "\n";
    echo '    <meta property="og:site_name" content="', $h($s['site_name']), "\">\n";
    echo '    <meta property="og:locale" content="', $h($s['locale']), "\">\n";
    echo '    <meta property="og:title" content="', $h($s['title']), "\">\n";
    echo '    <meta property="og:description" content="', $h($s['description']), "\">\n";
    echo '    <meta property="og:url" content="', $h($s['canonical']), "\">\n";
    echo '    <meta property="og:image" content="', $h($s['image']), "\">\n";
    echo '    <meta property="og:image:alt" content="Imali Smart logo">', "\n";
    echo '    <meta name="twitter:card" content="summary">', "\n";
    echo '    <meta name="twitter:title" content="', $h($s['title']), "\">\n";
    echo '    <meta name="twitter:description" content="', $h($s['description']), "\">\n";
    echo '    <meta name="twitter:image" content="', $h($s['image']), "\">\n";
    if ($s['indexable']) {
        $json = json_encode($s['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        echo '    <script type="application/ld+json">', $json, "</script>\n";
    }
}
