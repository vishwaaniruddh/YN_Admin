<?php
// admin/api/sitemap.php
// Dynamic XML Sitemap Generator for Google Search Console & Indexing
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = 'https://yosshitaneha.com';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

/**
 * Helper to output url tag
 */
function echo_sitemap_url($url, $lastmod = null, $changefreq = 'weekly', $priority = '0.8') {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
    if ($lastmod) {
        $formattedDate = date('c', strtotime($lastmod));
        echo '    <lastmod>' . $formattedDate . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
    echo '    <priority>' . $priority . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// 1. Static Core Pages
echo_sitemap_url($baseUrl . '/', date('Y-m-d'), 'daily', '1.0');
echo_sitemap_url($baseUrl . '/collections', date('Y-m-d'), 'daily', '0.9');
echo_sitemap_url($baseUrl . '/blogs', date('Y-m-d'), 'weekly', '0.8');
echo_sitemap_url($baseUrl . '/about', date('Y-m-d'), 'monthly', '0.7');
echo_sitemap_url($baseUrl . '/contact', date('Y-m-d'), 'monthly', '0.7');
echo_sitemap_url($baseUrl . '/shipping-policy', date('Y-m-d'), 'monthly', '0.5');

// 2. Dynamic Categories
try {
    $stmtCat = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC");
    $allCats = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
    $categoriesTree = build_nested_category_tree($allCats);

    function traverse_sitemap_categories($tree, $baseUrl) {
        foreach ($tree as $cat) {
            if (!empty($cat['path'])) {
                echo_sitemap_url($baseUrl . '/category/' . $cat['path'], $cat['updated_at'] ?? null, 'weekly', '0.8');
            } elseif (!empty($cat['slug'])) {
                echo_sitemap_url($baseUrl . '/category/' . $cat['slug'], $cat['updated_at'] ?? null, 'weekly', '0.8');
            }
            if (!empty($cat['children'])) {
                traverse_sitemap_categories($cat['children'], $baseUrl);
            }
        }
    }

    traverse_sitemap_categories($categoriesTree, $baseUrl);
} catch (Exception $e) {}

// 3. Dynamic Published Products
try {
    $stmtProd = $pdo->query("SELECT slug, updated_at FROM products WHERE status = 'published' AND deleted_at IS NULL ORDER BY id DESC");
    while ($p = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($p['slug'])) {
            echo_sitemap_url($baseUrl . '/product/' . $p['slug'], $p['updated_at'], 'weekly', '0.8');
        }
    }
} catch (Exception $e) {}

// 4. Dynamic Published Blogs
try {
    $stmtBlog = $pdo->query("SELECT slug, updated_at FROM blogs WHERE status = 'published' AND deleted_at IS NULL ORDER BY id DESC");
    while ($b = $stmtBlog->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($b['slug'])) {
            echo_sitemap_url($baseUrl . '/blog/' . $b['slug'], $b['updated_at'], 'weekly', '0.7');
        }
    }
} catch (Exception $e) {}

echo '</urlset>' . "\n";
