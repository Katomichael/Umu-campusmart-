<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/index.php');

$listing = Database::fetchOne(
    "SELECT l.*, c.name AS cat_name, c.slug AS cat_slug,
            u.id AS seller_id, u.full_name AS seller_name, u.email AS seller_email,
            u.phone AS seller_phone, u.trust_score, u.total_reviews,
            u.avatar AS seller_avatar, u.course AS seller_course, u.year_of_study
     FROM listings l
     JOIN categories c ON l.category_id = c.id
     JOIN users u ON l.seller_id = u.id
     WHERE l.id = ?",
    [$id]
);
if (!$listing) redirect('/index.php');

$me = currentUser();
$isAdmin  = $me && $me['role'] === 'admin';
$isSeller = $me && $me['id'] == $listing['seller_id'];

// Only approved (active) listings are public.
if ($listing['status'] !== 'active' && !$isSeller && !$isAdmin) {
    redirect('/index.php');
}

// Increment view count (public listings only)
if ($listing['status'] === 'active' && !$isSeller && !$isAdmin) {
    Database::query('UPDATE listings SET view_count = view_count + 1 WHERE id = ?', [$id]);
}

$images = Database::fetchAll(
    'SELECT image_path, is_primary FROM listing_images WHERE listing_id = ? ORDER BY sort_order',
    [$id]
);

$reviews = Database::fetchAll(
    "SELECT r.*, u.full_name AS reviewer_name, l.title AS listing_title
     FROM reviews r JOIN users u ON r.reviewer_id = u.id
     LEFT JOIN listings l ON r.listing_id = l.id
     WHERE r.reviewed_id = ? ORDER BY r.created_at DESC LIMIT 5",
    [$listing['seller_id']]
);

// Fetch categories for sidebar
try {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
} catch (Throwable $e) {
    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY name');
}

// Fetch related products in the same category
$relatedListings = Database::fetchAll(
    "SELECT l.id, l.title, l.price, l.condition_type, l.view_count, l.is_featured, l.created_at,
            c.name AS cat_name, c.slug AS cat_slug,
            u.id AS seller_id, u.full_name AS seller_name, u.trust_score, u.avatar AS seller_avatar,
            (SELECT image_path FROM listing_images WHERE listing_id=l.id AND is_primary=1 LIMIT 1) AS img
     FROM listings l
     JOIN categories c ON l.category_id = c.id
     JOIN users u ON l.seller_id = u.id
     WHERE l.status='active' AND l.category_id=? AND l.id!=? 
     ORDER BY l.is_featured DESC, l.created_at DESC
     LIMIT 6",
    [$listing['category_id'], $id]
);

$isSaved  = false;
if ($me) {
    $saved = Database::fetchOne(
        'SELECT 1 FROM saved_listings WHERE user_id=? AND listing_id=?', [$me['id'], $id]
    );
    $isSaved = (bool)$saved;
}

// Handle POST actions
$msgSent    = false;
$reportSent = false;
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me) {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $actionError = 'Invalid request.';
    } elseif ($action === 'mark_sold') {
      if (!$isSeller) {
        $actionError = 'Unauthorized action.';
      } elseif ($listing['status'] !== 'active') {
        $actionError = 'This listing is already not active.';
      } else {
        Database::query('UPDATE listings SET status=? WHERE id=? AND seller_id=?', ['sold', $id, $me['id']]);
        flash('success', 'Marked as sold. It will no longer appear on the home page.');
        redirect('/pages/listing.php?id=' . $id);
      }
    } elseif ($action === 'mark_active') {
      if (!$isSeller) {
        $actionError = 'Unauthorized action.';
      } elseif ($listing['status'] !== 'sold') {
        $actionError = 'Only sold listings can be restored.';
      } else {
        Database::query('UPDATE listings SET status=? WHERE id=? AND seller_id=?', ['active', $id, $me['id']]);
        flash('success', 'Listing restored and visible on the home page again.');
        redirect('/pages/listing.php?id=' . $id);
      }
    } elseif ($action === 'message') {
        $content = trim($_POST['content'] ?? '');
        if ($listing['status'] !== 'active') {
            $actionError = 'This listing is not available for messaging.';
        } elseif ($content && !$isSeller) {
            Database::insert(
                'INSERT INTO messages (listing_id, sender_id, receiver_id, content) VALUES (?,?,?,?)',
                [$id, $me['id'], $listing['seller_id'], $content]
            );
            header('Location: ' . APP_URL . '/pages/messages.php?listing=' . $id . '&with=' . $listing['seller_id']);
            exit;
        }
    } elseif ($action === 'save') {
        if ($isSaved) {
            Database::query('DELETE FROM saved_listings WHERE user_id=? AND listing_id=?', [$me['id'], $id]);
            $isSaved = false;
        } else {
            Database::query('INSERT IGNORE INTO saved_listings (user_id, listing_id) VALUES (?,?)', [$me['id'], $id]);
            $isSaved = true;
        }
    } elseif ($action === 'report') {
        $reason = $_POST['reason'] ?? 'other';
        $desc   = trim($_POST['description'] ?? '');
        Database::insert(
            'INSERT INTO reports (reporter_id, listing_id, reason, description) VALUES (?,?,?,?)',
            [$me['id'], $id, $reason, $desc ?: null]
        );
        $reportSent = true;
    }
}

$pageTitle = $listing['title'];
include __DIR__ . '/../includes/header.php';
?>

<style>
.related-products-section {
  margin-top: 48px;
  padding-top: 32px;
  border-top: 2px solid var(--border);
}

.related-products-title {
  font-size: 24px;
  font-weight: 800;
  color: #e74c3c;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  letter-spacing: -0.3px;
}

.related-products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 18px;
}

.related-card {
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

.related-card:hover {
  border-color: var(--accent);
  box-shadow: 0 12px 32px rgba(0,0,0,0.12);
  transform: translateY(-6px);
}

.related-card-img {
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

.related-card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.related-card-body {
  padding: 14px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.related-card-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 8px;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.related-card-price {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary);
  margin-bottom: 8px;
  letter-spacing: -0.2px;
}

.related-card-meta {
  font-size: 11px;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: auto;
  padding-top: 8px;
  border-top: 1px solid var(--border);
}

@media (max-width: 768px) {
  .related-products-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
  }

  .related-products-title {
    font-size: 18px;
    margin-bottom: 18px;
  }

  .related-card-img {
    height: 120px;
    font-size: 28px;
  }
}
</style>


<!-- Main Layout with Sidebar -->
<div class="main-layout" style="max-width:1400px;margin:0 auto;padding:20px">
  <a href="<?= APP_URL ?>/index.php" style="color:var(--muted);font-size:14px;display:block;margin-bottom:20px">← Back to listings</a>

  <?php if ($actionError): ?>
    <div class="alert alert-danger"><?= e($actionError) ?></div>
  <?php endif; ?>
  <?php if ($msgSent): ?>
    <div class="alert alert-success">Message sent to seller!</div>
  <?php endif; ?>
  <?php if ($reportSent): ?>
    <div class="alert alert-success">Report submitted. Thank you!</div>
  <?php endif; ?>

  <div class="listing-main-container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <?php
        $currentCategorySlug = (string)($listing['cat_slug'] ?? '');
        $currentSubcat = '';
        include __DIR__ . '/../includes/categories_sidebar_categories.php';
      ?>
    </aside>

    <!-- Main Content -->
    <div class="main-content" style="flex:1">
      <!-- Featured Listing -->
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:28px;margin-bottom:32px;box-shadow:var(--shadow)">
        <div class="featured-listing-container">
          <!-- Left: Images -->
          <div class="featured-listing-images">
            <div class="featured-listing-main" id="main-img">
              <?php if (!empty($images)): ?>
                <img src="<?= APP_URL.'/public/'.e($images[0]['image_path']) ?>" alt="<?= e($listing['title']) ?>">
              <?php else: ?>
                <div style="font-size:48px;color:#ccc"><i class="fas fa-box"></i></div>
              <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
              <div class="featured-listing-thumbs">
                <?php foreach ($images as $i => $img): ?>
                  <div class="thumb <?= $i===0?'active':'' ?>" data-src="<?= APP_URL.'/public/'.e($img['image_path']) ?>">
                    <img src="<?= APP_URL.'/public/'.e($img['image_path']) ?>" alt="">
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Middle: Details -->
          <div class="featured-listing-details">
            <div style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap">
              <span class="badge badge-gray"><?= e($listing['cat_name']) ?></span>
              <?= conditionBadge($listing['condition_type']) ?>
              <?php if ($listing['status'] !== 'active'): ?>
                <span class="badge badge-red"><?= strtoupper($listing['status']) ?></span>
              <?php endif; ?>
              <?php if ($listing['is_featured']): ?>
                <span class="badge badge-amber">⭐ Featured</span>
              <?php endif; ?>
            </div>

            <h1 style="font-size:24px;font-weight:800;color:#222;margin-bottom:12px"><?= e($listing['title']) ?></h1>
            <div style="font-size:32px;font-weight:800;color:var(--primary);margin-bottom:18px"><?= formatPrice($listing['price']) ?></div>

            <div class="listing-detail-meta">
              <div class="meta-item">
                <strong style="color:var(--muted);font-size:12px">LOCATION</strong>
                <div style="font-size:14px;font-weight:700"><?= e($listing['location']) ?></div>
              </div>
              <div class="meta-item">
                <strong style="color:var(--muted);font-size:12px">CONDITION</strong>
                <div style="font-size:14px;font-weight:700"><?= ucfirst(str_replace('_', ' ', $listing['condition_type'])) ?></div>
              </div>
              <div class="meta-item">
                <strong style="color:var(--muted);font-size:12px">POSTED</strong>
                <div style="font-size:14px;font-weight:700"><?= timeAgo($listing['created_at']) ?></div>
              </div>
            </div>

            <hr style="margin:18px 0;border:none;border-top:1px solid var(--border)">

            <div style="font-size:14px;line-height:1.8;color:#555;margin-bottom:20px">
              <?= nl2br(e(substr($listing['description'], 0, 300))) ?><?= strlen($listing['description']) > 300 ? '...' : '' ?>
            </div>

            <?php if ($listing['status'] === 'active' && !$isSeller): ?>
              <?php if (!$me): ?>
                <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-primary btn-full">
                  <i class="fas fa-sign-in-alt"></i> Login to Contact Seller
                </a>
              <?php else: ?>
                <a class="btn btn-primary btn-full" style="margin-bottom:10px"
                   href="<?= APP_URL ?>/pages/messages.php?listing=<?= $id ?>&with=<?= $listing['seller_id'] ?>">
                  <i class="fas fa-comments"></i> Chat with Seller
                </a>
                <form method="POST" style="margin-bottom:10px">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="save">
                  <button type="submit" class="btn btn-outline btn-full">
                    <?= $isSaved ? '❤️ Saved' : '📌 Save Listing' ?>
                  </button>
                </form>
              <?php endif; ?>
            <?php elseif ($isSeller): ?>
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/pages/edit_listing.php?id=<?= $id ?>" class="btn btn-outline" style="flex:1">✏️ Edit</a>
                <?php if ($listing['status'] === 'active'): ?>
                  <form method="POST" style="margin:0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_sold">
                    <button type="submit" class="btn btn-primary btn-sm">✓ Mark as Sold</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Right: Seller Info -->
          <div class="featured-listing-seller">
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px">
              <p style="font-size:11px;font-weight:800;color:var(--muted);margin-bottom:16px">SELLER</p>
              
              <div style="display:flex;gap:12px;margin-bottom:18px;align-items:center">
                <div class="seller-avatar-large">
                  <?php if (!empty($listing['seller_avatar'])): ?>
                    <img src="<?= APP_URL . '/public/' . e($listing['seller_avatar']) ?>" alt="<?= e($listing['seller_name']) ?>">
                  <?php else: ?>
                    <?= strtoupper(substr($listing['seller_name'],0,1)) ?>
                  <?php endif; ?>
                </div>
                <div>
                  <a href="<?= APP_URL ?>/pages/user_profile.php?id=<?= $listing['seller_id'] ?>"
                     style="font-weight:800;font-size:16px;color:var(--primary);text-decoration:none">
                    <?= e($listing['seller_name']) ?>
                  </a>
                  <p style="font-size:12px;color:var(--muted);margin-top:4px">
                    <?php if ($listing['seller_course']): ?>
                      <?= e($listing['seller_course']) ?><br>
                    <?php endif; ?>
                    <?php if ($listing['year_of_study']): ?>
                      Year <?= $listing['year_of_study'] ?>
                    <?php endif; ?>
                  </p>
                </div>
              </div>

              <?php if ($me && !$isSeller): ?>
                <button class="btn btn-outline btn-full" style="padding:10px;font-size:13px;color:#e74c3c"
                        data-modal-open="modal-report">
                  <i class="fas fa-flag"></i> Report
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Similar Adverts -->
      <?php if (!empty($relatedListings)): ?>
      <div>
        <h2 style="font-size:20px;font-weight:800;color:#222;margin-bottom:20px">
          Similar Adverts
        </h2>
        <div class="related-products-grid">
          <?php foreach ($relatedListings as $rel): ?>
            <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $rel['id'] ?>" class="related-card">
              <div class="related-card-img">
                <?php if ($rel['img']): ?>
                  <img src="<?= APP_URL.'/public/'.e($rel['img']) ?>" alt="<?= e($rel['title']) ?>">
                <?php else: ?>
                  <i class="fas fa-box"></i>
                <?php endif; ?>
              </div>
              <div class="related-card-body">
                <div class="related-card-title"><?= e($rel['title']) ?></div>
                <div class="related-card-price"><?= formatPrice($rel['price']) ?></div>
                <div class="related-card-meta">
                  <i class="fas fa-eye"></i> <?= number_format($rel['view_count']) ?>
                  <span style="margin-left: auto; font-weight: 600;"><?= conditionBadge($rel['condition_type']) ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.listing-main-container {
  display: flex;
  gap: 24px;
}

.featured-listing-container {
  display: grid;
  grid-template-columns: 1fr 1fr 300px;
  gap: 24px;
  align-items: start;
}

.featured-listing-images {
  background: #f5f5f5;
  border-radius: 12px;
  overflow: hidden;
}

.featured-listing-main {
  width: 100%;
  aspect-ratio: 4/3;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #eee;
  position: relative;
  overflow: hidden;
}

.featured-listing-main img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.featured-listing-thumbs {
  display: flex;
  gap: 8px;
  padding: 12px;
  overflow-x: auto;
}

.featured-listing-thumbs .thumb {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: border-color 0.2s;
}

.featured-listing-thumbs .thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.featured-listing-thumbs .thumb.active {
  border-color: var(--primary);
}

.featured-listing-details {
  padding: 0;
}

.listing-detail-meta {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-top: 16px;
}

.meta-item {
  padding: 12px;
  background: #f9f9f9;
  border-radius: 8px;
}

.featured-listing-seller {
  position: sticky;
  top: 20px;
}

.seller-avatar-large {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 800;
  overflow: hidden;
  flex-shrink: 0;
}

.seller-avatar-large img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

@media (max-width: 1024px) {
  .featured-listing-container {
    grid-template-columns: 1fr 1fr;
  }

  .featured-listing-seller {
    grid-column: 1 / -1;
    position: relative;
    top: 0;
  }
}

@media (max-width: 768px) {
  .listing-main-container {
    flex-direction: column;
  }

  .featured-listing-container {
    grid-template-columns: 1fr;
  }

  .sidebar {
    display: none;
  }
}

@media (max-width: 480px) {
  .featured-listing-container { gap: 16px; }

  .featured-listing-thumbs {
    padding: 10px;
    gap: 6px;
  }

  .featured-listing-thumbs .thumb {
    width: 52px;
    height: 52px;
  }

  .listing-detail-meta {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }

  .meta-item { padding: 10px; }
}
</style>

<!-- Report Modal -->
<?php if ($me && !$isSeller): ?>
<div class="modal-overlay" id="modal-report" style="display:none">
  <div class="modal-box">
    <div class="modal-header">
      <h2 class="modal-title">Report Listing</h2>
      <button class="modal-close" data-modal-close>×</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="report">
      <div class="form-group">
        <label>Reason</label>
        <select class="form-control" name="reason">
          <option value="spam">Spam</option>
          <option value="fraud">Fraud / Scam</option>
          <option value="inappropriate">Inappropriate Content</option>
          <option value="prohibited">Prohibited Item</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Details (optional)</label>
        <textarea class="form-control" name="description" rows="3"
                  placeholder="Describe the issue..."></textarea>
      </div>
      <button type="submit" class="btn btn-danger btn-full">Submit Report</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
