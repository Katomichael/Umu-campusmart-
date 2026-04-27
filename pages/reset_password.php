<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) redirect('/index.php');

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$errors = [];
$info = '';
$email = strtolower(trim((string)($_POST['email'] ?? ($_GET['email'] ?? ''))));
$debugResetUrl = '';

$devShow = (getenv('DEV_SHOW_RESET_LINK') ?: '0') === '1';

$validToken = false;
$resetRow = null;

$autoRequest = ($token === '')
  && (($_GET['autostart'] ?? '') === '1')
  && ($email !== '')
  && filter_var($email, FILTER_VALIDATE_EMAIL);

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token)) {
  $hash = hash('sha256', $token);
  try {
    $resetRow = Database::fetchOne(
      'SELECT pr.*, u.email
       FROM password_resets pr
       JOIN users u ON u.id = pr.user_id
       WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
       LIMIT 1',
      [$hash]
    );
    $validToken = (bool)$resetRow;
  } catch (Throwable $e) {
    $errors[] = 'Password reset is not available yet. Run the migration script first.';
    error_log('Reset password lookup failed: ' . $e->getMessage());
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Invalid form submission.';
  } elseif ($token !== '') {
    // Token-based reset
    if (!$validToken) {
      $errors[] = 'This reset link is invalid or has expired.';
    } else {
      $p1 = (string)($_POST['password'] ?? '');
      $p2 = (string)($_POST['password_confirm'] ?? '');

      if (strlen($p1) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
      } elseif (!hash_equals($p1, $p2)) {
        $errors[] = 'Passwords do not match.';
      }

      if (!$errors) {
        try {
          Database::query('UPDATE users SET password_hash=? WHERE id=?', [hashPassword($p1), (int)$resetRow['user_id']]);
          Database::query('UPDATE password_resets SET used_at=NOW() WHERE id=?', [(int)$resetRow['id']]);

          // Best-effort security notification.
          try {
            $to = (string)($resetRow['email'] ?? '');
            if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
              $subject = 'Your CampusMart password was changed';
              $loginUrl = APP_URL . '/pages/login.php';
              $when = date('Y-m-d H:i');
              $html = '<p>Hello,</p>'
                  . '<p>This is a confirmation that your CampusMart password was changed on <strong>' . e($when) . '</strong>.</p>'
                  . '<p>If you did not do this, please reset your password again immediately or contact support.</p>'
                  . '<p><a href="' . e($loginUrl) . '">Sign in</a></p>';
              $text = "Hello,\n\nThis is a confirmation that your CampusMart password was changed on {$when}.\n\nIf you did not do this, please reset your password again immediately or contact support.\n\nSign in: {$loginUrl}\n";
              sendEmail($to, $subject, $html, $text);
            }
          } catch (Throwable $e) {
            // Ignore email failures.
          }

          flash('success', 'Password updated. You can now sign in.');
          redirect('/pages/login.php');
        } catch (Throwable $e) {
          $errors[] = 'Could not reset password. Please try again.';
          error_log('Reset password update failed: ' . $e->getMessage());
        }
      }
    }
  } else {
    // Request a reset link (no token)
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Enter a valid email address.';
    }

    if (!$errors) {
      // Generic message to avoid email enumeration.
      $info = 'If an account exists for that email, we\'ve sent a password reset link.';

      try {
        $user = Database::fetchOne('SELECT id, full_name, email FROM users WHERE email=? LIMIT 1', [$email]);
        if ($user) {
          $token = bin2hex(random_bytes(32));
          $hash  = hash('sha256', $token);

          // Best-effort cleanup of old unused tokens for this user.
          try {
            Database::query('DELETE FROM password_resets WHERE user_id=? AND used_at IS NULL', [(int)$user['id']]);
          } catch (Throwable $e) {
            // ignore
          }

          Database::insert(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip, requested_ua)
             VALUES (?,?,?,?,?)',
            [
              (int)$user['id'],
              $hash,
              date('Y-m-d H:i:s', time() + 3600),
              (string)($_SERVER['REMOTE_ADDR'] ?? ''),
              substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]
          );

          $resetUrl = APP_URL . '/pages/reset_password.php?token=' . urlencode($token);
          $name = trim((string)($user['full_name'] ?? '')) ?: 'there';

          $subject = 'Reset your CampusMart password';
          $html = '<p>Hello ' . e($name) . ',</p>'
              . '<p>We received a request to reset your password.</p>'
              . '<p><a href="' . e($resetUrl) . '">Click here to reset your password</a></p>'
              . '<p>This link expires in 1 hour. If you didn\'t request this, you can ignore this email.</p>';
          $text = "Hello {$name},\n\nReset your password: {$resetUrl}\n\nThis link expires in 1 hour.";

          $sent = sendEmail($email, $subject, $html, $text);
          if ($devShow && !$sent) {
            $debugResetUrl = $resetUrl;
          }
        }
      } catch (Throwable $e) {
        error_log('Reset password request failed: ' . $e->getMessage());
      }
    }
  }
}

$pageTitle = 'Reset Password';
$hideNavbar = true;
$hideFooter = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap auth-bg" style="--auth-bg-image: url('<?= APP_URL ?>/public/images/login-bg.webp');">
  <div class="auth-box auth-box-gradient">
    <div class="auth-logo">
      <div class="emoji"></div>
      <h1>Reset Password</h1>
      <p><?= $token !== '' ? 'Choose a new password' : 'Request a reset link' ?></p>
    </div>

    <?php if ($info): ?>
      <div class="alert alert-success"><?= e($info) ?></div>
      <?php if ($debugResetUrl): ?>
        <div class="alert alert-success" style="margin-top:-8px">
          <strong>Dev mode:</strong> reset link: <a href="<?= e($debugResetUrl) ?>" style="color:var(--primary);font-weight:700">Open reset link</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= e($e) ?></div>
    <?php endforeach; ?>

    <?php if ($token !== ''): ?>
      <?php if (!$errors && !$validToken): ?>
        <div class="alert alert-danger">This reset link is invalid or has expired.</div>
      <?php else: ?>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <div class="form-group">
            <label>New password</label>
            <input class="form-control" type="password" name="password" minlength="6" required>
          </div>
          <div class="form-group">
            <label>Confirm new password</label>
            <input class="form-control" type="password" name="password_confirm" minlength="6" required>
          </div>

          <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
            Update Password
          </button>
        </form>
      <?php endif; ?>

      <p class="text-center mt-4" style="font-size:14px;color:var(--muted)">
        <a href="<?= APP_URL ?>/pages/reset_password.php" style="color:var(--primary);font-weight:600">Request a new link</a>
        &nbsp;·&nbsp;
        <a href="<?= APP_URL ?>/pages/login.php" style="color:var(--primary);font-weight:600">Back to login</a>
      </p>
    <?php else: ?>
      <?php if ($autoRequest && $_SERVER['REQUEST_METHOD'] !== 'POST' && !$info && !$errors): ?>
        <div class="alert alert-success">Sending reset link…</div>
        <form method="POST" id="auto-reset-request">
          <?= csrfField() ?>
          <input type="hidden" name="email" value="<?= e($email) ?>">
          <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
            Continue
          </button>
        </form>
        <script>
          (function () {
            const form = document.getElementById('auto-reset-request');
            if (form) form.submit();
          })();
        </script>
      <?php else: ?>
        <form method="POST">
          <?= csrfField() ?>
          <div class="form-group">
            <label>Email</label>
            <input class="form-control" type="email" name="email" value="<?= e($email) ?>"
                   placeholder="you@<?= UNIVERSITY_DOMAIN ?>" required>
          </div>
          <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
            Send Reset Link
          </button>
        </form>
      <?php endif; ?>

      <p class="text-center mt-4" style="font-size:14px;color:var(--muted)">
        <a href="<?= APP_URL ?>/pages/login.php" style="color:var(--primary);font-weight:600">Back to login</a>
      </p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
