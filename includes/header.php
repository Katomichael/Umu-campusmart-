<?php
// includes/header.php
// Usage: include with $pageTitle already set
$user = currentUser();
$unread = 0;
if ($user) {
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS n FROM messages WHERE receiver_id = ? AND is_read = 0',
        [$user['id']]
    );
    $unread = (int)($row['n'] ?? 0);
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$endsWithPath = static function (string $haystack, string $needle): bool {
  if ($needle === '') return true;
  $len = strlen($needle);
  if ($len > strlen($haystack)) return false;
  return substr($haystack, -$len) === $needle;
};
$isActive = static function (string $targetPath) use ($currentPath, $endsWithPath): bool {
  return $currentPath === $targetPath || $endsWithPath($currentPath, $targetPath);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($pageTitle ?? APP_NAME) ?> | CampusMart</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛒</text></svg>"/>
  <?php $cssVersion = @filemtime(__DIR__ . '/../public/css/style.css') ?: time(); ?>
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/style.css?v=<?= $cssVersion ?>"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php if (empty($hideNavbar)): ?>
  <nav class="navbar">
    <div class="container nav-inner">
      <div class="nav-left">
        <a href="<?= APP_URL ?>/index.php" class="nav-brand">
          UMU CampusMart
          <span class="nav-slogan">Buy Smart. Sell Easy. Stay on Campus.</span>
          <span class="nav-sub">UMU</span>
        </a>
        <button class="nav-toggle" type="button" aria-controls="nav-links" aria-expanded="false">Menu</button>
      </div>

      <div class="nav-links" id="nav-links">
        <a href="<?= APP_URL ?>/index.php" class="<?= $isActive('/index.php') ? 'active' : '' ?>">Browse</a>

        <?php if ($user): ?>
          <a href="<?= APP_URL ?>/pages/create_listing.php" class="btn btn-accent btn-sm <?= $isActive('/pages/create_listing.php') ? 'active' : '' ?>">+ Sell</a>
          <a href="<?= APP_URL ?>/pages/messages.php" class="nav-msg-link <?= $isActive('/pages/messages.php') ? 'active' : '' ?>">
            Messages
            <?php if ($unread > 0): ?>
              <span class="badge-pill"><?= $unread ?></span>
            <?php endif; ?>
          </a>

          <?php
            $displayName = trim((string)($user['full_name'] ?? ''));
            $firstName = $displayName ? explode(' ', $displayName)[0] : 'Account';
          ?>
          <div class="nav-user">
            <a href="<?= APP_URL ?>/pages/profile.php" class="nav-avatar" aria-label="View profile">
              <?php if (!empty($user['avatar'])): ?>
                <img src="<?= APP_URL . '/public/' . e($user['avatar']) ?>" alt="<?= e($displayName) ?>">
              <?php else: ?>
                <?= strtoupper(substr($displayName ?: 'U', 0, 1)) ?>
              <?php endif; ?>
            </a>
            <button class="nav-user-btn" type="button" aria-haspopup="true" aria-expanded="false">
              <span class="nav-user-label"><?= e($firstName) ?></span>
              <span class="nav-user-caret" aria-hidden="true">▾</span>
            </button>
            <div class="nav-user-menu" role="menu">
              <div class="nav-user-meta">
                <div class="nav-user-meta-name"><?= e($displayName ?: 'Account') ?></div>
                <?php if (!empty($user['email'])): ?>
                  <div class="nav-user-meta-sub"><?= e($user['email']) ?></div>
                <?php endif; ?>
              </div>
              <a href="<?= APP_URL ?>/pages/profile.php" class="<?= $isActive('/pages/profile.php') ? 'active' : '' ?>" role="menuitem">Profile</a>
              <a href="<?= APP_URL ?>/pages/logout.php" role="menuitem">Logout</a>
            </div>
          </div>

          <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-admin <?= $isActive('/admin/dashboard.php') ? 'active' : '' ?>">Admin </a>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?= APP_URL ?>/pages/login.php"    class="btn btn-ghost btn-sm <?= $isActive('/pages/login.php') ? 'active' : '' ?>">Login</a>
          <a href="<?= APP_URL ?>/pages/register.php" class="btn btn-accent btn-sm <?= $isActive('/pages/register.php') ? 'active' : '' ?>">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
<?php endif; ?>

<main class="main-content">
