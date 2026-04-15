<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Check if admin already exists
$adminExists = Database::fetchOne('SELECT id FROM users WHERE role="admin" LIMIT 1');

if ($adminExists) {
    die('<h2>❌ Setup Complete</h2><p>An admin user already exists. Delete this file for security.</p>');
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (!$full_name) {
        $message = '❌ Full name is required.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '❌ Valid email is required.';
    } elseif (strlen($password) < 6) {
        $message = '❌ Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $message = '❌ Passwords do not match.';
    } else {
        $existing = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            $message = '❌ Email already exists.';
        } else {
            try {
                Database::insert(
                    'INSERT INTO users (full_name, email, password_hash, role, is_banned, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())',
                    [$full_name, $email, hashPassword($password), 'admin', 0]
                );
                $success = true;
                $message = '✅ Admin user created successfully! Redirecting...';
            } catch (Exception $e) {
                $message = '❌ Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusMart Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-header .emoji {
            font-size: 48px;
            margin-bottom: 12px;
        }
        .setup-header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
        }
        .setup-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        button:active {
            transform: translateY(0);
        }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .message-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .message-success {
            background: #dfd;
            color: #3a3;
            border: 1px solid #cfc;
        }
        .warning {
            background: #ffeaa7;
            border: 1px solid #f0e68c;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #333;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="setup-box">
        <div class="setup-header">
            <div class="emoji">⚙️</div>
            <h1>CampusMart Setup</h1>
            <p>Create your first admin account</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $success ? 'message-success' : 'message-error' ?>">
                <?= $message ?>
            </div>
            <?php if ($success): ?>
                <script>
                    setTimeout(() => {
                        window.location.href = '<?= APP_URL ?>/pages/login.php';
                    }, 2000);
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="admin@example.com" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                </div>
                <button type="submit">Create Admin Account</button>
            </form>

            <div class="warning">
                ⚠️ <strong>Security Note:</strong> Delete this file (setup.php) after creating the admin account!
            </div>
        <?php endif; ?>
    </div>
</body>
</html>