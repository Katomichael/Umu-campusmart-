<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) redirect('/index.php');

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        $user = Database::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);

        if (!$user || !verifyPassword($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['is_banned']) {
            $error = 'Your account has been suspended. Contact admin.';
        } else {
            sessionLogin($user['id']);
            flash('success', 'Welcome back, ' . $user['full_name'] . '! ');
            redirect($user['role'] === 'admin' ? '/admin/dashboard.php' : '/index.php');
        }
    }
}

$pageTitle = 'Login';
$hideNavbar = true;
$hideFooter = true;
include __DIR__ . '/../includes/header.php';
?>

<style>

.welcome-heading {
    text-align: center;
    font-size: 2.6rem;
    font-weight: 900;
    margin-bottom: 38px;
    letter-spacing: -1px;
    background: linear-gradient(90deg, #b81111 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: transparent;
    text-shadow: 0 4px 24px rgba(102, 126, 234, 0.10);
}


.auth-box {
    background-color: rgba(255, 255, 255, 0.92);
    background:
        linear-gradient(
            135deg,
            rgba(255, 255, 255, 0.94) 0%,
            rgba(255, 255, 255, 0.88) 55%,
            rgba(184, 17, 17, 0.10) 100%
        );
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 12px;
    padding: 40px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 14px 45px rgba(0, 0, 0, 0.16);
    position: relative;
}

.auth-logo {
    text-align: center;
    margin-bottom: 30px;
}

.umu-badge {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.umu-badge img {
    width: 90px;
    height: auto;
    max-height: 90px;
    opacity: 1;
    image-rendering: auto;
    -ms-interpolation-mode: bicubic;
    transition: opacity 0.3s ease;
}

.umu-badge img:hover {
    opacity: 1;
}

.auth-logo .emoji {
    font-size: 48px;
    margin-bottom: 12px;
}

.auth-logo h1 {
    font-size: 24px;
    font-weight: 700;
    color: #b81111;
    margin: 0 0 8px 0;
}

.auth-logo p {
    font-size: 14px;
    color: #cea5a5;
    margin: 0;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
}

.form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.alert {
    padding: 12px 14px;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 16px;
}

.alert-danger {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

.alert-success {
    background: #efe;
    color: #3c3;
    border: 1px solid #cfc;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-full {
    width: 100%;
    padding: 11px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.mt-4 {
    margin-top: 24px;
}

.text-center {
    text-align: center;
}

.muted {
    color: #999;
}

a {
    text-decoration: none;
    color: inherit;
}

@media (max-width: 480px) {
    .auth-box {
        padding: 30px 20px;
    }

    .auth-logo h1 {
        font-size: 20px;
    }

    .umu-badge img {
        width: 80px;
        height: 80px;
    }
}
</style>


<div class="auth-wrap auth-bg" style="--auth-bg-image: url('<?= APP_URL ?>/public/images/login-bg.webp');">
    <div class="auth-box">
    
    <!-- UMU Badge -->



    <!-- UMU Badge -->
    <div class="umu-badge">
      <?php 
                $badgePath = __DIR__ . '/../public/images/umu-badge.jfif';
        if (file_exists($badgePath)): 
      ?>
                <img src="<?= APP_URL ?>/public/images/umu-badge.jfif" alt="Uganda Martyrs University">
      <?php else: ?>
        <div style="font-size: 70px; opacity: 0.7;"></div>
      <?php endif; ?>
    </div>



    <div class="auth-logo">
      <div class="emoji"></div>
      <h1>WELCOME BACK</h1>
      <p>Sign in to your CampusMart account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php $s = getFlash('success'); if ($s): ?>
      <div class="alert alert-success"><?= e($s) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label>University Email</label>
        <input class="form-control" type="email" name="email"
               value="<?= e($email) ?>" placeholder="you@<?= UNIVERSITY_DOMAIN ?>" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input class="form-control" type="password" name="password"
               placeholder="••••••••" required>
      </div>
            <div style="display:flex;justify-content:flex-end;margin-top:-6px;margin-bottom:8px">
                                <a id="forgot-password-link" href="<?= APP_URL ?>/pages/reset_password.php" style="font-size:13px;color:#667eea;font-weight:600">
                    Forgot password?
                </a>
            </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
        Sign In
      </button>
    </form>

        <script>
            (function () {
                const link = document.getElementById('forgot-password-link');
                if (!link) return;

                link.addEventListener('click', function (e) {
                    const emailInput = document.querySelector('input[name="email"]');
                    const email = emailInput ? (emailInput.value || '').trim() : '';
                    if (!email) return; // If empty, let reset page show the normal email form.

                    e.preventDefault();
                    const url = new URL(link.href, window.location.origin);
                    url.searchParams.set('email', email);
                    url.searchParams.set('autostart', '1');
                    window.location.href = url.toString();
                });
            })();
        </script>

    <p class="text-center mt-4" style="font-size:14px;color:#999">
      No account?
      <a href="<?= APP_URL ?>/pages/register.php" style="color:#667eea;font-weight:600">Register here</a>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>