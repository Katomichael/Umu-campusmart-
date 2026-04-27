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
    l.price AS listing_price,
    cm.other_id,
    u.full_name AS other_name,
    u.avatar AS other_avatar,
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
        "SELECT m.*, u.full_name AS sender_name, u.avatar AS sender_avatar
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
    $listingData = Database::fetchOne('SELECT id, title, price FROM listings WHERE id=?', [$activeListing]);
}

$pageTitle = 'Messages';
include __DIR__ . '/../includes/header.php';
?>
<style>
  .main-content { padding: 0 !important; }
  
  /* Conversation List Improvements */
  .conv-item {
    display: flex !important;
    gap: 12px;
    align-items: flex-start;
    padding: 14px 16px !important;
    transition: all 0.2s ease;
    border-bottom: 1px solid var(--border);
    flex-wrap: nowrap;
  }
  
  .conv-item:hover {
    background: linear-gradient(135deg, rgba(151,14,14,0.04) 0%, rgba(245,166,35,0.04) 100%) !important;
    transform: translateX(4px);
  }
  
  .conv-item.active {
    background: linear-gradient(135deg, rgba(151,14,14,0.1) 0%, rgba(245,166,35,0.08) 100%) !important;
    border-left: 3px solid var(--primary);
    padding-left: 13px !important;
  }
  
  .conv-item-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid var(--border);
  }
  
  .conv-item-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  
  .conv-item-content {
    flex: 1;
    min-width: 0;
    overflow: hidden;
  }
  
  .conv-item-thumb {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background: #eef2f7;
    overflow: hidden;
    flex-shrink: 0;
    object-fit: cover;
    border: 1px solid var(--border);
  }
  
  .conv-item-unread {
    background: var(--accent) !important;
    color: #1a1f2e !important;
    font-weight: 700 !important;
  }
  
  .conv-item-last {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  
  /* Message Bubbles */
  .chat-bubble-wrap {
    display: flex;
    gap: 8px;
    align-items: flex-end;
    margin: 12px 0;
    animation: messageSlide 0.3s ease;
  }
  
  .chat-bubble-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 13px;
    flex-shrink: 0;
    overflow: hidden;
    border: 1px solid var(--border);
  }
  
  .chat-bubble-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  
  .chat-bubble-wrap.me {
    justify-content: flex-end;
  }
  
  .chat-bubble-wrap.me .chat-bubble-avatar {
    order: 2;
  }
  
  .chat-bubble-wrap.me > div:not(.chat-bubble-avatar) {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
  }
  
  .chat-bubble-content {
    max-width: 65%;
    padding: 11px 14px;
    border-radius: 14px;
    font-size: 14px;
    line-height: 1.4;
  }
  
  .chat-bubble-wrap.them .chat-bubble-content {
    background: var(--surface);
    box-shadow: var(--shadow);
    border-radius: 14px 14px 14px 4px;
    color: var(--text);
  }
  
  .chat-bubble-wrap.me .chat-bubble-content {
    background: var(--primary);
    color: #fff;
    border-radius: 14px 14px 4px 14px;
  }
  
  .chat-time {
    font-size: 11px;
    opacity: .7;
    margin-top: 4px;
    padding: 0 4px;
  }
  
  @keyframes messageSlide {
    from {
      opacity: 0;
      transform: translateY(8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  /* Chat Messages Area */
  .chat-messages {
    padding: 20px;
  }
  
  /* Chat Header */
  .chat-header {
    padding: 14px 20px !important;
    background: linear-gradient(135deg, rgba(151,14,14,0.04) 0%, rgba(245,166,35,0.04) 100%) !important;
    border-bottom: 2px solid var(--border) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px;
  }
  
  .chat-header-user {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
  }
  
  .chat-header-user:hover {
    color: var(--primary);
  }
  
  .chat-header-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid var(--border);
  }
  
  .chat-header-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  
  .chat-header-info {
    flex: 1;
  }
  
  .chat-header-name {
    font-weight: 700;
    font-size: 15px;
    color: var(--text);
  }
  
  .chat-header-listing {
    font-size: 12px;
    color: var(--muted);
    display: flex;
    gap: 6px;
    align-items: center;
  }
  
  .chat-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
  }
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
         class="conv-item <?= $isActive ? 'active' : '' ?> <?= $c['unread'] > 0 ? 'conv-item-unread' : '' ?>">
        <!-- User Avatar -->
        <div class="conv-item-avatar">
          <?php if (!empty($c['other_avatar'])): ?>
            <img src="<?= APP_URL . '/public/' . e($c['other_avatar']) ?>" alt="<?= e($c['other_name']) ?>">
          <?php else: ?>
            <?= strtoupper(substr($c['other_name'], 0, 1)) ?>
          <?php endif; ?>
        </div>
        
        <!-- Content -->
        <div class="conv-item-content">
          <div style="font-weight: 700; color: var(--text); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($c['other_name']) ?></div>
          <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($c['listing_title']) ?></div>
          <div class="conv-item-last" style="font-size: 13px; color: var(--muted);"><?= e(substr($c['last_msg'], 0, 50)) ?><?= strlen($c['last_msg']) > 50 ? '...' : '' ?></div>
        </div>
        
        <!-- Unread Badge -->
        <?php if ($c['unread'] > 0): ?>
          <div style="min-width: 20px; height: 20px; background: var(--accent); color: #1a1f2e; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">
            <?= $c['unread'] > 99 ? '99+' : $c['unread'] ?>
          </div>
        <?php endif; ?>
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
           class="chat-header-user">
          <div class="chat-header-avatar">
            <?php if (!empty($otherUser['avatar'])): ?>
              <img src="<?= APP_URL . '/public/' . e($otherUser['avatar']) ?>" alt="<?= e($otherUser['full_name'] ?? '') ?>">
            <?php else: ?>
              <?= strtoupper(substr($otherUser['full_name'] ?? '?', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <div class="chat-header-info">
            <div class="chat-header-name"><?= e($otherUser['full_name'] ?? '') ?></div>
            <?php if ($listingData): ?>
              <div class="chat-header-listing">
                <span>📦</span>
                <span><?= e($listingData['title']) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </a>
        
        <div class="chat-header-actions">
          <?php if ($listingData): ?>
            <a href="<?= APP_URL ?>/pages/listing.php?id=<?= (int)$listingData['id'] ?>"
               style="padding: 8px 14px; background: var(--primary); color: #fff; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;"
               onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(151,14,14,0.2)'"
               onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
              👁️ View Listing
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Messages -->
      <div class="chat-messages" id="chat-messages">
        <?php if (empty($chatMessages)): ?>
          <p class="text-muted text-center" style="margin-top:40px;font-size:14px">Start the conversation!</p>
        <?php endif; ?>
        <?php foreach ($chatMessages as $msg): ?>
          <?php $isMe = $msg['sender_id'] == $me['id']; ?>
          <div class="chat-bubble-wrap <?= $isMe ? 'me' : 'them' ?>">
            <?php if (!$isMe): ?>
              <div class="chat-bubble-avatar">
                <?php if (!empty($msg['sender_avatar'])): ?>
                  <img src="<?= APP_URL . '/public/' . e($msg['sender_avatar']) ?>" alt="<?= e($msg['sender_name']) ?>">
                <?php else: ?>
                  <?= strtoupper(substr($msg['sender_name'] ?? '?', 0, 1)) ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            
            <div>
              <div class="chat-bubble-content">
                <?= e($msg['content']) ?>
              </div>
              <div class="chat-time">
                <?= date('g:i A', strtotime($msg['created_at'])) ?>
              </div>
            </div>
            
            <?php if ($isMe): ?>
              <div class="chat-bubble-avatar">
                <?php if (!empty($msg['sender_avatar'])): ?>
                  <img src="<?= APP_URL . '/public/' . e($msg['sender_avatar']) ?>" alt="You">
                <?php else: ?>
                  <?= strtoupper(substr($me['full_name'] ?? '?', 0, 1)) ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
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
