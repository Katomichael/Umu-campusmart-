<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$me = currentUser();
$errors  = [];
$success = getFlash('success');

// Save profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $full_name     = trim($_POST['full_name']     ?? '');
    $course        = trim($_POST['course']        ?? '');
    $year_of_study = (int)($_POST['year_of_study']?? 1);
    $phone         = trim($_POST['phone']         ?? '');
    $bio           = trim($_POST['bio']           ?? '');

    if (!$full_name) $errors[] = 'Full name is required.';

    $avatar = null;
    if (!empty($_FILES['avatar']['name'])) {
        $avatar = uploadImage($_FILES['avatar'], 'avatars');
        if (!$avatar) $errors[] = 'Invalid avatar image (max 5MB, jpg/png/gif/webp).';
    }

    if (!$errors) {
        $sql = 'UPDATE users SET full_name=?, course=?, year_of_study=?, phone=?, bio=?';
        $p   = [$full_name, $course ?: null, $year_of_study, $phone ?: null, $bio ?: null];
        if ($avatar) { $sql .= ', avatar=?'; $p[] = $avatar; }
        $sql .= ' WHERE id=?';
        $p[]  = $me['id'];
        Database::query($sql, $p);
        flash('success', 'Profile updated!');
        redirect('/pages/profile.php');
    }
}

// Reload fresh data
$user = Database::fetchOne(
    'SELECT * FROM users WHERE id=?', [$me['id']]
);
$myListings = Database::fetchAll(
    "SELECT l.*, c.name AS cat_name,
            (SELECT image_path FROM listing_images WHERE listing_id=l.id AND is_primary=1 LIMIT 1) AS img
     FROM listings l JOIN categories c ON l.category_id=c.id
     WHERE l.seller_id=? ORDER BY l.created_at DESC",
    [$me['id']]
);
$myReviews = Database::fetchAll(
    "SELECT r.*, u.full_name AS reviewer_name, l.title AS listing_title
     FROM reviews r JOIN users u ON r.reviewer_id=u.id
     LEFT JOIN listings l ON r.listing_id=l.id
     WHERE r.reviewed_id=? ORDER BY r.created_at DESC",
    [$me['id']]
);

$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width:1400px;padding:28px 16px">
  <div class="layout-with-sidebar">
    <aside class="sidebar">
      <?php
        $currentCategorySlug = '';
        $currentSubcat = '';
        include __DIR__ . '/../includes/categories_sidebar_categories.php';
      ?>
    </aside>

    <div style="flex:1;max-width:900px">
      <h1 class="page-title"> Profile</h1>

  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>

  <!-- Profile header -->
  <div class="profile-header">
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
      </p>
      <?php if ($user['bio']): ?>
        <p style="font-size:14px;margin-bottom:8px"><?= e($user['bio']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn" data-tab="listings"> Listings (<?= count($myListings) ?>)</button>
    <button class="tab-btn" data-tab="reviews">Reviews (<?= count($myReviews) ?>)</button>
    <button class="tab-btn" data-tab="edit">Edit Profile</button>
  </div>

  <!-- Listings tab -->
  <div id="tab-listings" class="tab-pane">
    <?php if (empty($myListings)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No listings yet</h3>
        <p>Start selling — <a href="<?= APP_URL ?>/pages/create_listing.php">post your first item!</a></p>
      </div>
    <?php else: ?>
      <div class="listings-grid">
        <?php foreach ($myListings as $l): ?>
          <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $l['id'] ?>" class="listing-card">
            <div class="listing-card-img">
              <?php if ($l['img']): ?>
                <img src="<?= APP_URL.'/public/'.e($l['img']) ?>" alt="">
              <?php else: ?>📦<?php endif; ?>
            </div>
            <div class="listing-card-body">
              <div class="listing-card-header">
                <div class="listing-card-title"><?= e($l['title']) ?></div>
                <?= conditionBadge($l['condition_type']) ?>
              </div>
              <div class="listing-card-price"><?= formatPrice($l['price']) ?></div>
              <div class="listing-card-meta">
                <span><?= e($l['cat_name']) ?></span>
                <span class="badge <?= $l['status']==='active'?'badge-green':'badge-gray' ?>">
                  <?= $l['status'] ?>
                </span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Reviews tab -->
  <div id="tab-reviews" class="tab-pane">
    <?php if (empty($myReviews)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No reviews yet</h3>
        <p>Reviews from buyers and sellers will appear here</p>
      </div>
    <?php else: ?>
      <?php foreach ($myReviews as $r): ?>
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

  <!-- Edit profile tab -->
  <div id="tab-edit" class="tab-pane">
    <form method="POST" enctype="multipart/form-data" class="card">
      <div class="card-body">
        <?= csrfField() ?>
        <div class="form-grid">
          <div class="form-group span-2">
            <label>Full Name *</label>
            <input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Course</label>
            <input class="form-control" name="course" value="<?= e($user['course'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Year of Study</label>
            <select class="form-control" name="year_of_study">
              <?php for ($y=1;$y<=5;$y++): ?>
                <option value="<?= $y ?>" <?= $user['year_of_study']==$y?'selected':'' ?>>Year <?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Profile Photo</label>
            <input type="file" name="avatar" accept="image/*" style="font-size:14px;padding:8px 0">
          </div>
          <div class="form-group span-2">
            <label>Bio</label>
            <textarea class="form-control" name="bio" rows="3"><?= e($user['bio'] ?? '') ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
