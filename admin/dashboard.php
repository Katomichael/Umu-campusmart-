<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

// Stats
$stats = [
  'students'  => Database::fetchOne('SELECT COUNT(*) AS n FROM users WHERE role=?', ['student'])['n'],
    'listings'  => Database::fetchOne('SELECT COUNT(*) AS n FROM listings')['n'],
  'pending'   => Database::fetchOne('SELECT COUNT(*) AS n FROM listings WHERE status=?', ['pending'])['n'],
  'active'    => Database::fetchOne('SELECT COUNT(*) AS n FROM listings WHERE status=?', ['active'])['n'],
    'messages'  => Database::fetchOne('SELECT COUNT(*) AS n FROM messages')['n'],
  'reports'   => Database::fetchOne('SELECT COUNT(*) AS n FROM reports WHERE status=?', ['pending'])['n'],
];

$userQ = trim($_GET['user_q'] ?? '');
$userWhere  = 'role=?';
$userParams = ['student'];
if ($userQ !== '') {
  $userWhere .= ' AND (full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)';
  $like = '%' . $userQ . '%';
  array_push($userParams, $like, $like, $like);
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
  WHERE (l.status='pending' OR l.status IS NULL OR l.status='')
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

$allReviews = Database::fetchAll(
    "SELECT r.*,
            reviewer.full_name AS reviewer_name,
            reviewed.full_name AS reviewed_name,
            l.title AS listing_title
     FROM reviews r
     JOIN users reviewer ON r.reviewer_id=reviewer.id
     JOIN users reviewed ON r.reviewed_id=reviewed.id
     LEFT JOIN listings l ON r.listing_id=l.id
     ORDER BY r.created_at DESC
     LIMIT 100"
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
          Database::query('DELETE FROM users WHERE id=? AND role=?', [$uid, 'student']);
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
    } elseif ($action === 'toggle_verify') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
          $target = Database::fetchOne('SELECT id, role, is_verified FROM users WHERE id=?', [$uid]);
          if ($target && $target['role'] === 'student') {
            $new = $target['is_verified'] ? 0 : 1;
            Database::query('UPDATE users SET is_verified=? WHERE id=? AND role=?', [$new, $uid, 'student']);
            adminAuditLog($new ? 'user.verify' : 'user.unverify', 'user', $uid);
          }
        }
    } elseif ($action === 'approve_listing') {
        $lid = (int)($_POST['listing_id'] ?? 0);
        if ($lid) {
          Database::query(
            'UPDATE listings SET status=? WHERE id=? AND status IN (?,?)',
            ['active', $lid, 'pending', 'rejected']
          );
          adminAuditLog('listing.approve', 'listing', $lid);
        }
    } elseif ($action === 'reject_listing') {
        $lid = (int)($_POST['listing_id'] ?? 0);
        if ($lid) {
          Database::query('UPDATE listings SET status=? WHERE id=? AND status=?', ['rejected', $lid, 'pending']);
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
      Database::query('UPDATE listings SET status=? WHERE id=?', ['removed', $lid]);
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

<style>
/* ── Admin Dashboard Enhanced Styles ──────────────────────────── */
.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  gap: 24px;
  flex-wrap: wrap;
}

.admin-header h1 {
  font-size: 32px;
  font-weight: 900;
  margin: 0 0 6px 0;
  letter-spacing: -0.5px;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.admin-header p {
  margin: 0;
  color: var(--muted);
  font-size: 14px;
}

/* Enhanced Stat Grid */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}

.stat-card {
  background: linear-gradient(135deg, var(--surface) 0%, rgba(245,166,35,0.02) 100%);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
  border: 1px solid var(--border);
  transition: all 0.3s ease;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 16px;
  align-items: center;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(0,0,0,0.12);
  border-color: var(--accent);
}

.stat-card.urgent {
  background: linear-gradient(135deg, #fff5f5 0%, rgba(231,76,60,0.05) 100%);
  border-color: rgba(231,76,60,0.3);
}

.stat-card.success {
  background: linear-gradient(135deg, #f0fdf4 0%, rgba(34,197,94,0.05) 100%);
  border-color: rgba(34,197,94,0.3);
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-icon.students {
  background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
  color: #155724;
}

.stat-icon.listings {
  background: linear-gradient(135deg, #cce5ff 0%, #b3d9ff 100%);
  color: #004085;
}

.stat-icon.pending {
  background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
  color: #856404;
}

.stat-icon.active {
  background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
  color: #155724;
}

.stat-icon.messages {
  background: linear-gradient(135deg, #cce5ff 0%, #b3d9ff 100%);
  color: #004085;
}

.stat-icon.reports {
  background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
  color: #721c24;
}

.stat-content {
  min-width: 0;
}

.stat-value {
  font-size: 28px;
  font-weight: 900;
  margin: 0;
  line-height: 1.2;
  letter-spacing: -0.3px;
}

.stat-label {
  font-size: 12px;
  color: var(--muted);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}

.stat-change {
  font-size: 12px;
  font-weight: 700;
  margin-top: 6px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.stat-change.positive {
  color: #22c55e;
}

.stat-change.negative {
  color: #ef4444;
}

/* Quick Actions Panel */
.quick-actions {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  border-radius: 14px;
  padding: 28px;
  margin-bottom: 32px;
  color: #fff;
  box-shadow: 0 8px 24px rgba(151,14,14,0.2);
}

.quick-actions h3 {
  font-size: 16px;
  font-weight: 800;
  margin: 0 0 18px 0;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  display: flex;
  align-items: center;
  gap: 8px;
  opacity: 0.95;
}

.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
}

.action-btn {
  background: rgba(255,255,255,0.15);
  border: 1.5px solid rgba(255,255,255,0.3);
  color: #fff;
  padding: 14px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.2s ease;
  text-align: center;
  white-space: nowrap;
}

.action-btn:hover {
  background: rgba(255,255,255,0.25);
  border-color: rgba(255,255,255,0.5);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.15);
}

/* Enhanced Admin Layout */
.admin-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
  align-items: start;
}

.admin-sidebar {
  background: var(--surface);
  border-radius: 12px;
  box-shadow: var(--shadow);
  padding: 12px;
  border: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: sticky;
  top: 80px;
}

.tab-btn {
  text-align: left;
  padding: 12px 14px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--text);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
  white-space: nowrap;
}

.tab-btn::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: transparent;
  border-radius: 10px 0 0 10px;
  transition: all 0.2s ease;
}

.tab-btn:hover {
  background: rgba(245,166,35,0.08);
  color: var(--primary);
}

.tab-btn.active {
  background: linear-gradient(135deg, rgba(151,14,14,0.12) 0%, rgba(245,166,35,0.08) 100%);
  color: var(--primary);
  font-weight: 800;
}

.tab-btn.active::before {
  background: var(--primary);
}

.tab-btn-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  background: #e74c3c;
  color: #fff;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 800;
  margin-left: auto;
  flex-shrink: 0;
}

/* Enhanced Tables */
.table-wrap {
  background: var(--surface);
  border-radius: 12px;
  box-shadow: var(--shadow);
  overflow-x: auto;
  border: 1px solid var(--border);
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

th {
  padding: 14px 16px;
  text-align: left;
  font-weight: 700;
  font-size: 12px;
  color: var(--muted);
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--border);
  white-space: nowrap;
  background: linear-gradient(135deg, rgba(151,14,14,0.02) 0%, rgba(245,166,35,0.02) 100%);
  text-transform: uppercase;
}

td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}

tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  background: linear-gradient(135deg, rgba(245,166,35,0.05) 0%, rgba(151,14,14,0.03) 100%);
}

tbody tr:last-child td {
  border-bottom: none;
}

@media (max-width: 768px) {
  .admin-layout {
    grid-template-columns: 1fr;
  }

  .admin-sidebar {
    display: flex;
    flex-direction: row;
    gap: 6px;
    overflow-x: auto;
    padding: 8px;
    position: static;
  }

  .tab-btn {
    flex-shrink: 0;
    padding: 10px 12px;
    font-size: 12px;
  }

  .stat-grid {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  }

  .quick-actions-grid {
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  }

  .admin-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
}
</style>

<div class="container" style="padding:28px 16px">

  <div class="admin-header">
    <div>
      <h1 class="page-title">Admin Dashboard</h1>
      <p class="page-sub">CampusMart Management</p>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card success">
      <div class="stat-icon students"><i class="fas fa-users"></i></div>
      <div class="stat-content">
        <div class="stat-value"><?= number_format($stats['students']) ?></div>
        <div class="stat-label">Students</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon listings"><i class="fas fa-th-list"></i></div>
      <div class="stat-content">
        <div class="stat-value"><?= number_format($stats['listings']) ?></div>
        <div class="stat-label">Total Listings</div>
      </div>
    </div>
    <div class="stat-card urgent">
      <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
      <div class="stat-content">
        <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        <div class="stat-label">Pending</div>
        <div class="stat-change negative"><i class="fas fa-arrow-up"></i> Needs Review</div>
      </div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon active"><i class="fas fa-check-circle"></i></div>
      <div class="stat-content">
        <div class="stat-value"><?= number_format($stats['active']) ?></div>
        <div class="stat-label">Active</div>
        <div class="stat-change positive"><i class="fas fa-arrow-up"></i> Live</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon messages"><i class="fas fa-comments"></i></div>
      <div class="stat-content">
        <div class="stat-value"><?= number_format($stats['messages']) ?></div>
        <div class="stat-label">Messages</div>
      </div>
    </div>
    <div class="stat-card urgent">
      <div class="stat-icon reports"><i class="fas fa-exclamation-circle"></i></div>
      <div class="stat-content">
        <div class="stat-value"><?= number_format($stats['reports']) ?></div>
        <div class="stat-label">Reports</div>
        <div class="stat-change negative"><i class="fas fa-exclamation"></i> Urgent</div>
      </div>
    </div>
  </div>

  <!-- Quick Actions Panel -->
  <div class="quick-actions">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="quick-actions-grid">
      <button class="action-btn" data-action="categories">
        <i class="fas fa-plus"></i> Create Category
      </button>
      <button class="action-btn" data-action="users">
        <i class="fas fa-ban"></i> Manage Users
      </button>
      <button class="action-btn" data-action="moderation">
        <i class="fas fa-check"></i> Review Listings
      </button>
      <button class="action-btn" data-action="reports">
        <i class="fas fa-flag"></i> View Reports
      </button>
    </div>
  </div>

  <div class="admin-layout">
    <aside class="admin-sidebar" aria-label="Admin navigation">
      <button class="tab-btn active" data-tab="users">
        <i class="fas fa-users"></i> Users
      </button>
      <button class="tab-btn" data-tab="moderation">
        <i class="fas fa-inbox"></i> Moderation
        <?php if ($stats['pending'] > 0): ?><span class="tab-btn-badge"><?= $stats['pending'] ?></span><?php endif; ?>
      </button>
      <button class="tab-btn" data-tab="listings">
        <i class="fas fa-th-list"></i> Listings
      </button>
      <button class="tab-btn" data-tab="categories">
        <i class="fas fa-tags"></i> Categories
      </button>
      <button class="tab-btn" data-tab="reports">
        <i class="fas fa-flag"></i> Reports
        <?php if ($stats['reports'] > 0): ?><span class="tab-btn-badge"><?= $stats['reports'] ?></span><?php endif; ?>
      </button>
      <button class="tab-btn" data-tab="reviews">
        <i class="fas fa-star"></i> Reviews
      </button>
      <button class="tab-btn" data-tab="audit">
        <i class="fas fa-history"></i> Audit Log
      </button>
    </aside>

    <section class="admin-main" aria-label="Admin content">

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
                  <input type="hidden" name="action"  value="toggle_verify">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $u['is_verified'] ? 'btn-outline' : 'btn-primary' ?>">
                    <?= $u['is_verified'] ? 'Unverify' : 'Verify' ?>
                  </button>
                </form>

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
                  <?php elseif (!empty($r['reported_user_id'])): ?>
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

  <!-- Reviews tab -->
  <div id="tab-reviews" class="tab-pane">
    <?php if (empty($allReviews)): ?>
      <div class="empty-state">
        <div class="empty-icon"></div>
        <h3>No reviews yet</h3>
        <p>Reviews from users will appear here</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Reviewer</th><th>Reviewed User</th><th>Listing</th><th>Rating</th><th>Comment</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allReviews as $rev): ?>
              <tr>
                <td><?= e($rev['reviewer_name']) ?></td>
                <td><?= e($rev['reviewed_name']) ?></td>
                <td style="font-size:12px;max-width:180px">
                  <?php if ($rev['listing_title']): ?>
                    <a href="<?= APP_URL ?>/pages/listing.php?id=<?= (int)$rev['listing_id'] ?>" target="_blank" style="color:var(--primary);text-decoration:none;font-weight:600">
                      <?= e(mb_strimwidth($rev['listing_title'], 0, 40, '…')) ?>
                    </a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td>
                  <span style="font-size:14px;color:var(--accent);font-weight:700">
                    <?php for ($i = 0; $i < $rev['rating']; $i++): ?>
                      ⭐
                    <?php endfor; ?>
                  </span>
                </td>
                <td style="font-size:12px;max-width:220px;color:var(--muted)">
                  <?= e(mb_strimwidth($rev['comment'] ?? '—', 0, 60, '…')) ?>
                </td>
                <td style="font-size:12px"><?= timeAgo($rev['created_at']) ?></td>
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

    </section>
  </div>

  <div class="admin-bottom-maroon" aria-hidden="true"></div>

</div>

<script>
(function() {
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabPanes = document.querySelectorAll('.tab-pane');
  const actionBtns = document.querySelectorAll('[data-action]');
  
  function showTab(tabName) {
    // Hide all panes
    tabPanes.forEach(pane => pane.style.display = 'none');
    
    // Remove active class from all buttons
    tabBtns.forEach(btn => btn.classList.remove('active'));
    
    // Show selected pane
    const selectedPane = document.getElementById('tab-' + tabName);
    if (selectedPane) {
      selectedPane.style.display = 'block';
    }
    
    // Add active class to clicked button
    const activeBtn = document.querySelector('[data-tab="' + tabName + '"]');
    if (activeBtn) {
      activeBtn.classList.add('active');
    }
  }
  
  // Add click listeners to tab buttons
  tabBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const tabName = this.getAttribute('data-tab');
      showTab(tabName);
    });
  });
  
  // Add click listeners to action buttons
  actionBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const tabName = this.getAttribute('data-action');
      showTab(tabName);
    });
  });
  
  // Initialize: show users tab by default
  showTab('users');
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
