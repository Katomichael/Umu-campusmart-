<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$reportId = (int)($_GET['report_id'] ?? 0);
if (!$reportId) redirect('/admin/dashboard.php');

$report = Database::fetchOne(
  "SELECT r.*, l.title AS listing_title, l.id AS listing_id, l.seller_id,
          reporter.full_name AS reporter_name
   FROM reports r
   JOIN listings l ON r.listing_id = l.id
   JOIN users reporter ON r.reporter_id = reporter.id
   WHERE r.id = ? AND r.listing_id IS NOT NULL",
  [$reportId]
);
if (!$report) redirect('/admin/dashboard.php');

$listingId = (int)$report['listing_id'];
$sellerId  = (int)$report['seller_id'];

$seller = Database::fetchOne('SELECT id, full_name, avatar FROM users WHERE id=?', [$sellerId]);

$activeWith = (int)($_GET['with'] ?? 0);

// Only show conversations that involve the seller for this listing.
$participants = Database::fetchAll(
  'SELECT DISTINCT IF(sender_id=?, receiver_id, sender_id) AS other_id
   FROM messages
   WHERE listing_id=? AND (sender_id=? OR receiver_id=?)
   ORDER BY other_id',
  [$sellerId, $listingId, $sellerId, $sellerId]
);

$others = [];
if (!empty($participants)) {
  $ids = array_values(array_filter(array_map(fn($r) => (int)($r['other_id'] ?? 0), $participants)));
  $ids = array_values(array_filter($ids, fn($id) => $id > 0 && $id !== $sellerId));
  if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $others = Database::fetchAll(
      "SELECT id, full_name, avatar FROM users WHERE id IN ($placeholders)",
      $ids
    );
  }
}

if (!$activeWith && !empty($others)) {
  $activeWith = (int)$others[0]['id'];
}

$chatMessages = [];
$otherUser = null;
if ($activeWith) {
  $otherUser = Database::fetchOne('SELECT id, full_name, avatar FROM users WHERE id=?', [$activeWith]);
  $chatMessages = Database::fetchAll(
    "SELECT m.*, u.full_name AS sender_name
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.listing_id = ?
       AND ((m.sender_id = ? AND m.receiver_id = ?)
         OR (m.sender_id = ? AND m.receiver_id = ?))
     ORDER BY m.created_at ASC",
    [$listingId, $sellerId, $activeWith, $activeWith, $sellerId]
  );
}

adminAuditLog('messages.view_report', 'report', $reportId, ['listing_id' => $listingId, 'with' => $activeWith ?: null]);

$pageTitle = 'Reported Messages';
include __DIR__ . '/../includes/header.php';
?>
<style>
  .main-content { padding: 0 !important; }
</style>

<div class="messages-layout">

  <!-- Participant list -->
  <div class="conv-list">
    <div class="conv-list-header">🛡️ Reported Messages</div>

    <div style="padding:12px 16px">
      <a href="<?= APP_URL ?>/admin/dashboard.php" style="color:var(--muted);font-size:13px">← Back to dashboard</a>
      <div style="margin-top:10px;font-size:12px;color:var(--muted)">Report #<?= (int)$reportId ?> · <?= e($report['reason'] ?? '') ?></div>
      <div style="margin-top:4px;font-weight:800;font-size:13px"><?= e($report['listing_title'] ?? '') ?></div>
      <?php if (!empty($report['description'])): ?>
        <div style="margin-top:6px;font-size:12px;color:#444"><?= e($report['description']) ?></div>
      <?php endif; ?>
      <?php if (!empty($report['admin_note'])): ?>
        <div style="margin-top:6px;font-size:12px;color:#444"><strong>Note:</strong> <?= e($report['admin_note']) ?></div>
      <?php endif; ?>
    </div>

    <div style="padding:0 16px 8px;font-size:12px;color:var(--muted);font-weight:800">Participants</div>

    <?php if (empty($others)): ?>
      <p style="padding:0 16px 16px;font-size:13px;color:var(--muted)">No messages found for this listing.</p>
    <?php else: ?>
      <?php foreach ($others as $u): ?>
        <?php $isActive = ((int)$u['id'] === (int)$activeWith); ?>
        <a href="<?= APP_URL ?>/admin/messages.php?report_id=<?= (int)$reportId ?>&with=<?= (int)$u['id'] ?>"
           class="conv-item <?= $isActive ? 'active' : '' ?>" style="display:block">
          <div class="flex-between">
            <div class="conv-item-name"><?= e($u['full_name']) ?></div>
          </div>
          <div class="conv-item-listing" style="font-size:12px">
            Chat with seller: <?= e($seller['full_name'] ?? 'Seller') ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Chat area -->
  <div class="chat-area">

    <div class="chat-header">
      <div style="display:flex;align-items:center;gap:12px;color:inherit">
        <div class="seller-card-avatar" style="width:36px;height:36px;font-size:15px">
          <?php if (!empty($otherUser['avatar'])): ?>
            <img src="<?= APP_URL . '/public/' . e($otherUser['avatar']) ?>" alt="<?= e($otherUser['full_name'] ?? '') ?>">
          <?php else: ?>
            <?= strtoupper(substr($otherUser['full_name'] ?? '?', 0, 1)) ?>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-weight:800;font-size:14px"><?= e($otherUser['full_name'] ?? 'Select a participant') ?></div>
          <div style="font-size:12px;color:var(--muted)">Read-only view (report scope)</div>
        </div>
      </div>
    </div>

    <div class="chat-messages" id="chat-messages">
      <?php if (empty($chatMessages)): ?>
        <p class="text-muted text-center" style="margin-top:40px;font-size:14px">
          <?= $activeWith ? 'No messages in this thread.' : 'Select a participant to view messages.' ?>
        </p>
      <?php endif; ?>

      <?php foreach ($chatMessages as $msg): ?>
        <?php $isOther = ((int)$msg['sender_id'] === (int)$activeWith); ?>
        <div class="chat-bubble-wrap <?= $isOther ? 'me' : '' ?>">
          <div class="chat-bubble <?= $isOther ? 'me' : 'them' ?>">
            <p><?= e($msg['content']) ?></p>
            <div class="chat-time"><?= timeAgo($msg['created_at']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
