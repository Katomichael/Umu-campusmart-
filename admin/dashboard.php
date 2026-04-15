<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

// Stats
$stats = [
    'students'  => Database::fetchOne('SELECT COUNT(*) AS n FROM users WHERE role="student"')['n'],
    'listings'  => Database::fetchOne('SELECT COUNT(*) AS n FROM listings')['n'],
    'active'    => Database::fetchOne('SELECT COUNT(*) AS n FROM listings WHERE status="active"')['n'],
    'messages'  => Database::fetchOne('SELECT COUNT(*) AS n FROM messages')['n'],
    'reports'   => Database::fetchOne('SELECT COUNT(*) AS n FROM reports WHERE status="pending"')['n'],
];

$recentUsers = Database::fetchAll(
  'SELECT id, full_name, email, student_id, course, is_banned, created_at
     FROM users WHERE role="student" ORDER BY created_at DESC LIMIT 10'
);

$recentListings = Database::fetchAll(
    "SELECT l.id, l.title, l.price, l.status, l.created_at,
            u.full_name AS seller_name, c.name AS cat_name
     FROM listings l
     JOIN users u ON l.seller_id=u.id
     JOIN categories c ON l.category_id=c.id
     ORDER BY l.created_at DESC LIMIT 10"
);

$pendingReports = Database::fetchAll(
    "SELECT r.*,
            reporter.full_name AS reporter_name,
            reported_u.full_name AS reported_user_name,
            l.title AS listing_title
     FROM reports r
     JOIN users reporter ON r.reporter_id=reporter.id
     LEFT JOIN users reported_u ON r.reported_user_id=reported_u.id
     LEFT JOIN listings l ON r.listing_id=l.id
     WHERE r.status='pending'
     ORDER BY r.created_at DESC"
);

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

  if ($action === 'delete_user') {
      $uid = (int)($_POST['user_id'] ?? 0);
      $me  = currentUser();

      if ($uid && (!$me || $uid !== (int)$me['id'])) {
        $target = Database::fetchOne('SELECT id, role, avatar FROM users WHERE id=?', [$uid]);
        // Only allow deleting student accounts from this UI.
        if ($target && $target['role'] === 'student') {
          // Collect uploaded files to clean up (DB cascades won't remove files).
          $paths = [];
          if (!empty($target['avatar'])) {
            $paths[] = (string)$target['avatar'];
          }

          $imgRows = Database::fetchAll(
            'SELECT li.image_path
             FROM listing_images li
             JOIN listings l ON l.id = li.listing_id
             WHERE l.seller_id = ?',
            [$uid]
          );
          foreach ($imgRows as $r) {
            if (!empty($r['image_path'])) $paths[] = (string)$r['image_path'];
          }

          // Delete user (cascades will remove dependent rows)
          Database::query('DELETE FROM users WHERE id=? AND role="student"', [$uid]);

          // Best-effort file cleanup under UPLOAD_DIR only.
          $uploadRoot = realpath(UPLOAD_DIR);
          $publicRoot = realpath(__DIR__ . '/../public');
          if ($uploadRoot && $publicRoot) {
            $uploadRoot = rtrim($uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            foreach (array_unique($paths) as $rel) {
              if (!is_string($rel) || $rel === '') continue;
              if (!str_starts_with($rel, 'uploads/')) continue;
              $full = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
              if ($full && str_starts_with($full, $uploadRoot)) @unlink($full);
            }
          }
        }
      }
    } elseif ($action === 'remove_listing') {
        $lid = (int)$_POST['listing_id'];
        Database::query('UPDATE listings SET status="removed" WHERE id=?', [$lid]);
    } elseif ($action === 'feature_listing') {
        $lid     = (int)$_POST['listing_id'];
        $current = Database::fetchOne('SELECT is_featured FROM listings WHERE id=?', [$lid]);
        if ($current) {
            Database::query('UPDATE listings SET is_featured=? WHERE id=?',
                [!$current['is_featured'], $lid]);
        }
    } elseif ($action === 'resolve_report') {
        $rid    = (int)$_POST['report_id'];
        $status = $_POST['status'] ?? 'resolved';
        if (in_array($status, ['resolved','dismissed','reviewed'])) {
            Database::query(
                'UPDATE reports SET status=?, resolved_at=NOW() WHERE id=?',
                [$status, $rid]
            );
        }
    }

    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding:28px 16px">

  <div class="admin-header">
    <div>
      <h1 class="page-title"> Admin Dashboard</h1>
      <p class="page-sub"><?= UNIVERSITY_NAME ?> — CampusMart Management</p>
    </div>
    <?php if ($stats['reports'] > 0): ?>
      <span class="badge badge-red" style="font-size:14px;padding:8px 16px">
         <?= $stats['reports'] ?> pending report<?= $stats['reports']!=1?'s':'' ?>
      </span>
    <?php endif; ?>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d4edda"></div>
      <div><div class="stat-value"><?= number_format($stats['students']) ?></div><div class="stat-label">Students</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#cce5ff"></div>
      <div><div class="stat-value"><?= number_format($stats['listings']) ?></div><div class="stat-label">Total Listings</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#d4edda"></div>
      <div><div class="stat-value"><?= number_format($stats['active']) ?></div><div class="stat-label">Active Listings</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#cce5ff"></div>
      <div><div class="stat-value"><?= number_format($stats['messages']) ?></div><div class="stat-label">Messages</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f8d7da"></div>
      <div><div class="stat-value"><?= number_format($stats['reports']) ?></div><div class="stat-label">Pending Reports</div></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn" data-tab="users"> Users</button>
    <button class="tab-btn" data-tab="listings"> Listings</button>
    <button class="tab-btn" data-tab="reports">Reports (<?= $stats['reports'] ?>)</button>
  </div>

  <!-- Users tab -->
  <div id="tab-users" class="tab-pane">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th><th>Email</th><th>Student ID</th>
            <th>Course</th><th>Status</th><th>Joined</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/pages/user_profile.php?id=<?= $u['id'] ?>"
                   style="color:var(--primary);font-weight:600"><?= e($u['full_name']) ?></a>
              </td>
              <td style="font-size:12px"><?= e($u['email']) ?></td>
              <td style="font-size:12px"><?= e($u['student_id'] ?? '—') ?></td>
              <td style="font-size:12px"><?= e($u['course'] ?? '—') ?></td>
              <td>
                <span class="badge <?= $u['is_banned'] ? 'badge-red' : 'badge-green' ?>">
                  <?= $u['is_banned'] ? 'Banned' : 'Active' ?>
                </span>
              </td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="delete_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"
                          data-confirm="Delete this user permanently? This will remove their listings, messages, reviews and saved items.">
                    Delete
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Listings tab -->
  <div id="tab-listings" class="tab-pane">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Title</th><th>Seller</th><th>Price</th>
            <th>Category</th><th>Status</th><th>Date</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentListings as $l): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $l['id'] ?>"
                   style="color:var(--primary);font-weight:600" target="_blank">
                  <?= e($l['title']) ?>
                </a>
              </td>
              <td><?= e($l['seller_name']) ?></td>
              <td><?= formatPrice($l['price']) ?></td>
              <td><?= e($l['cat_name']) ?></td>
              <td>
                <span class="badge <?= $l['status']==='active' ? 'badge-green' : 'badge-gray' ?>">
                  <?= $l['status'] ?>
                </span>
              </td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <!-- Feature -->
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"     value="feature_listing">
                  <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline"></button>
                </form>
                <!-- Remove -->
                <?php if ($l['status'] !== 'removed'): ?>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"     value="remove_listing">
                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"
                            data-confirm="Remove this listing?">Remove</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Reports tab -->
  <div id="tab-reports" class="tab-pane">
    <?php if (empty($pendingReports)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No pending reports</h3>
        <p>All reports have been resolved</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Reporter</th><th>Type</th><th>Reason</th>
              <th>Target</th><th>Description</th><th>Date</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingReports as $r): ?>
              <tr>
                <td><?= e($r['reporter_name']) ?></td>
                <td>
                  <span class="badge badge-blue">
                    <?= $r['listing_id'] ? 'Listing' : 'User' ?>
                  </span>
                </td>
                <td><?= e($r['reason']) ?></td>
                <td style="font-size:12px">
                  <?= e($r['listing_title'] ?? $r['reported_user_name'] ?? '—') ?>
                </td>
                <td style="font-size:12px;max-width:180px">
                  <?= e(mb_strimwidth($r['description'] ?? '', 0, 60, '…')) ?>
                </td>
                <td style="font-size:12px"><?= timeAgo($r['created_at']) ?></td>
                <td style="display:flex;gap:6px">
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"    value="resolve_report">
                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="status"    value="resolved">
                    <button type="submit" class="btn btn-sm btn-primary">Resolve</button>
                  </form>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"    value="resolve_report">
                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="status"    value="dismissed">
                    <button type="submit" class="btn btn-sm btn-outline">Dismiss</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
