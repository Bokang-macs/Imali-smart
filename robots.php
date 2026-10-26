<?php
// Serves /robots.txt (see the rewrite rule in .htaccess). Uses the page list in includes/seo.php.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/seo.php';

// robots.txt does not need a session cookie.
if (!headers_sent()) {
    header_remove('Set-Cookie');
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
}

$base = seo_base_url();

echo "User-agent: *\n";
echo "Allow: /\n";
// Private app areas and server-only folders: nothing useful for search engines.
foreach (['api/', 'actions/', 'includes/', 'config/', 'database/', 'storage/', 'uploads/', 'statement.php'] as $path) {
    echo 'Disallow: /', $path, "\n";
}
echo "\nSitemap: ", $base, "/sitemap.xml\n";
