<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

// Stats
$stats = [
    'students'  => Database::fetchOne('SELECT COUNT(*) AS n FROM users WHERE role="student"')['n'],
    'listings'  => Database::fetchOne('SELECT COUNT(*) AS n FROM listings')['n'],
    'pending'   => Database::fetchOne('SELECT COUNT(*) AS n FROM listings WHERE status="pending"')['n'],
    'active'    => Database::fetchOne('SELECT COUNT(*) AS n FROM listings WHERE status="active"')['n'],
    'messages'  => Database::fetchOne('SELECT COUNT(*) AS n FROM messages')['n'],
    'reports'   => Database::fetchOne('SELECT COUNT(*) AS n FROM reports WHERE status="pending"')['n'],
];

$userQ = trim($_GET['user_q'] ?? '');
$userWhere  = 'role="student"';
$userParams = [];
if ($userQ !== '') {
  $userWhere .= ' AND (full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)';
  $like = '%' . $userQ . '%';
  $userParams = [$like, $like, $like];
}

$recentUsers = Database::fetchAll(
  "SELECT id, full_name, email, student_id, course, is_banned, is_verified, created_at
     FROM users
     WHERE $userWhere
     ORDER BY created_at DESC
     LIMIT 25",
  $userParams
);

$listingQ = trim($_GET['listing_q'] ?? '');
$listingStatus = trim($_GET['listing_status'] ?? '');
$listingWhere  = ['1=1'];
$listingParams = [];

if ($listingQ !== '') {
  $listingWhere[] = '(l.title LIKE ? OR u.full_name LIKE ?)';
  $like = '%' . $listingQ . '%';
  $listingParams[] = $like;
  $listingParams[] = $like;
}

$allowedStatuses = ['pending','active','rejected','sold','reserved','removed'];
if (in_array($listingStatus, $allowedStatuses, true)) {
  $listingWhere[] = 'l.status = ?';
  $listingParams[] = $listingStatus;
}

$whereSql = 'WHERE ' . implode(' AND ', $listingWhere);

$recentListings = Database::fetchAll(
    "SELECT l.id, l.title, l.price, l.status, l.is_featured, l.created_at,
            u.full_name AS seller_name, c.name AS cat_name
     FROM listings l
     JOIN users u ON l.seller_id=u.id
     JOIN categories c ON l.category_id=c.id
     $whereSql
     ORDER BY l.created_at DESC
     LIMIT 25",
    $listingParams
);

$pendingListings = Database::fetchAll(
  "SELECT l.id, l.title, l.price, l.created_at,
          u.full_name AS seller_name, c.name AS cat_name
   FROM listings l
   JOIN users u ON l.seller_id=u.id
   JOIN categories c ON l.category_id=c.id
   WHERE l.status='pending'
   ORDER BY l.created_at DESC
   LIMIT 25"
);

try {
  $allCategories = Database::fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
} catch (Throwable $e) {
  $allCategories = Database::fetchAll('SELECT * FROM categories ORDER BY name');
}

try {
  $auditLogs = Database::fetchAll(
    "SELECT a.*, u.full_name AS admin_name
     FROM admin_audit_logs a
     JOIN users u ON a.admin_id = u.id
     ORDER BY a.created_at DESC
     LIMIT 50"
  );
} catch (Throwable $e) {
  $auditLogs = [];
}

$pendingReports = Database::fetchAll(
    "SELECT r.*,
            reporter.full_name AS reporter_name,
            reported_u.full_name AS reported_user_name,
            reported_u.is_banned AS reported_user_banned,
            l.title AS listing_title,
            l.seller_id AS listing_seller_id,
            seller_u.full_name AS listing_seller_name,
            seller_u.is_banned AS listing_seller_banned
     FROM reports r
     JOIN users reporter ON r.reporter_id=reporter.id
     LEFT JOIN users reported_u ON r.reported_user_id=reported_u.id
     LEFT JOIN listings l ON r.listing_id=l.id
     LEFT JOIN users seller_u ON l.seller_id = seller_u.id
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
          adminAuditLog('user.delete', 'user', $uid);

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
    } elseif ($action === 'toggle_ban') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $me  = currentUser();

        if ($uid && $me && $uid !== (int)$me['id']) {
          $target = Database::fetchOne('SELECT id, role, is_banned FROM users WHERE id=?', [$uid]);
          if ($target && $target['role'] === 'student') {
            $new = $target['is_banned'] ? 0 : 1;
            Database::query('UPDATE users SET is_banned=? WHERE id=? AND role="student"', [$new, $uid]);
            adminAuditLog($new ? 'user.ban' : 'user.unban', 'user', $uid);
          }
        }
    } elseif ($action === 'toggle_verify') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
          $target = Database::fetchOne('SELECT id, role, is_verified FROM users WHERE id=?', [$uid]);
          if ($target && $target['role'] === 'student') {
            $new = $target['is_verified'] ? 0 : 1;
            Database::query('UPDATE users SET is_verified=? WHERE id=? AND role="student"', [$new, $uid]);
            adminAuditLog($new ? 'user.verify' : 'user.unverify', 'user', $uid);
          }
        }
    } elseif ($action === 'set_password') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $me  = currentUser();
        $p1  = (string)($_POST['password'] ?? '');
        $p2  = (string)($_POST['password_confirm'] ?? '');

        if ($uid && $me && $uid !== (int)$me['id'] && $p1 !== '' && hash_equals($p1, $p2) && strlen($p1) >= 8) {
          $target = Database::fetchOne('SELECT id, role FROM users WHERE id=?', [$uid]);
          if ($target && $target['role'] === 'student') {
            Database::query('UPDATE users SET password_hash=? WHERE id=?', [hashPassword($p1), $uid]);
            adminAuditLog('user.password_set', 'user', $uid);
          }
        }
    } elseif ($action === 'approve_listing') {
        $lid = (int)($_POST['listing_id'] ?? 0);
        if ($lid) {
          Database::query('UPDATE listings SET status="active" WHERE id=? AND status IN ("pending","rejected")', [$lid]);
          adminAuditLog('listing.approve', 'listing', $lid);
        }
    } elseif ($action === 'reject_listing') {
        $lid = (int)($_POST['listing_id'] ?? 0);
        if ($lid) {
          Database::query('UPDATE listings SET status="rejected" WHERE id=? AND status="pending"', [$lid]);
          adminAuditLog('listing.reject', 'listing', $lid);
        }
    } elseif ($action === 'create_category') {
        $name = trim((string)($_POST['name'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $sort = (int)($_POST['sort_order'] ?? 0);

        if ($name !== '') {
          $base = strtolower($name);
          $slug = preg_replace('~[^a-z0-9]+~', '-', $base);
          $slug = trim((string)$slug, '-');
          if ($slug === '') $slug = 'category';

          $candidate = $slug;
          for ($i = 2; $i <= 30; $i++) {
            $exists = Database::fetchOne('SELECT 1 FROM categories WHERE slug=? LIMIT 1', [$candidate]);
            if (!$exists) break;
            $candidate = $slug . '-' . $i;
          }
          $slug = $candidate;

          try {
            $id = Database::insert(
              'INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (?,?,?,?,?)',
              [$name, $slug, $icon ?: null, $desc ?: null, $sort]
            );
          } catch (Throwable $e) {
            $id = Database::insert(
              'INSERT INTO categories (name, slug, icon, description) VALUES (?,?,?,?)',
              [$name, $slug, $icon ?: null, $desc ?: null]
            );
          }

          adminAuditLog('category.create', 'category', (int)$id, ['slug' => $slug]);
        }
    } elseif ($action === 'update_categories') {
        $orders = $_POST['sort_order'] ?? [];
        if (is_array($orders)) {
          foreach ($orders as $cid => $order) {
            $cid = (int)$cid;
            $order = (int)$order;
            if ($cid <= 0) continue;
            try {
              Database::query('UPDATE categories SET sort_order=? WHERE id=?', [$order, $cid]);
            } catch (Throwable $e) {
              // ignore (migration not applied)
            }
          }
          adminAuditLog('category.reorder', 'category', null);
        }
    } elseif ($action === 'update_report_note') {
        $rid  = (int)($_POST['report_id'] ?? 0);
        $note = trim((string)($_POST['admin_note'] ?? ''));
        if ($rid) {
          Database::query('UPDATE reports SET admin_note=? WHERE id=?', [$note ?: null, $rid]);
          adminAuditLog('report.note', 'report', $rid);
        }
    } elseif ($action === 'remove_listing') {
        $lid = (int)$_POST['listing_id'];
        Database::query('UPDATE listings SET status="removed" WHERE id=?', [$lid]);
        adminAuditLog('listing.remove', 'listing', $lid);
    } elseif ($action === 'feature_listing') {
        $lid     = (int)$_POST['listing_id'];
        $current = Database::fetchOne('SELECT is_featured FROM listings WHERE id=?', [$lid]);
        if ($current) {
            $new = !$current['is_featured'];
            Database::query('UPDATE listings SET is_featured=? WHERE id=?',
                [$new, $lid]);
            adminAuditLog($new ? 'listing.feature' : 'listing.unfeature', 'listing', $lid);
        }
    } elseif ($action === 'resolve_report') {
        $rid    = (int)$_POST['report_id'];
        $status = $_POST['status'] ?? 'resolved';
        if (in_array($status, ['resolved','dismissed','reviewed'])) {
            Database::query(
                'UPDATE reports SET status=?, resolved_at=NOW() WHERE id=?',
                [$status, $rid]
            );
            adminAuditLog('report.update_status', 'report', $rid, ['status' => $status]);
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
      <div class="stat-icon" style="background:#fff3cd"></div>
      <div><div class="stat-value"><?= number_format($stats['pending']) ?></div><div class="stat-label">Pending Listings</div></div>
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
    <button class="tab-btn" data-tab="moderation">Moderation (<?= (int)$stats['pending'] ?>)</button>
    <button class="tab-btn" data-tab="listings"> Listings</button>
    <button class="tab-btn" data-tab="categories"> Categories</button>
    <button class="tab-btn" data-tab="reports">Reports (<?= (int)$stats['reports'] ?>)</button>
    <button class="tab-btn" data-tab="audit"> Audit Log</button>
  </div>

  <!-- Users tab -->
  <div id="tab-users" class="tab-pane">

    <form method="GET" class="card" style="margin-bottom:14px">
      <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input class="form-control" name="user_q" value="<?= e($userQ) ?>"
               placeholder="Search users (name, email, student ID)…" style="max-width:360px">
        <button type="submit" class="btn btn-sm btn-primary">Search</button>
        <?php if ($userQ !== ''): ?>
          <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-sm btn-outline">Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th><th>Email</th><th>Student ID</th>
            <th>Course</th><th>Status</th><th>Joined</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/pages/user_profile.php?id=<?= $u['id'] ?>"
                   style="color:var(--primary);font-weight:600" target="_blank"><?= e($u['full_name']) ?></a>
              </td>
              <td style="font-size:12px"><?= e($u['email']) ?></td>
              <td style="font-size:12px"><?= e($u['student_id'] ?? '—') ?></td>
              <td style="font-size:12px"><?= e($u['course'] ?? '—') ?></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <span class="badge <?= $u['is_banned'] ? 'badge-red' : 'badge-green' ?>">
                  <?= $u['is_banned'] ? 'Banned' : 'Active' ?>
                </span>
                <span class="badge <?= $u['is_verified'] ? 'badge-blue' : 'badge-gray' ?>">
                  <?= $u['is_verified'] ? 'Verified' : 'Unverified' ?>
                </span>
              </td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">

                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="toggle_ban">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $u['is_banned'] ? 'btn-outline' : 'btn-danger' ?>"
                          data-confirm="<?= $u['is_banned'] ? 'Unban this user?' : 'Ban this user?' ?>">
                    <?= $u['is_banned'] ? 'Unban' : 'Ban' ?>
                  </button>
                </form>

                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="toggle_verify">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $u['is_verified'] ? 'btn-outline' : 'btn-primary' ?>">
                    <?= $u['is_verified'] ? 'Unverify' : 'Verify' ?>
                  </button>
                </form>

                <button type="button" class="btn btn-sm btn-outline" data-modal-open="modal-pass-<?= $u['id'] ?>">
                  Set Password
                </button>

                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="delete_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"
                          data-confirm="Delete this user permanently? This will remove their listings, messages, reviews and saved items.">
                    Delete
                  </button>
                </form>

                <!-- Password Modal -->
                <div class="modal-overlay" id="modal-pass-<?= $u['id'] ?>" style="display:none">
                  <div class="modal-box">
                    <div class="modal-header">
                      <h2 class="modal-title">Set New Password</h2>
                      <button class="modal-close" data-modal-close>×</button>
                    </div>
                    <form method="POST">
                      <?= csrfField() ?>
                      <input type="hidden" name="action"  value="set_password">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

                      <div class="form-group">
                        <label>New password (min 8 chars)</label>
                        <input class="form-control" type="password" name="password" minlength="8" required>
                      </div>
                      <div class="form-group">
                        <label>Confirm password</label>
                        <input class="form-control" type="password" name="password_confirm" minlength="8" required>
                      </div>

                      <button type="submit" class="btn btn-primary btn-full">Save</button>
                    </form>
                  </div>
                </div>

              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Moderation tab -->
  <div id="tab-moderation" class="tab-pane">
    <?php if (empty($pendingListings)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No pending listings</h3>
        <p>All submissions have been reviewed</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th><th>Seller</th><th>Price</th><th>Category</th><th>Date</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingListings as $l): ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $l['id'] ?>" target="_blank"
                     style="color:var(--primary);font-weight:600">
                    <?= e($l['title']) ?>
                  </a>
                </td>
                <td><?= e($l['seller_name']) ?></td>
                <td><?= formatPrice($l['price']) ?></td>
                <td><?= e($l['cat_name']) ?></td>
                <td style="font-size:12px"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap">
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve_listing">
                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                  </form>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reject_listing">
                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline">Reject</button>
                  </form>
                  <a class="btn btn-sm btn-outline" href="<?= APP_URL ?>/pages/edit_listing.php?id=<?= $l['id'] ?>" target="_blank">Edit</a>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="remove_listing">
                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="Remove this listing?">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Listings tab -->
  <div id="tab-listings" class="tab-pane">

    <form method="GET" class="card" style="margin-bottom:14px">
      <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input class="form-control" name="listing_q" value="<?= e($listingQ) ?>"
               placeholder="Search listings (title or seller)…" style="max-width:360px">
        <select class="form-control" name="listing_status" style="max-width:200px">
          <option value="">All statuses</option>
          <?php foreach (['pending','active','rejected','sold','reserved','removed'] as $st): ?>
            <option value="<?= e($st) ?>" <?= $listingStatus === $st ? 'selected' : '' ?>><?= e($st) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <?php if ($listingQ !== '' || $listingStatus !== ''): ?>
          <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-sm btn-outline">Clear</a>
        <?php endif; ?>
      </div>
    </form>

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
                <?php
                  $badgeCls = 'badge-gray';
                  if ($l['status'] === 'active') $badgeCls = 'badge-green';
                  elseif ($l['status'] === 'pending') $badgeCls = 'badge-amber';
                  elseif ($l['status'] === 'rejected' || $l['status'] === 'removed') $badgeCls = 'badge-red';
                ?>
                <span class="badge <?= $badgeCls ?>"><?= e($l['status']) ?></span>
              </td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">

                <?php if ($l['status'] === 'pending'): ?>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve_listing">
                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                  </form>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reject_listing">
                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline">Reject</button>
                  </form>
                <?php endif; ?>

                <!-- Feature -->
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"     value="feature_listing">
                  <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline">
                    <?= $l['is_featured'] ? 'Unfeature' : 'Feature' ?>
                  </button>
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

  <!-- Categories tab -->
  <div id="tab-categories" class="tab-pane">

    <div class="card" style="margin-bottom:14px">
      <div class="card-body">
        <h3 style="margin:0 0 12px;font-size:14px;font-weight:800">Add Category</h3>
        <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="create_category">
          <div class="form-group" style="margin:0">
            <label>Name</label>
            <input class="form-control" name="name" placeholder="e.g. Gadgets" required>
          </div>
          <div class="form-group" style="margin:0;max-width:120px">
            <label>Icon</label>
            <input class="form-control" name="icon" placeholder="💡">
          </div>
          <div class="form-group" style="margin:0;max-width:140px">
            <label>Order</label>
            <input class="form-control" type="number" name="sort_order" value="0">
          </div>
          <div class="form-group" style="margin:0;min-width:240px;flex:1">
            <label>Description</label>
            <input class="form-control" name="description" placeholder="Optional">
          </div>
          <button type="submit" class="btn btn-sm btn-primary">Create</button>
        </form>
      </div>
    </div>

    <div class="table-wrap">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_categories">

        <table>
          <thead>
            <tr>
              <th>Name</th><th>Slug</th><th style="width:120px">Order</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allCategories as $c): ?>
              <tr>
                <td><?= e(($c['icon'] ?? '') . ' ' . ($c['name'] ?? '')) ?></td>
                <td style="font-size:12px"><?= e($c['slug'] ?? '') ?></td>
                <td>
                  <input class="form-control" type="number" name="sort_order[<?= (int)$c['id'] ?>]"
                         value="<?= e((string)($c['sort_order'] ?? 0)) ?>">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="padding:12px;display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-sm btn-primary">Save Order</button>
        </div>
      </form>
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
                  <?php if (!empty($r['listing_id'])): ?>
                    <div><?= e($r['listing_title'] ?? '—') ?></div>
                    <div class="text-muted" style="font-size:12px">
                      Seller: <?= e($r['listing_seller_name'] ?? '—') ?>
                    </div>
                  <?php else: ?>
                    <?= e($r['reported_user_name'] ?? '—') ?>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;max-width:180px">
                  <?= e(mb_strimwidth($r['description'] ?? '', 0, 60, '…')) ?>
                </td>
                <td style="font-size:12px"><?= timeAgo($r['created_at']) ?></td>
                <td style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">

                  <form method="POST" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"    value="update_report_note">
                    <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                    <input class="form-control" name="admin_note" value="<?= e($r['admin_note'] ?? '') ?>"
                           placeholder="Admin note…" style="min-width:220px">
                    <button type="submit" class="btn btn-sm btn-outline">Save note</button>
                  </form>

                  <?php if (!empty($r['listing_id'])): ?>
                    <a class="btn btn-sm btn-outline" href="<?= APP_URL ?>/admin/messages.php?report_id=<?= (int)$r['id'] ?>" target="_blank">
                      View messages
                    </a>
                    <?php if (!empty($r['listing_id'])): ?>
                      <form method="POST" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="remove_listing">
                        <input type="hidden" name="listing_id" value="<?= (int)$r['listing_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Remove this listing?">Remove listing</button>
                      </form>
                    <?php endif; ?>
                    <?php if (!empty($r['listing_seller_id'])): ?>
                      <form method="POST" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_ban">
                        <input type="hidden" name="user_id" value="<?= (int)$r['listing_seller_id'] ?>">
                        <?php $sellerBanned = !empty($r['listing_seller_banned']); ?>
                        <button type="submit" class="btn btn-sm <?= $sellerBanned ? 'btn-outline' : 'btn-danger' ?>"
                                data-confirm="<?= $sellerBanned ? 'Unban this seller?' : 'Ban this seller?' ?>">
                          <?= $sellerBanned ? 'Unban seller' : 'Ban seller' ?>
                        </button>
                      </form>
                    <?php endif; ?>
                  <?php elseif (!empty($r['reported_user_id'])): ?>
                    <form method="POST" style="display:inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="toggle_ban">
                      <input type="hidden" name="user_id" value="<?= (int)$r['reported_user_id'] ?>">
                      <?php $userBanned = !empty($r['reported_user_banned']); ?>
                      <button type="submit" class="btn btn-sm <?= $userBanned ? 'btn-outline' : 'btn-danger' ?>"
                              data-confirm="<?= $userBanned ? 'Unban this user?' : 'Ban this user?' ?>">
                        <?= $userBanned ? 'Unban user' : 'Ban user' ?>
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"    value="resolve_report">
                    <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="status"    value="resolved">
                    <button type="submit" class="btn btn-sm btn-primary">Resolve</button>
                  </form>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"    value="resolve_report">
                    <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
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

  <!-- Audit log tab -->
  <div id="tab-audit" class="tab-pane">
    <?php if (empty($auditLogs)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No audit logs</h3>
        <p>Run the migration, then admin actions will appear here</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Admin</th><th>Action</th><th>Entity</th><th>IP</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($auditLogs as $a): ?>
              <tr>
                <td><?= e($a['admin_name'] ?? '') ?></td>
                <td style="font-size:12px"><code><?= e($a['action'] ?? '') ?></code></td>
                <td style="font-size:12px">
                  <?= e(($a['entity_type'] ?? '') . ($a['entity_id'] ? ' #' . $a['entity_id'] : '')) ?>
                </td>
                <td style="font-size:12px"><?= e($a['ip_address'] ?? '—') ?></td>
                <td style="font-size:12px"><?= timeAgo($a['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
