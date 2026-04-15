<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) redirect('/index.php');

$errors = [];
$form   = ['full_name'=>'','email'=>'','student_id'=>'','course'=>'','year_of_study'=>'1','phone'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $form['full_name']     = trim($_POST['full_name']     ?? '');
    $form['email']         = strtolower(trim($_POST['email'] ?? ''));
        $form['student_id']    = strtoupper(trim($_POST['student_id'] ?? ''));
        $form['course']        = trim($_POST['course']        ?? '');
        $form['year_of_study'] = (int)($_POST['year_of_study']?? 1);
        $form['phone']         = trim($_POST['phone']         ?? '');
        $password              = $_POST['password']           ?? '';

    if (!$form['full_name']) {
      $errors[] = 'Full name is required.';
    } elseif (mb_strlen($form['full_name']) > 100) {
      $errors[] = 'Full name is too long.';
    }

    if (!$form['email']) {
      $errors[] = 'Email is required.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Enter a valid email address.';
    } elseif (!isValidUMUEmail($form['email'])) {
      $errors[] = 'Only @' . UNIVERSITY_DOMAIN . ' emails are accepted.';
    }

    if ($form['student_id'] !== '') {
      // Expected format: 2023-B071-22673
      if (!preg_match('/^\d{4}-B\d{3}-\d{5}$/', $form['student_id'])) {
        $errors[] = 'Student ID must be in the format 2023-B071-22673.';
      }
    }

    if ($form['course'] !== '' && mb_strlen($form['course']) > 100) {
      $errors[] = 'Course / Programme is too long.';
    }

    if ($form['year_of_study'] < 1 || $form['year_of_study'] > 5) {
      $errors[] = 'Year of study must be between 1 and 5.';
    }

    if ($form['phone'] !== '') {
      if (mb_strlen($form['phone']) > 20) {
        $errors[] = 'Phone number is too long.';
      } elseif (!preg_match('/^[0-9+()\s-]+$/', $form['phone'])) {
        $errors[] = 'Phone number must contain numbers only (and +, spaces, -, parentheses).';
      } else {
        $digits = preg_replace('/\D+/', '', $form['phone']);
        if (strlen($digits) < 7) {
          $errors[] = 'Phone number looks too short.';
        }
      }
    }

    if (strlen($password) < 6) {
      $errors[] = 'Password must be at least 6 characters.';
    }

        if (!$errors) {
          $existing = Database::fetchOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$form['email']]);
          if ($existing) {
            $errors[] = 'This email is already registered.';
          } else {
            try {
              $id = Database::insert(
                'INSERT INTO users (full_name, email, password_hash, student_id, course, year_of_study, phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                  $form['full_name'], $form['email'], hashPassword($password),
                  $form['student_id'] ?: null, $form['course'] ?: null,
                  $form['year_of_study'], $form['phone'] ?: null,
                ]
              );

              // Non-blocking welcome email.
              sendWelcomeEmail($form['email'], $form['full_name']);

              sessionLogin($id);
              flash('success', 'Welcome to CampusMart! 🎉');
              redirect('/index.php');
            } catch (PDOException $e) {
              // Friendly messages for common unique constraint violations.
              $msg = $e->getMessage();
              if (stripos($msg, 'users.email') !== false) {
                $errors[] = 'This email is already registered.';
              } elseif (stripos($msg, 'users.student_id') !== false) {
                $errors[] = 'This student ID is already registered.';
              } else {
                $errors[] = 'Registration failed. Please try again.';
                error_log('Register failed: ' . $msg);
              }
            }
          }
        }
    }
}

$pageTitle = 'Register';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap auth-bg" style="--auth-bg-image: url('<?= APP_URL ?>/public/images/login-bg.webp');">
  <div class="auth-box">
    <div class="auth-logo">
      <div class="emoji"></div>
      <h1>Create Account</h1>
      <p>UMU students only — use your @<?= UNIVERSITY_DOMAIN ?> email</p>
    </div>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= e($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="form-group span-2">
          <label>Full Name </label>
          <input class="form-control" name="full_name" value="<?= e($form['full_name']) ?>"
                 placeholder="" required>
        </div>
        <div class="form-group span-2">
          <label>University Email *</label>
          <input class="form-control" type="email" name="email" value="<?= e($form['email']) ?>"
                 placeholder="<?= UNIVERSITY_DOMAIN ?>" required>
        </div>
        <div class="form-group span-2">
          <label>Password *</label>
          <input class="form-control" type="password" name="password"
                 placeholder="Min. 6 characters" required>
        </div>
        <div class="form-group">
          <label>Student ID</label>
           <input class="form-control" name="student_id" value="<?= e($form['student_id']) ?>"
             maxlength="15" inputmode="numeric" pattern="\d{4}-B\d{3}-\d{5}"
             placeholder="2023-B071-22673" title="Format: 2023-B071-22673">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input class="form-control" type="tel" name="phone" value="<?= e($form['phone']) ?>" maxlength="20"
                 inputmode="tel" pattern="[0-9+()\s-]+"
                 placeholder="+256…">
        </div>
        <div class="form-group span-2">
          <label>Course / Programme</label>
          <input class="form-control" name="course" value="<?= e($form['course']) ?>" maxlength="100"
                 placeholder="e.g. BSc Computer Science">
        </div>
        <div class="form-group">
          <label>Year of Study</label>
          <select class="form-control" name="year_of_study" required>
            <?php for ($y=1;$y<=5;$y++): ?>
              <option value="<?= $y ?>" <?= $form['year_of_study']==$y?'selected':'' ?>>Year <?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px">
        Create Account
      </button>
    </form>

    <p class="text-center mt-4" style="font-size:14px;color:var(--muted)">
      Already have an account?
      <a href="<?= APP_URL ?>/pages/login.php" style="color:var(--primary);font-weight:600">Sign in</a>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
