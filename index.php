<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/bootstrap.php';


// ── Filters from GET ──────────────────────────────
$search   = trim($_GET['search']   ?? '');
$catSlug  = trim($_GET['category'] ?? '');
$cond     = trim($_GET['condition']?? '');
$minPrice = (float)($_GET['min']   ?? 0);
$maxPrice = (float)($_GET['max']   ?? 0);
$sort     = $_GET['sort']          ?? 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));

// ── Build query ───────────────────────────────────
$where  = ['l.status = "active"'];
$params = [];

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
$pag   = paginate($total, $page, LISTINGS_PER_PAGE, 'index.php?' . http_build_query(array_filter(compact('search','catSlug','cond','minPrice','maxPrice','sort'), fn($v)=>$v!==''&&$v!==0)));

// Fetch listings
$listings = Database::fetchAll(
    "SELECT l.id, l.title, l.price, l.condition_type, l.view_count, l.is_featured, l.created_at,
            c.name AS cat_name, c.slug AS cat_slug,
            u.id AS seller_id, u.full_name AS seller_name, u.trust_score,
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
$categories = Database::fetchAll('SELECT * FROM categories ORDER BY name');

$pageTitle = 'Browse Listings';
include __DIR__ . '/includes/header.php';
?>

<style>
.index-page {
    background: #9a1717;
    min-height: 100vh;

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
    background: white;
    border-radius: 8px;
    padding: 20px;
    height: fit-content;
    position: sticky;
    top: 80px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.sidebar h3 {
    font-size: 12px;
    font-weight: 700;
    color: #333;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

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

body.sidebar-collapsed .main-layout { grid-template-columns: 1fr; }
body.sidebar-collapsed #browse-sidebar { display: none; }
body.sidebar-collapsed .sidebar-toggle { display: inline-flex; }

.sidebar h3 i {
    color: #667eea;
    width: 14px;
}

.sidebar-section {
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eee;
}

.sidebar-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.category-list {
    list-style: none;
}

.category-list li {
    margin-bottom: 8px;
}

.category-list a {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #666;
    text-decoration: none;
    padding: 8px;
    border-radius: 4px;
    transition: all 0.2s;
}

.category-list a i {
    width: 16px;
    text-align: center;
    color: #999;
}

.category-list a:hover {
    background: #f0f0f0;
    color: #333;
}

.category-list a:hover i {
    color: #667eea;
}

.category-list a.active {
    background: #e8f0ff;
    color: #667eea;
    font-weight: 600;
}

.category-list a.active i {
    color: #667eea;
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
    background: white;
    border-radius: 8px;
    padding: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.content-header {
    padding: 20px 24px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.content-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: #333;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.content-header h2 i {
    color: #667eea;
    font-size: 20px;
}

.sort-dropdown {
    font-size: 13px;
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    cursor: pointer;
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
  padding: 9px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 13px;
}

.quick-search button {
  padding: 9px 12px;
  border: 1px solid var(--primary);
  border-radius: 6px;
  background: var(--primary);
  color: #fff;
  cursor: pointer;
  font-size: 13px;
  white-space: nowrap;
}

.quick-search button:hover {
  background: var(--primary-light);
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
    padding: 24px;
}

.listing-card {
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s;
    border: 1px solid transparent;
    display: flex;
    flex-direction: column;
}

.listing-card:hover {
    border-color: #ddd;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-4px);
}

.listing-card-img {
    width: 100%;
    height: 140px;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    overflow: hidden;
    color: #999;
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
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.listing-card-price {
    font-size: 14px;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.listing-card-price i {
    font-size: 12px;
}

.condition-badge {
    display: inline-block;
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 4px;
    background: #e8f0ff;
    color: #667eea;
    font-weight: 600;
    margin-bottom: 8px;
    width: fit-content;
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
    font-size: 11px;
    color: #999;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: auto;
}

.listing-card-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.listing-card-meta i {
    color: #ffc107;
    font-size: 11px;
}

.empty-state {
    text-align: center;
    padding: 80px 40px;
    color: #999;
}

.empty-icon {
    font-size: 72px;
    margin-bottom: 20px;
    color: #ddd;
}

.empty-state h3 {
    font-size: 18px;
    color: #333;
  margin-bottom: 8px;
}

.empty-state p {
    font-size: 14px;
    color: #999;
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
    .main-layout {
    grid-template-columns: 1fr;
    gap: 16px;
    padding: 16px 12px;
    }
    .sidebar {
    position: static;
    max-height: none;
    overflow: visible;
    }
  .main-content {
    min-width: 0;
  }
    .listings-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    padding: 16px;
    }
  .category-list a {
    font-size: 12px;
    padding: 6px;
  }
    .top-nav-content {
        gap: 15px;
        overflow-x: auto;
    }
}

  @media (max-width: 480px) {
    .main-layout {
      grid-template-columns: 1fr;
      gap: 12px;
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
      border-radius: 0 8px 8px 0;
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

    body.sidebar-open .sidebar-overlay {
      display: block;
      opacity: 1;
      pointer-events: auto;
    }
  }
</style>

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
      <ul class="category-list">
        <li><a href="index.php" class="<?= !$catSlug ? 'active' : '' ?>"><i class="fas fa-inbox"></i> All Items</a></li>
        <?php foreach ($categories as $cat): ?>
          <li>
            <a href="<?= buildQueryString(['category' => $cat['slug']]) ?>"
               class="<?= $catSlug === $cat['slug'] ? 'active' : '' ?>">
              <i class="<?= getCategoryIcon($cat['slug']) ?>"></i> <?= e($cat['name']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="sidebar-section">
        <h3><i class="fas fa-filter"></i> Filters</h3>
        <form method="GET" id="filter-form">
          <?php if ($search): ?><input type="hidden" name="search" value="<?= e($search) ?>"><?php endif; ?>

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
          <?php if ($catSlug || $cond || $minPrice || $maxPrice): ?>
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
          <i class="fas fa-shopping-bag"></i>
          <?= $catSlug ? getCategoryName($catSlug, $categories) : 'BUY NOW' ?>
        </h2>

        <form method="GET" class="quick-search">
          <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= e($catSlug) ?>"><?php endif; ?>
          <?php if ($cond): ?><input type="hidden" name="condition" value="<?= e($cond) ?>"><?php endif; ?>
          <?php if ($minPrice): ?><input type="hidden" name="min" value="<?= e((string)$minPrice) ?>"><?php endif; ?>
          <?php if ($maxPrice): ?><input type="hidden" name="max" value="<?= e((string)$maxPrice) ?>"><?php endif; ?>
          <?php if ($sort): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
          <input type="text" name="search" placeholder="Search items…" value="<?= e($search) ?>">
          <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>

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
            <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $l['id'] ?>" class="listing-card">
              <div class="listing-card-img">
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
                  <span><i class="fas fa-star"></i> <?= number_format($l['trust_score'], 1) ?></span>
                  <span><i class="fas fa-user"></i> <?= e(explode(' ', $l['seller_name'])[0]) ?></span>
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
(function () {
  const toggleBtn = document.getElementById('sidebar-toggle');
  const sidebar = document.getElementById('browse-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const collapseBtn = document.getElementById('sidebar-collapse');

  if (!toggleBtn || !sidebar || !overlay) return;

  function setOpen(isOpen) {
    document.body.classList.toggle('sidebar-open', isOpen);
    toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  function setCollapsed(isCollapsed) {
    document.body.classList.toggle('sidebar-collapsed', isCollapsed);
    if (isCollapsed) setOpen(false);
  }

  toggleBtn.addEventListener('click', function () {
    if (document.body.classList.contains('sidebar-collapsed')) {
      setCollapsed(false);
      return;
    }
    const openNow = document.body.classList.contains('sidebar-open');
    setOpen(!openNow);
  });

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      const collapsedNow = document.body.classList.contains('sidebar-collapsed');
      setCollapsed(!collapsedNow);
    });
  }

  overlay.addEventListener('click', function () {
    setOpen(false);
  });

  sidebar.addEventListener('click', function (e) {
    const link = e.target.closest('a');
    if (!link) return;
    setOpen(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
function buildQueryString($updates) {
    global $search, $catSlug, $cond, $minPrice, $maxPrice, $sort;
    $query = ['search'=>$search,'category'=>$catSlug,'condition'=>$cond,'min'=>$minPrice,'max'=>$maxPrice,'sort'=>$sort];
    $query = array_merge($query, $updates);
    return 'index.php?' . http_build_query(array_filter($query, fn($v)=>$v!==''&&$v!==0));
}

function getCategoryName($slug, $categories) {
    foreach ($categories as $c) {
        if ($c['slug'] === $slug) return $c['name'];
    }
    return 'Items';
}

function getCategoryIcon($slug) {
    $icons = [
        'electronics' => 'fas fa-laptop',
        'books' => 'fas fa-book',
        'apparel' => 'fas fa-shirt',
        'home-living' => 'fas fa-home',
        'services' => 'fas fa-wrench',
        'sports' => 'fas fa-basketball',
        'furniture' => 'fas fa-chair',
    ];
    return $icons[$slug] ?? 'fas fa-cube';
}
?>