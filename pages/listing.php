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
        Database::query('UPDATE listings SET status="sold" WHERE id=? AND seller_id=?', [$id, $me['id']]);
        flash('success', 'Marked as sold. It will no longer appear on the home page.');
        redirect('/pages/listing.php?id=' . $id);
      }
    } elseif ($action === 'mark_active') {
      if (!$isSeller) {
        $actionError = 'Unauthorized action.';
      } elseif ($listing['status'] !== 'sold') {
        $actionError = 'Only sold listings can be restored.';
      } else {
        Database::query('UPDATE listings SET status="active" WHERE id=? AND seller_id=?', [$id, $me['id']]);
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

<div class="container" style="padding-top:28px">
  <a href="<?= APP_URL ?>/index.php" style="color:var(--muted);font-size:14px">← Back to listings</a>

  <?php if ($actionError): ?>
    <div class="alert alert-danger mt-4"><?= e($actionError) ?></div>
  <?php endif; ?>
  <?php if ($msgSent): ?>
    <div class="alert alert-success mt-4">Message sent to seller!</div>
  <?php endif; ?>
  <?php if ($reportSent): ?>
    <div class="alert alert-success mt-4">Report submitted. Thank you!</div>
  <?php endif; ?>

  <div class="detail-layout">

    <!-- Left: images + description -->
    <div>
      <div class="detail-main-img" id="main-img">
        <?php if (!empty($images)): ?>
          <img src="<?= APP_URL.'/public/'.e($images[0]['image_path']) ?>" alt="<?= e($listing['title']) ?>">
        <?php else: ?>
          📦
        <?php endif; ?>
      </div>

      <?php if (count($images) > 1): ?>
        <div class="thumb-row">
          <?php foreach ($images as $i => $img): ?>
            <div class="thumb <?= $i===0?'active':'' ?>" data-src="<?= APP_URL.'/public/'.e($img['image_path']) ?>">
              <img src="<?= APP_URL.'/public/'.e($img['image_path']) ?>" alt="">
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="margin-top:28px">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:10px">Description</h2>
        <p style="font-size:14px;line-height:1.8;color:#444;white-space:pre-wrap"><?= e($listing['description']) ?></p>
      </div>

      <!-- Reviews -->
      <?php if (!empty($reviews)): ?>
        <div style="margin-top:28px">
          <h2 style="font-size:16px;font-weight:700;margin-bottom:14px">Seller Reviews</h2>
          <?php foreach ($reviews as $r): ?>
            <div class="card" style="margin-bottom:10px">
              <div class="card-body">
                <div class="flex-between" style="margin-bottom:6px">
                  <span style="font-weight:700;font-size:14px"><?= e($r['reviewer_name']) ?></span>
                  <span><?= stars($r['rating']) ?></span>
                </div>
                <?php if ($r['comment']): ?>
                  <p style="font-size:14px;line-height:1.6"><?= e($r['comment']) ?></p>
                <?php endif; ?>
                <p class="text-muted" style="font-size:12px;margin-top:4px"><?= timeAgo($r['created_at']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right: action panel -->
    <div>
      <!-- Price + details -->
      <div class="detail-panel">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
          <span class="badge badge-gray"><?= e($listing['cat_name']) ?></span>
          <?= conditionBadge($listing['condition_type']) ?>
          <?php if ($listing['status'] !== 'active'): ?>
            <span class="badge badge-red"><?= strtoupper($listing['status']) ?></span>
          <?php endif; ?>
          <?php if ($listing['is_featured']): ?>
            <span class="badge badge-amber">⭐ Featured</span>
          <?php endif; ?>
        </div>

        <h1 class="detail-title"><?= e($listing['title']) ?></h1>
        <div class="detail-price"><?= formatPrice($listing['price']) ?></div>

        <div class="detail-meta">
          <span>📍 <?= e($listing['location']) ?></span>
          <span>👁 <?= number_format($listing['view_count']) ?> views</span>
          <span>🕐 <?= timeAgo($listing['created_at']) ?></span>
        </div>

        <?php if ($listing['status'] === 'active' && !$isSeller): ?>
          <?php if (!$me): ?>
            <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-primary btn-full">
              Login to Contact Seller
            </a>
          <?php else: ?>
            <a class="btn btn-primary btn-full" style="margin-bottom:10px"
               href="<?= APP_URL ?>/pages/messages.php?listing=<?= $id ?>&with=<?= $listing['seller_id'] ?>">
              💬 Chat with Seller
            </a>
            <form method="POST" style="margin-bottom:10px">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="save">
              <button type="submit" class="btn btn-outline btn-full">
                <?= $isSaved ? '❤️ Saved' : 'Save Listing' ?>
              </button>
            </form>
          <?php endif; ?>
        <?php elseif ($isSeller): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <a href="<?= APP_URL ?>/pages/edit_listing.php?id=<?= $id ?>" class="btn btn-outline" style="flex:1">Edit</a>
              <?php if ($listing['status'] === 'active'): ?>
                <form method="POST" style="margin:0">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="mark_sold">
                  <button type="submit" class="btn btn-primary btn-sm">✓ Mark as Sold</button>
                </form>
              <?php elseif ($listing['status'] === 'sold'): ?>
                <form method="POST" style="margin:0">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="mark_active">
                  <button type="submit" class="btn btn-outline btn-sm">↩ Undo Sold</button>
                </form>
              <?php endif; ?>
              <a href="<?= APP_URL ?>/pages/delete_listing.php?id=<?= $id ?>"
                 class="btn btn-danger btn-sm"
                 data-confirm="Are you sure you want to delete this listing?">🗑 Delete</a>
            </div>
        <?php endif; ?>
      </div>

      <!-- Seller -->
      <div class="seller-card" style="margin-bottom:16px">
        <p style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:14px">SELLER</p>
        <div class="flex-gap" style="margin-bottom:10px">
          <div class="seller-card-avatar">
            <?php if (!empty($listing['seller_avatar'])): ?>
              <img src="<?= APP_URL . '/public/' . e($listing['seller_avatar']) ?>" alt="<?= e($listing['seller_name']) ?>">
            <?php else: ?>
              <?= strtoupper(substr($listing['seller_name'],0,1)) ?>
            <?php endif; ?>
          </div>
          <div>
            <a href="<?= APP_URL ?>/pages/user_profile.php?id=<?= $listing['seller_id'] ?>"
               style="font-weight:700;font-size:15px;color:var(--primary)">
              <?= e($listing['seller_name']) ?>
            </a>
            <p style="font-size:12px;color:var(--muted)">
              <?= e($listing['seller_course'] ?? '') ?>
              <?= $listing['year_of_study'] ? ' · Year '.$listing['year_of_study'] : '' ?>
            </p>
          </div>
        </div>
        <div class="flex-gap">
          <span class="stars"><?= stars($listing['trust_score']) ?></span>
          <span style="font-weight:700;font-size:13px"><?= $listing['trust_score'] ?></span>
          <span class="text-muted" style="font-size:12px">(<?= $listing['total_reviews'] ?> reviews)</span>
        </div>
      </div>

      <!-- Report -->
      <?php if ($me && !$isSeller): ?>
        <button class="btn btn-ghost btn-sm" style="color:var(--muted);background:transparent;border:none;text-decoration:underline;cursor:pointer;font-size:13px"
                data-modal-open="modal-report">
           Report this listing
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

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
