<?php
/**
 * Shared Categories sidebar block.
 *
 * Optional variables:
 * - $currentCategorySlug (string)
 * - $currentSubcat (string)
 * - $categories (array)  (if not provided it will be fetched)
 * - $renderSidebarTitle (bool) (default true)
 */

$currentCategorySlug = isset($currentCategorySlug) ? (string)$currentCategorySlug : '';
$currentSubcat = isset($currentSubcat) ? (string)$currentSubcat : '';
$renderSidebarTitle = isset($renderSidebarTitle) ? (bool)$renderSidebarTitle : true;

if (!isset($categories) || !is_array($categories)) {
  try {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
  } catch (Throwable $e) {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY name');
  }
}

// Get listing counts for each category
$categoryCounts = [];
$allItemsCount = 0;
try {
  $counts = Database::fetchAll('SELECT category_id, COUNT(*) AS count FROM listings WHERE status = "active" GROUP BY category_id');
  foreach ($counts as $row) {
    $categoryCounts[(int)$row['category_id']] = (int)$row['count'];
    $allItemsCount += (int)$row['count'];
  }
} catch (Throwable $e) {
  // If query fails, counts will remain empty
}

$hrefAll = APP_URL . '/index.php#top';

$hrefFor = static function (string $categorySlug, string $subcat = ''): string {
  $params = [];
  if ($categorySlug !== '') $params['category'] = $categorySlug;
  if ($subcat !== '') $params['subcat'] = $subcat;
  $qs = $params ? ('?' . http_build_query($params)) : '';
  return APP_URL . '/index.php' . $qs . '#top';
};
?>

<?php if ($renderSidebarTitle): ?>
  <h3 class="sidebar-title">
    <span class="title-left"><i class="fas fa-list"></i> Categories</span>
  </h3>
<?php endif; ?>

<ul class="category-list">
  <li>
    <a href="<?= e($hrefAll) ?>" class="<?= $currentCategorySlug === '' ? 'active' : '' ?>">
      <span class="category-label">
        <i class="fas fa-inbox"></i> All Items
      </span>
      <span class="category-count"><?= $allItemsCount ?></span>
    </a>
  </li>

  <?php foreach ($categories as $cat): ?>
    <?php $slug = (string)($cat['slug'] ?? ''); ?>
    <?php if ($slug === '') continue; ?>
    
    <?php $catId = (int)($cat['id'] ?? 0); ?>
    <?php $catCount = $categoryCounts[$catId] ?? 0; ?>

    <?php $hasSubmenu = ($slug === 'clothing' || $slug === 'sports' || $slug === 'electronics'); ?>
    <li class="category-item <?= $hasSubmenu ? 'has-submenu' : '' ?>">
      <a href="<?= e($hrefFor($slug, '')) ?>"
         class="<?= ($currentCategorySlug === $slug && $currentSubcat === '') ? 'active' : '' ?>"
         <?= $hasSubmenu ? 'aria-haspopup="true" aria-expanded="false"' : '' ?>>
        <span class="category-label">
          <i class="<?= e(getCategoryIcon($slug)) ?>"></i> <?= e(getCategoryDisplayName($slug, (string)($cat['name'] ?? ''))) ?>
        </span>
        <span class="category-count"><?= $catCount ?></span>
      </a>

      <?php if ($hasSubmenu): ?>
        <?php if ($slug === 'clothing'): ?>
          <div class="subcategory-menu" role="menu" aria-label="Fashion subcategories">
            <a role="menuitem" href="<?= e($hrefFor('clothing', 'womens-wear')) ?>" class="<?= ($currentCategorySlug === 'clothing' && $currentSubcat === 'womens-wear') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Womens wear
            </a>
            <a role="menuitem" href="<?= e($hrefFor('clothing', 'mens-wear')) ?>" class="<?= ($currentCategorySlug === 'clothing' && $currentSubcat === 'mens-wear') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Mens wear
            </a>
            <a role="menuitem" href="<?= e($hrefFor('clothing', 'jewellery')) ?>" class="<?= ($currentCategorySlug === 'clothing' && $currentSubcat === 'jewellery') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Jewellery
            </a>
          </div>
        <?php elseif ($slug === 'sports'): ?>
          <div class="subcategory-menu" role="menu" aria-label="Sports &amp; Fitness subcategories">
            <a role="menuitem" href="<?= e($hrefFor('sports', 'sports-shoes')) ?>" class="<?= ($currentCategorySlug === 'sports' && $currentSubcat === 'sports-shoes') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Sports shoes
            </a>
            <a role="menuitem" href="<?= e($hrefFor('sports', 'jerseys')) ?>" class="<?= ($currentCategorySlug === 'sports' && $currentSubcat === 'jerseys') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Jerseys
            </a>
            <a role="menuitem" href="<?= e($hrefFor('sports', 'gym-shoes')) ?>" class="<?= ($currentCategorySlug === 'sports' && $currentSubcat === 'gym-shoes') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Gym shoes
            </a>
          </div>
        <?php elseif ($slug === 'electronics'): ?>
          <div class="subcategory-menu" role="menu" aria-label="Electronics subcategories">
            <a role="menuitem" href="<?= e($hrefFor('electronics', 'phones-accessories')) ?>" class="<?= ($currentCategorySlug === 'electronics' && $currentSubcat === 'phones-accessories') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Phones & Accessories
            </a>
            <a role="menuitem" href="<?= e($hrefFor('electronics', 'laptops-computers')) ?>" class="<?= ($currentCategorySlug === 'electronics' && $currentSubcat === 'laptops-computers') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Laptops & Computers
            </a>
            <a role="menuitem" href="<?= e($hrefFor('electronics', 'tvs-home-audio')) ?>" class="<?= ($currentCategorySlug === 'electronics' && $currentSubcat === 'tvs-home-audio') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> TVs & Home Audio
            </a>
            <a role="menuitem" href="<?= e($hrefFor('electronics', 'gaming-consoles')) ?>" class="<?= ($currentCategorySlug === 'electronics' && $currentSubcat === 'gaming-consoles') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Gaming & Consoles
            </a>
            <a role="menuitem" href="<?= e($hrefFor('electronics', 'cameras-photography')) ?>" class="<?= ($currentCategorySlug === 'electronics' && $currentSubcat === 'cameras-photography') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Cameras & Photography
            </a>
            <a role="menuitem" href="<?= e($hrefFor('electronics', 'speakers-headphones')) ?>" class="<?= ($currentCategorySlug === 'electronics' && $currentSubcat === 'speakers-headphones') ? 'active' : '' ?>">
              <i class="fas fa-angle-right"></i> Speakers & Headphones
            </a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
