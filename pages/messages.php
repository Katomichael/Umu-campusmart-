<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$me = currentUser();

// Send a message (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $content    = trim($_POST['content']     ?? '');
    $listingId  = (int)($_POST['listing_id']  ?? 0);
    $receiverId = (int)($_POST['receiver_id'] ?? 0);

    if ($content && $listingId && $receiverId && $receiverId !== (int)$me['id']) {
        $listingRow = Database::fetchOne('SELECT id, seller_id, status FROM listings WHERE id=?', [$listingId]);
        if (!$listingRow) {
            flash('error', 'Conversation not found.');
        } elseif ($listingRow['status'] !== 'active') {
            flash('error', 'Messaging is only available for active listings.');
        } else {
            $sellerId = (int)$listingRow['seller_id'];

            // Prevent using a listing as a “cover” to message arbitrary users.
            $onePartyIsSeller = ((int)$me['id'] === $sellerId) || ($receiverId === $sellerId);

            $hasExisting = Database::fetchOne(
                'SELECT 1 FROM messages WHERE listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) LIMIT 1',
                [$listingId, (int)$me['id'], $receiverId, $receiverId, (int)$me['id']]
            );

            $allowed = false;
            if ($onePartyIsSeller) {
                if ((int)$me['id'] !== $sellerId) {
                    // Buyer can only message the seller.
                    $allowed = ($receiverId === $sellerId);
                } else {
                    // Seller can only reply where a thread already exists.
                    $allowed = (bool)$hasExisting;
                }
            }

            if ($allowed) {
                Database::insert(
                    'INSERT INTO messages (listing_id, sender_id, receiver_id, content) VALUES (?,?,?,?)',
                    [$listingId, (int)$me['id'], $receiverId, $content]
                );
            } else {
                flash('error', 'You are not allowed to message for this listing.');
            }
        }
    }

    // Redirect to same conversation
    header('Location: ' . APP_URL . '/pages/messages.php?listing=' . $listingId . '&with=' . $receiverId);
    exit;
}

// Active conversation
$activeListing  = (int)($_GET['listing'] ?? 0);
$activeWith     = (int)($_GET['with']    ?? 0);

$conversationError = '';
$sendError = getFlash('error');

// Validate active conversation so users can't open arbitrary chats via URL.
if ($activeListing && $activeWith) {
  $listingRow = Database::fetchOne('SELECT id, seller_id FROM listings WHERE id=?', [$activeListing]);
  if (!$listingRow) {
    $conversationError = 'Conversation not found.';
    $activeListing = 0;
    $activeWith = 0;
  } else {
    $hasExisting = Database::fetchOne(
      'SELECT 1 FROM messages WHERE listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) LIMIT 1',
      [$activeListing, $me['id'], $activeWith, $activeWith, $me['id']]
    );
    $allowed = ($activeWith === (int)$listingRow['seller_id']) || (bool)$hasExisting;
    if (!$allowed) {
      $conversationError = 'Conversation not found.';
      $activeListing = 0;
      $activeWith = 0;
    }
  }
}

// Fetch all conversations
$conversations = Database::fetchAll(
  "SELECT
    cm.listing_id,
    l.title AS listing_title,
    cm.other_id,
    u.full_name AS other_name,
    m.content AS last_msg,
    m.created_at,
    (
      SELECT COUNT(*)
      FROM messages mx
      WHERE mx.listing_id = cm.listing_id
        AND mx.sender_id = cm.other_id
        AND mx.receiver_id = ?
        AND mx.is_read = 0
    ) AS unread
   FROM (
    SELECT
      listing_id,
      CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id,
      MAX(id) AS last_message_id
    FROM messages
    WHERE sender_id = ? OR receiver_id = ?
    GROUP BY
      listing_id,
      CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
   ) cm
   JOIN messages m ON m.id = cm.last_message_id
   JOIN listings l ON l.id = cm.listing_id
   JOIN users u ON u.id = cm.other_id
   ORDER BY m.created_at DESC",
  [$me['id'], $me['id'], $me['id'], $me['id'], $me['id']]
);

// Fetch messages for active conversation
$chatMessages = [];
$otherUser    = null;
if ($activeListing && $activeWith) {
    $chatMessages = Database::fetchAll(
        "SELECT m.*, u.full_name AS sender_name
         FROM messages m JOIN users u ON m.sender_id = u.id
         WHERE m.listing_id = ?
           AND ((m.sender_id = ? AND m.receiver_id = ?)
             OR (m.sender_id = ? AND m.receiver_id = ?))
         ORDER BY m.created_at ASC",
        [$activeListing, $me['id'], $activeWith, $activeWith, $me['id']]
    );
    // Mark as read
    Database::query(
      'UPDATE messages SET is_read=1 WHERE listing_id=? AND sender_id=? AND receiver_id=? AND is_read=0',
      [$activeListing, $activeWith, $me['id']]
    );
    $otherUser = Database::fetchOne('SELECT id, full_name, avatar FROM users WHERE id=?', [$activeWith]);
}

$pageTitle = 'Messages';
include __DIR__ . '/../includes/header.php';
?>
<style>
  .main-content { padding: 0 !important; }
</style>

<?php if ($sendError): ?>
  <div class="alert alert-danger" style="margin:16px">
    <?= e($sendError) ?>
  </div>
<?php endif; ?>

<div class="messages-layout">

  <!-- Conversation list -->
  <div class="conv-list">
    <div class="conv-list-header">💬 Messages</div>
    <?php if (empty($conversations)): ?>
      <p style="padding:20px;font-size:14px;color:var(--muted)">No conversations yet</p>
    <?php endif; ?>
    <?php foreach ($conversations as $c): ?>
      <?php $isActive = ($c['listing_id'] == $activeListing && $c['other_id'] == $activeWith); ?>
      <a href="<?= APP_URL ?>/pages/messages.php?listing=<?= $c['listing_id'] ?>&with=<?= $c['other_id'] ?>"
         class="conv-item <?= $isActive ? 'active' : '' ?>" style="display:block">
        <div class="flex-between">
          <div class="conv-item-name"><?= e($c['other_name']) ?></div>
          <?php if ($c['unread'] > 0): ?>
            <span style="background:var(--primary);color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700"><?= $c['unread'] ?></span>
          <?php endif; ?>
        </div>
        <div class="conv-item-listing"><?= e($c['listing_title']) ?></div>
        <div class="conv-item-last"><?= e($c['last_msg']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Chat area -->
  <div class="chat-area">
    <?php if (!$activeListing): ?>
      <div class="chat-empty">
        <span style="font-size:40px">💬</span>
        <p><?= $conversationError ? e($conversationError) : 'Select a conversation to start chatting' ?></p>
      </div>
    <?php else: ?>

      <!-- Chat header -->
      <div class="chat-header">
        <a href="<?= APP_URL ?>/pages/user_profile.php?id=<?= (int)($otherUser['id'] ?? 0) ?>"
           style="display:flex;align-items:center;gap:12px;color:inherit">
          <div class="seller-card-avatar" style="width:36px;height:36px;font-size:15px">
            <?php if (!empty($otherUser['avatar'])): ?>
              <img src="<?= APP_URL . '/public/' . e($otherUser['avatar']) ?>" alt="<?= e($otherUser['full_name'] ?? '') ?>">
            <?php else: ?>
              <?= strtoupper(substr($otherUser['full_name'] ?? '?', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <span style="font-weight:700;font-size:15px"><?= e($otherUser['full_name'] ?? '') ?></span>
        </a>
      </div>

      <!-- Messages -->
      <div class="chat-messages" id="chat-messages">
        <?php if (empty($chatMessages)): ?>
          <p class="text-muted text-center" style="margin-top:40px;font-size:14px">Start the conversation!</p>
        <?php endif; ?>
        <?php foreach ($chatMessages as $msg): ?>
          <?php $isMe = $msg['sender_id'] == $me['id']; ?>
          <div class="chat-bubble-wrap <?= $isMe ? 'me' : '' ?>">
            <div class="chat-bubble <?= $isMe ? 'me' : 'them' ?>">
              <p><?= e($msg['content']) ?></p>
              <div class="chat-time"><?= timeAgo($msg['created_at']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Input -->
      <div class="chat-input-bar">
        <form method="POST" style="display:flex;gap:10px;flex:1">
          <?= csrfField() ?>
          <input type="hidden" name="listing_id"  value="<?= $activeListing ?>">
          <input type="hidden" name="receiver_id" value="<?= $activeWith ?>">
          <textarea class="form-control" name="content" id="chat-input" rows="1"
                    placeholder="Type a message… (Enter to send)" required
                    style="flex:1;resize:none"></textarea>
          <button type="submit" class="btn btn-primary btn-send" aria-label="Send message" title="Send">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
