<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) redirect('/index.php');

$user = Database::fetchOne(
    'SELECT id, full_name, course, year_of_study, bio, trust_score, total_reviews, avatar, created_at
     FROM users WHERE id = ? AND is_banned = 0',
    [$userId]
);
if (!$user) redirect('/index.php');

$listings = Database::fetchAll(
    "SELECT l.*, c.name AS cat_name,
            (SELECT image_path FROM listing_images WHERE listing_id=l.id AND is_primary=1 LIMIT 1) AS img
     FROM listings l JOIN categories c ON l.category_id=c.id
     WHERE l.seller_id=? AND l.status='active' ORDER BY l.created_at DESC",
    [$userId]
);

$reviews = Database::fetchAll(
    "SELECT r.*, u.full_name AS reviewer_name, l.title AS listing_title
     FROM reviews r JOIN users u ON r.reviewer_id=u.id
     LEFT JOIN listings l ON r.listing_id=l.id
     WHERE r.reviewed_id=? ORDER BY r.created_at DESC",
    [$userId]
);

// Leave a review form (must be logged in and not reviewing yourself)
$me = currentUser();
$canReview = $me && $me['id'] != $userId;
$reviewError = '';
$reviewSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canReview && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $rating  = (int)($_POST['rating']  ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $reviewError = 'Please select a rating between 1 and 5.';
    } else {
        try {
            Database::insert(
                'INSERT INTO reviews (reviewer_id, reviewed_id, rating, comment) VALUES (?,?,?,?)',
                [$me['id'], $userId, $rating, $comment ?: null]
            );
            // Recalculate trust score
            $stats = Database::fetchOne(
                'SELECT AVG(rating) AS avg_r, COUNT(*) AS total FROM reviews WHERE reviewed_id=?',
                [$userId]
            );
            Database::query(
                'UPDATE users SET trust_score=?, total_reviews=? WHERE id=?',
                [round($stats['avg_r'], 2), $stats['total'], $userId]
            );
            $reviewSuccess = 'Review submitted!';
            // Reload reviews
            $reviews = Database::fetchAll(
                "SELECT r.*, u.full_name AS reviewer_name, l.title AS listing_title
                 FROM reviews r JOIN users u ON r.reviewer_id=u.id
                 LEFT JOIN listings l ON r.listing_id=l.id
                 WHERE r.reviewed_id=? ORDER BY r.created_at DESC",
                [$userId]
            );
        } catch (\PDOException $e) {
            $reviewError = 'You have already reviewed this user.';
        }
    }
}

$pageTitle = $user['full_name'] . "'s Profile";
include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width:900px;padding:28px 16px">
  <a href="javascript:history.back()" style="color:var(--muted);font-size:14px">← Back</a>

  <!-- Profile header -->
  <div class="profile-header" style="margin-top:16px">
    <div class="profile-avatar">
      <?php if ($user['avatar']): ?>
        <img src="<?= APP_URL.'/public/'.e($user['avatar']) ?>" alt=""
             style="width:100%;height:100%;border-radius:50%;object-fit:cover">
      <?php else: ?>
        <?= strtoupper(substr($user['full_name'],0,1)) ?>
      <?php endif; ?>
    </div>
    <div class="profile-info">
      <div class="profile-name"><?= e($user['full_name']) ?></div>
      <p class="text-muted" style="font-size:14px;margin-bottom:6px">
        <?= e($user['course'] ?? '') ?>
        <?= $user['year_of_study'] ? ' · Year '.$user['year_of_study'] : '' ?>
        · UMU
      </p>
      <?php if ($user['bio']): ?>
        <p style="font-size:14px;margin-bottom:8px"><?= e($user['bio']) ?></p>
      <?php endif; ?>
      <div class="flex-gap">
        <span class="stars"><?= stars($user['trust_score']) ?></span>
        <strong><?= $user['trust_score'] ?></strong>
        <span class="text-muted" style="font-size:13px">(<?= $user['total_reviews'] ?> reviews)</span>
      </div>
    </div>
    <?php if ($me && $me['id'] != $userId): ?>
      <a href="<?= APP_URL ?>/pages/messages.php" class="btn btn-outline btn-sm">
        💬 Message
      </a>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn" data-tab="listings"> Listings (<?= count($listings) ?>)</button>
    <button class="tab-btn" data-tab="reviews">Reviews (<?= count($reviews) ?>)</button>
    <?php if ($canReview): ?>
      <button class="tab-btn" data-tab="review-form">Leave Review</button>
    <?php endif; ?>
  </div>

  <!-- Listings -->
  <div id="tab-listings" class="tab-pane">
    <?php if (empty($listings)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No active listings</h3>
        <p>This user hasn't listed anything yet</p>
      </div>
    <?php else: ?>
      <div class="listings-grid">
        <?php foreach ($listings as $l): ?>
          <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $l['id'] ?>" class="listing-card">
            <div class="listing-card-img">
              <?php if ($l['img']): ?>
                <img src="<?= APP_URL.'/public/'.e($l['img']) ?>" alt="">
              <?php else: ?><?php endif; ?>
            </div>
            <div class="listing-card-body">
              <div class="listing-card-header">
                <div class="listing-card-title"><?= e($l['title']) ?></div>
                <?= conditionBadge($l['condition_type']) ?>
              </div>
              <div class="listing-card-price"><?= formatPrice($l['price']) ?></div>
              <div class="listing-card-meta">
                <span><?= e($l['cat_name']) ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Reviews -->
  <div id="tab-reviews" class="tab-pane">
    <?php if (empty($reviews)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No reviews yet</h3>
      </div>
    <?php else: ?>
      <?php foreach ($reviews as $r): ?>
        <div class="card" style="margin-bottom:10px">
          <div class="card-body">
            <div class="flex-between" style="margin-bottom:6px">
              <div>
                <span style="font-weight:700;font-size:14px"><?= e($r['reviewer_name']) ?></span>
                <?php if ($r['listing_title']): ?>
                  <span class="text-muted" style="font-size:12px"> · <?= e($r['listing_title']) ?></span>
                <?php endif; ?>
              </div>
              <div class="flex-gap">
                <span class="stars"><?= stars($r['rating']) ?></span>
                <span class="text-muted" style="font-size:12px"><?= timeAgo($r['created_at']) ?></span>
              </div>
            </div>
            <?php if ($r['comment']): ?>
              <p style="font-size:14px"><?= e($r['comment']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Leave review -->
  <?php if ($canReview): ?>
    <div id="tab-review-form" class="tab-pane">
      <?php if ($reviewSuccess): ?><div class="alert alert-success"><?= e($reviewSuccess) ?></div><?php endif; ?>
      <?php if ($reviewError): ?><div class="alert alert-danger"><?= e($reviewError) ?></div><?php endif; ?>

      <div class="card" style="max-width:480px">
        <div class="card-body">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">
            Review <?= e($user['full_name']) ?>
          </h3>
          <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
              <label>Rating *</label>
              <select class="form-control" name="rating" required>
                <option value="">Select rating</option>
                <option value="5"> Excellent</option>
                <option value="4"> Good</option>
                <option value="3">Average</option>
                <option value="2"> Below Average</option>
                <option value="1">Poor</option>
              </select>
            </div>
            <div class="form-group">
              <label>Comment (optional)</label>
              <textarea class="form-control" name="comment" rows="3"
                        placeholder="Share your experience…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Review</button>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
