<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/bootstrap.php';


// ── Filters from GET ──────────────────────────────
$search   = trim($_GET['search']   ?? '');
$catSlug  = trim($_GET['category'] ?? '');
$subcat   = trim($_GET['subcat']   ?? '');
$cond     = trim($_GET['condition']?? '');
$minPrice = (float)($_GET['min']   ?? 0);
$maxPrice = (float)($_GET['max']   ?? 0);
$sort     = $_GET['sort']          ?? 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));

// ── Build query ───────────────────────────────────
$where  = ['l.status = ?'];
$params = ['active'];

if ($search) {
  // FULLTEXT may ignore very short tokens depending on MySQL config; fall back to LIKE.
  if (strlen($search) < 3) {
    $where[]  = '(l.title LIKE ? OR l.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
  } else {
    $where[]  = 'MATCH(l.title, l.description) AGAINST(? IN BOOLEAN MODE)';
    $params[] = $search . '*';
  }
}
if ($catSlug) {
    $where[]  = 'c.slug = ?';
    $params[] = $catSlug;
}

if ($subcat) {
    $subcatKey = strtolower($subcat);
    $subcatTermsMap = [
        'womens-wear' => ['women', 'womens', 'ladies', 'female', 'girl'],
        'mens-wear'   => ['men', 'mens', 'male', 'gentlemen', 'boy'],
        'jewellery'   => ['jewellery', 'jewelry', 'necklace', 'ring', 'bracelet', 'earring', 'watch'],
        'sports-shoes' => ['sports shoe', 'sports shoes', 'cleat', 'cleats', 'boot', 'boots', 'trainer', 'trainers'],
        'jerseys'      => ['jersey', 'jerseys', 'kit', 'kits', 'uniform', 'uniforms'],
        'gym-shoes'    => ['gym shoe', 'gym shoes', 'training shoe', 'training shoes', 'running shoe', 'running shoes', 'sneaker', 'sneakers', 'trainer', 'trainers'],
        'phones-accessories' => ['phone', 'phones', 'smartphone', 'smartphones', 'mobile', 'mobile phone', 'accessory', 'accessories', 'case', 'charger', 'earbud', 'earbuds', 'headphone', 'headphones'],
        'laptops-computers'   => ['laptop', 'laptops', 'computer', 'computers', 'pc', 'desktop', 'notebook', 'charger', 'ram', 'hard drive', 'ssd'],
        'tvs-home-audio'      => ['tv', 'tvs', 'television', 'televisions', 'home audio', 'soundbar', 'soundbars', 'speaker', 'speakers', 'subwoofer'],
        'gaming-consoles'     => ['game', 'games', 'gaming', 'console', 'consoles', 'playstation', 'xbox', 'nintendo', 'controller', 'controllers'],
        'cameras-photography' => ['camera', 'cameras', 'photography', 'lens', 'lenses', 'tripod', 'dslr', 'mirrorless', 'gopro'],
        'speakers-headphones' => ['speaker', 'speakers', 'headphone', 'headphones', 'earphone', 'earphones', 'earbud', 'earbuds', 'bluetooth speaker'],
    ];

    if (isset($subcatTermsMap[$subcatKey])) {
        $termClauses = [];
        foreach ($subcatTermsMap[$subcatKey] as $term) {
            $termClauses[] = '(l.title LIKE ? OR l.description LIKE ?)';
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
        }
        $where[] = '(' . implode(' OR ', $termClauses) . ')';
    }
}
if ($cond) {
    $where[]  = 'l.condition_type = ?';
    $params[] = $cond;
}
if ($minPrice > 0) {
    $where[]  = 'l.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice > 0) {
    $where[]  = 'l.price <= ?';
    $params[] = $maxPrice;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);
$orderMap = [
    'newest'     => 'l.created_at DESC',
    'price_asc'  => 'l.price ASC',
    'price_desc' => 'l.price DESC',
    'popular'    => 'l.view_count DESC',
];
$orderBy = $orderMap[$sort] ?? 'l.created_at DESC';

// Count total
$totalRow = Database::fetchOne(
    "SELECT COUNT(*) AS n FROM listings l
     JOIN categories c ON l.category_id = c.id
     $whereSql",
    $params
);
$total = (int)($totalRow['n'] ?? 0);
$pag   = paginate(
        $total,
        $page,
        LISTINGS_PER_PAGE,
        'index.php?' . http_build_query(array_filter([
                'search'    => $search,
                'category'  => $catSlug,
                'subcat'    => $subcat,
                'condition' => $cond,
                'min'       => $minPrice,
                'max'       => $maxPrice,
                'sort'      => $sort,
        ], fn($v)=>$v!==''&&$v!==0))
    );

// Fetch listings
$listings = Database::fetchAll(
    "SELECT l.id, l.title, l.price, l.condition_type, l.view_count, l.is_featured, l.created_at,
            c.name AS cat_name, c.slug AS cat_slug,
            u.id AS seller_id, u.full_name AS seller_name, u.trust_score, u.avatar AS seller_avatar,
            (SELECT image_path FROM listing_images WHERE listing_id=l.id AND is_primary=1 LIMIT 1) AS img
     FROM listings l
     JOIN categories c ON l.category_id = c.id
     JOIN users u ON l.seller_id = u.id
     $whereSql
     ORDER BY l.is_featured DESC, $orderBy
     LIMIT ? OFFSET ?",
    [...$params, LISTINGS_PER_PAGE, $pag['offset']]
);

// Categories for sidebar
try {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
} catch (Throwable $e) {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY name');
}

// Fetch featured products for banner carousel
$featuredListings = Database::fetchAll(
    "SELECT l.id, l.title, l.price, l.is_featured,
            (SELECT image_path FROM listing_images WHERE listing_id=l.id AND is_primary=1 LIMIT 1) AS img
     FROM listings l
     WHERE l.status='active' AND l.is_featured=1
     ORDER BY l.view_count DESC
     LIMIT 8"
);

$pageTitle = 'Browse Listings';
include __DIR__ . '/includes/header.php';
?>

<style>
.index-page {
    background: linear-gradient(135deg, #f4f6f9 0%, #eef2f7 100%);
    min-height: 100vh;
}

/* ── Hero Section ──────────────────────────────────────────── */
.hero-section {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
    color: #fff;
    padding: 48px 20px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}

.hero-inner {
    max-width: 1400px;
    margin: 0 auto;
    text-align: center;
}

.hero-title {
    font-size: 32px;
    font-weight: 900;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.hero-subtitle {
    font-size: 16px;
    opacity: 0.95;
    margin-bottom: 28px;
    font-weight: 400;
    letter-spacing: 0.3px;
}

.hero-search-wrapper {
    display: flex;
    gap: 12px;
    max-width: 600px;
    margin: 0 auto 24px;
    flex-wrap: wrap;
    justify-content: center;
    align-items: stretch;
}

.hero-search-wrapper input {
    flex: 1;
    min-width: 240px;
    padding: 14px 18px;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    background: rgba(255,255,255,0.95);
    color: var(--text);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
}

.hero-search-wrapper input::placeholder {
    color: #999;
}

.hero-search-wrapper input:focus {
    outline: none;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.hero-search-wrapper button {
    padding: 14px 28px;
    background: var(--accent);
    color: var(--text);
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(245,166,35,0.2);
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hero-search-wrapper button:hover {
    background: #e09520;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(245,166,35,0.3);
}

.hero-categories {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 20px;
}

.hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.hero-chip:hover {
    background: rgba(255,255,255,0.28);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-2px);
}

.top-nav {
    background: white;
    border-bottom: 1px solid #eee;
    padding: 12px 0;
    position: sticky;
    top: 0;
    z-index: 100;
}

.top-nav-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    gap: 30px;
    align-items: center;
}

.nav-link {
    font-size: 14px;
    color: #666;
    text-decoration: none;
    font-weight: 500;
    padding: 8px 0;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-link i {
    width: 16px;
    text-align: center;
}

.nav-link:hover, .nav-link.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

.main-layout {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px 20px;
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 30px;
}

.sidebar {
    background: var(--surface);
    border-radius: 14px;
    padding: 24px;
    height: fit-content;
    position: static;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: all 0.2s ease;
}

.sidebar:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.09);
}

.sidebar h3 {
    font-size: 12px;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
}

body.sidebar-collapsed .main-layout { grid-template-columns: 1fr; }
body.sidebar-collapsed #browse-sidebar { display: none; }
body.sidebar-collapsed .sidebar-toggle { display: inline-flex; }

.sidebar-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.sidebar-title .title-left {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.sidebar-collapse {
  border: 1px solid #ddd;
  background: #fff;
  color: #666;
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.sidebar-collapse:hover { background: #f3f3f3; }


.sidebar h3 i {
    color: #667eea;
    width: 14px;
}

.sidebar-section {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 2px solid var(--border);
}

.sidebar-section:first-of-type {
    margin-top: 20px;
    padding-top: 0;
    border-top: none;
}

.sidebar-section:last-child {
    margin-bottom: 0;
}



.price-filter {
    display: flex;
    gap: 8px;
}

.price-filter input {
    width: 50%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 12px;
}

.main-content {
    background: var(--surface);
    border-radius: 14px;
    padding: 0;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
}

.content-header {
    padding: 24px 28px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    background: linear-gradient(135deg, rgba(151,14,14,0.02) 0%, rgba(245,166,35,0.02) 100%);
}

.content-header h2 {
    font-size: 20px;
    font-weight: 800;
    color: var(--primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.3px;
}

.buy-now {
    color: var(--primary);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.content-header h2 i {
    color: var(--primary);
    font-size: 22px;
}

.sort-dropdown {
    font-size: 14px;
    padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    cursor: pointer;
    font-weight: 600;
    color: var(--text);
    transition: all 0.2s ease;
}

.sort-dropdown:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(151,14,14,0.04) 0%, rgba(245,166,35,0.04) 100%);
}

.quick-search {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  justify-content: center;
}

.quick-search input {
    width: 100%;
    max-width: 520px;
    padding: 11px 16px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    background: rgba(255,255,255,0.6);
    color: var(--text);
    font-weight: 500;
    transition: all 0.2s ease;
}

.quick-search input:focus {
    outline: none;
    border-color: var(--primary);
    background: #fff;
    box-shadow: 0 4px 12px rgba(151,14,14,0.1);
}

.quick-search button {
    padding: 11px 18px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: #fff;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(151,14,14,0.15);
    display: flex;
    align-items: center;
    gap: 6px;
}

.quick-search button:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(151,14,14,0.2);
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    padding: 24px;
}

.listing-card {
    background: var(--surface);
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow);
}

.listing-card:hover {
    border-color: var(--accent);
    box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    transform: translateY(-6px);
}

.listing-card-img {
    width: 100%;
    height: 140px;
    background: linear-gradient(135deg, #eef2f7 0%, #e0e9f0 100%);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    overflow: hidden;
    color: #999;
}

.featured-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--accent) 0%, #f5a623 100%);
  color: #1a1f2e;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: .2px;
  box-shadow: 0 8px 24px rgba(245,166,35,0.25);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

.listing-card.is-featured {
  border: 2px solid var(--accent);
  background: linear-gradient(135deg, rgba(245,166,35,0.02) 0%, rgba(151,14,14,0.02) 100%);
}

.listing-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.listing-card-body {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.listing-card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 6px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    letter-spacing: -0.2px;
}

.listing-card-price {
    font-size: 15px;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    letter-spacing: -0.3px;
}

.listing-card-price i {
    font-size: 12px;
}

.condition-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    padding: 5px 10px;
    border-radius: 6px;
    background: #e8f0ff;
    color: #004085;
    font-weight: 700;
    margin-bottom: 10px;
    width: fit-content;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.condition-badge.new {
    background: #d4edda;
    color: #155724;
}

.condition-badge.like_new {
    background: #d1ecf1;
    color: #0c5460;
}

.condition-badge.good {
    background: #cce5ff;
    color: #004085;
}

.condition-badge.fair {
    background: #fff3cd;
    color: #856404;
}

.listing-card-meta {
    font-size: 12px;
    color: var(--muted);
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.listing-card-seller {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.listing-card-seller-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid var(--border);
}

.listing-card-seller-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.listing-card-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}

.listing-card-meta i {
    color: var(--accent);
    font-size: 12px;
}

.empty-state {
    text-align: center;
    padding: 80px 40px;
    color: var(--muted);
}

.empty-icon {
    font-size: 72px;
    margin-bottom: 20px;
    color: #e0e4ea;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--text);
    margin-bottom: 12px;
    font-weight: 800;
}

.empty-state p {
    font-size: 15px;
    color: var(--muted);
}

.sidebar-toggle {
  display: none;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #666;
  text-decoration: none;
  font-weight: 600;
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid #ddd;
  background: #fff;
  cursor: pointer;
  white-space: nowrap;
}

.sidebar-overlay {
  display: none;
}

@media (max-width: 768px) {
    .hero-section {
        padding: 36px 16px;
        margin-bottom: 24px;
    }

    .hero-title {
        font-size: 26px;
    }

    .hero-subtitle {
        font-size: 14px;
        margin-bottom: 20px;
    }

    .hero-search-wrapper {
        flex-direction: column;
        max-width: 100%;
        margin-bottom: 20px;
    }

    .hero-search-wrapper input,
    .hero-search-wrapper button {
        width: 100%;
        padding: 11px 14px;
        font-size: 13px;
    }

    .hero-categories {
        margin-top: 16px;
        gap: 8px;
    }

    .hero-chip {
        font-size: 12px;
        padding: 6px 12px;
    }

    .main-layout {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 16px 12px;
    }
    .sidebar {
        position: static;
        max-height: none;
        overflow: visible;
        border-radius: 12px;
    }
    .main-content {
        min-width: 0;
    }
    .listings-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        padding: 16px;
        gap: 16px;
    }
    .category-list a {
        font-size: 13px;
        padding: 9px 10px;
    }
    .top-nav-content {
        gap: 15px;
        overflow-x: auto;
    }
    .content-header {
        padding: 16px 20px;
        gap: 12px;
    }
    .content-header h2 {
        font-size: 16px;
    }
    .quick-search {
        width: 100%;
    }
    .quick-search input,
    .quick-search button {
        font-size: 13px;
        padding: 9px 12px;
    }
}

@media (max-width: 480px) {
    .hero-section {
        padding: 28px 14px;
    }

    .hero-title {
        font-size: 22px;
        margin-bottom: 6px;
    }

    .hero-subtitle {
        font-size: 13px;
        margin-bottom: 16px;
    }

    .hero-search-wrapper {
        margin-bottom: 16px;
    }

    .hero-chip {
        font-size: 11px;
        padding: 5px 10px;
    }

    .main-layout {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 12px 10px;
    }

    .sidebar-toggle {
        display: inline-flex;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease;
        z-index: 400;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: min(280px, 82vw);
        z-index: 500;
        border-radius: 0 12px 12px 0;
        transform: translateX(-110%);
        transition: transform 0.18s ease;
        max-height: none;
        overflow: auto;
        visibility: hidden;
        pointer-events: none;
    }

    body.sidebar-open .sidebar {
        transform: translateX(0);
        visibility: visible;
        pointer-events: auto;
    }

    .sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease;
        z-index: 400;
    }

    body.sidebar-open .sidebar-overlay {
        display: block;
        opacity: 1;
        pointer-events: auto;
    }

    .listings-grid {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        padding: 12px;
        gap: 12px;
    }

    .listing-card-img {
        height: 130px;
    }

    .listing-card-title {
        font-size: 13px;
    }

    .listing-card-price {
        font-size: 15px;
        margin-bottom: 8px;
    }

    .content-header {
        padding: 14px 16px;
        gap: 10px;
    }

    .content-header h2 {
        font-size: 14px;
    }

    .sort-dropdown,
    .quick-search input,
    .quick-search button {
        font-size: 12px;
    }

    .condition-badge {
        font-size: 10px;
        padding: 4px 8px;
    }
}

/* ── Featured Banner Section ──────────────────────────────────── */
.featured-banner {
    background: linear-gradient(135deg, #0f2a47 0%, #1e3a5f 50%, #2d1f1e 100%);
    background-attachment: fixed;
    color: #fff;
    padding: 48px 20px;
    margin: 0 0 32px;
    border-radius: 0 0 24px 24px;
    box-shadow: 0 16px 56px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.05);
    min-height: 340px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.featured-banner::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(245,166,35,0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(102,126,234,0.05) 0%, transparent 50%);
    pointer-events: none;
}

.featured-banner-carousel {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.featured-banner-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.8s ease-in-out;
    display: flex;
    align-items: center;
}

.featured-banner-slide.active {
    opacity: 1;
    z-index: 10;
}

.featured-banner-content {
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    align-items: center;
    padding: 0 20px;
    position: relative;
    z-index: 5;
}

.featured-banner-info h3 {
    font-size: 12px;
    color: var(--accent);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 12px;
    display: inline-block;
    padding: 6px 14px;
    background: rgba(245,166,35,0.12);
    border-radius: 20px;
}

.featured-banner-info h2 {
    font-size: 40px;
    font-weight: 900;
    margin-bottom: 16px;
    line-height: 1.15;
    letter-spacing: -0.8px;
}

.featured-banner-info p {
    font-size: 15px;
    color: rgba(255,255,255,0.85);
    margin-bottom: 24px;
    line-height: 1.6;
}

.featured-banner-price {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
}

.featured-banner-price .current {
    font-size: 36px;
    font-weight: 900;
    color: var(--accent);
    line-height: 1;
}

.featured-banner-price .original {
    font-size: 18px;
    color: rgba(255,255,255,0.5);
    text-decoration: line-through;
}

.featured-banner-price .discount {
    background: linear-gradient(135deg, var(--accent) 0%, #f5a623 100%);
    color: #1a1f2e;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 0.5px;
}

.featured-banner-image {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 320px;
}

.featured-banner-image::before {
    content: '';
    position: absolute;
    inset: -20px;
    background: radial-gradient(circle at 30% 30%, rgba(245,166,35,0.15) 0%, transparent 70%);
    border-radius: 20px;
    z-index: 0;
}

.featured-banner-image img {
    max-width: 100%;
    max-height: 360px;
    object-fit: contain;
    filter: drop-shadow(0 24px 48px rgba(0,0,0,0.35)) drop-shadow(0 4px 12px rgba(245,166,35,0.2));
    animation: slideInRight 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    z-index: 1;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(60px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

.featured-banner-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 32px;
    background: linear-gradient(135deg, var(--accent) 0%, #f5a623 100%);
    color: #1a1f2e;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 8px 20px rgba(245,166,35,0.3), inset 0 1px 0 rgba(255,255,255,0.3);
}

.featured-banner-btn:hover {
    background: linear-gradient(135deg, #f5a623 0%, #e09520 100%);
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(245,166,35,0.4);
}

.featured-banner-indicators {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 20;
}

.carousel-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    border: 2px solid transparent;
}

.carousel-dot.active {
    background: var(--accent);
    width: 32px;
    border-radius: 5px;
    box-shadow: 0 4px 16px rgba(245,166,35,0.4);
}

@media (max-width: 768px) {
    .featured-banner {
        padding: 36px 16px;
        margin-bottom: 24px;
        border-radius: 0 0 20px 20px;
        min-height: 280px;
    }

    .featured-banner-content {
        grid-template-columns: 1fr;
        gap: 32px;
        padding: 0 16px;
    }

    .featured-banner-info h2 {
        font-size: 32px;
    }

    .featured-banner-image {
        min-height: 240px;
    }

    .featured-banner-image img {
        max-height: 260px;
    }
}

@media (max-width: 480px) {
    .hero-search-wrapper {
        gap: 8px;
        margin-bottom: 12px;
    }

    .hero-search-wrapper input {
        flex: 1;
        min-width: 100px;
        padding: 9px 10px;
        border-radius: 8px;
        font-size: 12px;
    }

    .hero-search-wrapper button {
        padding: 9px 12px;
        border-radius: 8px;
        font-size: 12px;
        gap: 6px;
    }

    .featured-banner {
        padding: 20px 12px;
        margin-bottom: 16px;
        border-radius: 0 0 16px 16px;
        min-height: 180px;
    }

    .featured-banner-content {
        gap: 12px;
        padding: 0 8px;
    }

    .featured-banner-info {
        text-align: center;
    }

    .featured-banner-info h3 {
        font-size: 10px;
        margin-bottom: 6px;
        padding: 4px 10px;
    }

    .featured-banner-info h2 {
        font-size: 20px;
        margin-bottom: 8px;
        line-height: 1.15;
    }

    .featured-banner-info p {
        font-size: 12px;
        margin-bottom: 10px;
    }

    .featured-banner-price {
        margin-bottom: 12px;
        gap: 8px;
    }

    .featured-banner-price .current {
        font-size: 28px;
    }

    .featured-banner-price .original {
        font-size: 14px;
    }

    .featured-banner-price .discount {
        padding: 6px 12px;
        font-size: 11px;
    }

    .featured-banner-btn {
        padding: 11px 20px;
        font-size: 13px;
        gap: 6px;
    }

    .featured-banner-image {
        min-height: 160px;
    }

    .featured-banner-image img {
        max-height: 180px;
    }
    }

    .featured-banner-price {
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }

    .featured-banner-price .current {
        font-size: 24px;
    }

    .featured-banner-price .discount {
        font-size: 12px;
        padding: 6px 10px;
        border-radius: 999px;
    }

    .featured-banner-btn {
        width: 100%;
        justify-content: center;
        padding: 12px 18px;
        font-size: 14px;
    }

    .featured-banner-image {
        min-height: 160px;
    }

    .featured-banner-image img {
        max-height: 180px;
    }

    .featured-banner-indicators {
        bottom: 14px;
    }
}

/* ── Additional Polish ──────────────────────────────────── */
.listing-card-img {
    transition: transform 0.3s ease;
}

.listing-card:hover .listing-card-img {
    transform: scale(1.05);
}

.listing-card-seller-avatar {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.listing-card:hover .listing-card-seller-avatar {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(151,14,14,0.2);
}

.condition-badge {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-chip {
    animation: fadeInUp 0.4s ease 0.1s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.listing-card {
    animation: fadeInUp 0.3s ease;
}

/* Smooth scrolling and transitions */
html {
    scroll-behavior: smooth;
}

* {
    transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
}

button, a {
    transition: all 0.2s ease;
}
</style>

  <!-- Hero Section -->
  <div class="hero-section">
    <div class="hero-inner">
      <p class="hero-subtitle">Browse thousands of items from fellow students. Buy smart, sell easy.</p>
      
      <form method="GET" class="hero-search-wrapper" action="index.php">
        <input type="text" name="search" placeholder="Search for items, books, electronics..." value="<?= e($search) ?>">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
      </form>

      <div class="hero-categories">
        <a href="index.php" class="hero-chip" title="Browse all items">
          <i class="fas fa-inbox"></i> All Items
        </a>
        <?php foreach ($categories as $cat): ?>
                    <a href="<?= buildQueryString(['category' => $cat['slug'], 'subcat' => '']) ?>" class="hero-chip" title="<?= e(getCategoryDisplayName($cat['slug'], $cat['name'])) ?>">
                        <i class="<?= getCategoryIcon($cat['slug']) ?>"></i> <?= e(getCategoryDisplayName($cat['slug'], $cat['name'])) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Featured Banner Section -->
  <?php if (!empty($featuredListings)): ?>
  <div class="featured-banner">
    <div class="featured-banner-carousel" id="featured-carousel">
      <?php foreach ($featuredListings as $idx => $listing): ?>
      <div class="featured-banner-slide <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>">
        <div class="featured-banner-content">
          <div class="featured-banner-info">
            <h2><?= e($listing['title']) ?></h2>
            <p>Check out this amazing deal! Limited time offer on premium quality items from verified sellers.</p>
            <div class="featured-banner-price">
              <span class="current"><?= formatPrice($listing['price']) ?></span>
              <span class="discount">⚡ Limited Time</span>
            </div>
            <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $listing['id'] ?>" class="featured-banner-btn">
              <i class="fas fa-shopping-cart"></i> View Item
            </a>
          </div>
          <div class="featured-banner-image">
            <?php if ($listing['img']): ?>
              <img src="<?= APP_URL.'/public/'.e($listing['img']) ?>" alt="<?= e($listing['title']) ?>" loading="lazy">
            <?php else: ?>
              <div style="font-size: 64px; color: rgba(255,255,255,0.5);"><i class="fas fa-box"></i></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    
    <?php if (count($featuredListings) > 1): ?>
    <div class="featured-banner-indicators">
      <?php foreach ($featuredListings as $idx => $listing): ?>
      <div class="carousel-dot <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>" onclick="goToSlide(<?= $idx ?>)"></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- Main Layout -->
  <div class="main-layout">
    <!-- Sidebar Filters -->
    <aside class="sidebar" id="browse-sidebar">
      <h3 class="sidebar-title">
        <span class="title-left"><i class="fas fa-list"></i> Categories</span>
        <button type="button" class="sidebar-collapse" id="sidebar-collapse" aria-label="Hide categories">
          <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
      </h3>

      <?php
        $currentCategorySlug = $catSlug;
        $currentSubcat = $subcat;
                $renderSidebarTitle = false;
        include __DIR__ . '/includes/categories_sidebar_categories.php';
      ?>

      <div class="sidebar-section">
        <h3><i class="fas fa-filter"></i> Filters</h3>
        <form method="GET" id="filter-form">
          <?php if ($search): ?><input type="hidden" name="search" value="<?= e($search) ?>"><?php endif; ?>
                    <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= e($catSlug) ?>"><?php endif; ?>
                    <?php if ($subcat): ?><input type="hidden" name="subcat" value="<?= e($subcat) ?>"><?php endif; ?>
                    <?php if ($sort): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>

          <div style="margin-bottom: 12px;">
            <label style="font-size: 12px; color: #666; font-weight: 600; display: block; margin-bottom: 6px;">
              <i class="fas fa-check-circle" style="color: #667eea; margin-right: 4px;"></i>Condition
            </label>
            <select name="condition" class="form-control" style="font-size: 12px;">
              <option value="">Any</option>
              <option value="new" <?= $cond==='new'?'selected':'' ?>>New</option>
              <option value="like_new" <?= $cond==='like_new'?'selected':'' ?>>Like New</option>
              <option value="good" <?= $cond==='good'?'selected':'' ?>> Good</option>
              <option value="fair" <?= $cond==='fair'?'selected':'' ?>> Fair</option>
            </select>
          </div>

          <div style="margin-bottom: 12px;">
            <label style="font-size: 12px; color: #666; font-weight: 600; display: block; margin-bottom: 6px;">
              <i class="fas fa-money-bill-wave" style="color: #667eea; margin-right: 4px;"></i>Price Range (UGX)
            </label>
            <div class="price-filter">
              <input type="number" name="min" placeholder="Min" value="<?= $minPrice ?: '' ?>">
              <input type="number" name="max" placeholder="Max" value="<?= $maxPrice ?: '' ?>">
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 12px; padding: 8px;">
            <i class="fas fa-check"></i> Apply
          </button>
                    <?php if ($catSlug || $subcat || $cond || $minPrice || $maxPrice): ?>
            <a href="index.php" class="btn btn-outline" style="width: 100%; font-size: 12px; padding: 8px; margin-top: 8px; display: block; text-align: center;">
              <i class="fas fa-redo"></i> Clear
            </a>
          <?php endif; ?>
        </form>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
      <div class="content-header">
        <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-expanded="false" aria-controls="browse-sidebar">
          <i class="fas fa-list"></i> Categories
        </button>
        <h2>
          <?php if ($catSlug): ?>
            <i class="fas fa-shopping-bag"></i>
            <?= getCategoryName($catSlug, $categories) ?>
          <?php else: ?>
            <i class="fas fa-fire"></i> Hot Deals
          <?php endif; ?>
        </h2>

        <select name="sort" class="sort-dropdown" onchange="window.location.href='<?= buildQueryString(['sort' => '']) ?>'.replace('sort=', 'sort=') + this.value">
          <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option>
          <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low → High</option>
          <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
          <option value="popular" <?= $sort==='popular'?'selected':'' ?>>Most Viewed</option>
        </select>
      </div>

      <?php if (empty($listings)): ?>
        <div class="empty-state">
          <div class="empty-icon"><i class="fas fa-search"></i></div>
          <h3>No listings found</h3>
          <p>Try adjusting your search or filters</p>
        </div>
      <?php else: ?>
        <div class="listings-grid">
          <?php foreach ($listings as $l): ?>
            <?php $isFeatured = !empty($l['is_featured']); ?>
            <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $l['id'] ?>" class="listing-card <?= $isFeatured ? 'is-featured' : '' ?>">
              <div class="listing-card-img">
                <?php if ($isFeatured): ?>
                  <div class="featured-badge"><i class="fas fa-bolt"></i> Featured</div>
                <?php endif; ?>
                <?php if ($l['img']): ?>
                  <img src="<?= APP_URL.'/public/'.e($l['img']) ?>" alt="<?= e($l['title']) ?>">
                <?php else: ?>
                  <i class="fas fa-box"></i>
                <?php endif; ?>
              </div>
              <div class="listing-card-body">
                <div class="listing-card-title"><?= e($l['title']) ?></div>
                <div class="listing-card-price"><i class="fas fa-tag"></i> <?= formatPrice($l['price']) ?></div>
                <div class="condition-badge condition-badge-<?= strtolower($l['condition_type']) ?>">
                  <?= conditionBadge($l['condition_type']) ?>
                </div>
                <div class="listing-card-meta">
                  <div class="listing-card-seller">
                    <div class="listing-card-seller-avatar">
                      <?php if (!empty($l['seller_avatar'])): ?>
                        <img src="<?= APP_URL . '/public/' . e($l['seller_avatar']) ?>" alt="<?= e($l['seller_name']) ?>">
                      <?php else: ?>
                        <?= strtoupper(substr($l['seller_name'], 0, 1)) ?>
                      <?php endif; ?>
                    </div>
                    <span><?= e(explode(' ', $l['seller_name'])[0]) ?></span>
                  </div>
                  <span><i class="fas fa-star"></i> <?= number_format($l['trust_score'], 1) ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <?= getPaginationHTML($pag) ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
// Featured carousel functionality
(function () {
  const carousel = document.getElementById('featured-carousel');
  if (!carousel) return;

  const slides = document.querySelectorAll('.featured-banner-slide');
  const dots = document.querySelectorAll('.carousel-dot');
  let currentIndex = 0;
  let autoplayInterval;

  function showSlide(index) {
    slides.forEach((slide, i) => {
      slide.classList.toggle('active', i === index);
    });
    dots.forEach((dot, i) => {
      dot.classList.toggle('active', i === index);
    });
    currentIndex = index;
  }

  function nextSlide() {
    const nextIndex = (currentIndex + 1) % slides.length;
    showSlide(nextIndex);
  }

  function startAutoplay() {
    if (slides.length > 1) {
      autoplayInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
    }
  }

  function stopAutoplay() {
    clearInterval(autoplayInterval);
  }

  // Make goToSlide accessible globally
  window.goToSlide = function(index) {
    showSlide(index);
    stopAutoplay();
    startAutoplay(); // Restart autoplay
  };

  // Start autoplay on page load
  startAutoplay();

  // Pause on hover
  carousel.addEventListener('mouseenter', stopAutoplay);
  carousel.addEventListener('mouseleave', startAutoplay);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
function buildQueryString($updates) {
    global $search, $catSlug, $subcat, $cond, $minPrice, $maxPrice, $sort;
    $query = ['search'=>$search,'category'=>$catSlug,'subcat'=>$subcat,'condition'=>$cond,'min'=>$minPrice,'max'=>$maxPrice,'sort'=>$sort];
    $query = array_merge($query, $updates);
    return 'index.php?' . http_build_query(array_filter($query, fn($v)=>$v!==''&&$v!==0));
}

function getCategoryName($slug, $categories) {
    foreach ($categories as $c) {
        if ($c['slug'] === $slug) return getCategoryDisplayName($c['slug'], $c['name']);
    }
    return 'Items';
}
?>