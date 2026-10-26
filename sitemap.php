<?php
// Serves /sitemap.xml (see the rewrite rule in .htaccess). Lists every public page marked
// 'index' => true in includes/seo.php, so adding a page there adds it here automatically.
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/seo.php';

// sitemap.xml does not need a session cookie.
if (!headers_sent()) {
    header_remove('Set-Cookie');
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
}

$base = seo_base_url();
$xmlEscape = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach (seo_pages() as $file => $cfg) {
    if (empty($cfg['index'])) continue;
    $loc = $base . ($file === 'index.php' ? '/' : '/' . $file);
    $path = __DIR__ . '/' . $file;
    $lastmod = is_file($path) ? gmdate('Y-m-d', (int) filemtime($path)) : gmdate('Y-m-d');
    echo "  <url>\n";
    echo '    <loc>', $xmlEscape($loc), "</loc>\n";
    echo '    <lastmod>', $lastmod, "</lastmod>\n";
    echo '    <changefreq>', $xmlEscape((string) ($cfg['changefreq'] ?? 'monthly')), "</changefreq>\n";
    echo '    <priority>', $xmlEscape((string) ($cfg['priority'] ?? '0.5')), "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
