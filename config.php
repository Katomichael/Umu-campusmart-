<?php
// CampusMart Configuration

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3307);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'campusmart_db');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CampusMart');
// Auto-detect host so the app works on other devices (phone/tablet) on the same network.
// Keep the base path in sync with your XAMPP htdocs folder name.
$__appBasePath = '/campusmart-php';
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('APP_URL',  $__scheme . '://' . $__host . $__appBasePath);
define('UNIVERSITY_NAME', 'Uganda Martyrs University');
define('UNIVERSITY_DOMAIN', 'stud.umu.ac.ug');

define('SESSION_NAME', 'campusmart_session');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);

define('UPLOAD_DIR',    __DIR__ . '/public/uploads/');
define('UPLOAD_URL',    APP_URL . '/public/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ── Email (Welcome messages, notifications) ─────────────────────────────
// Note: On many local XAMPP installs, PHP `mail()` is not configured.
// This feature is enabled by default, but failures won't block sign-up.
define('MAIL_ENABLED', true);
// SMTP via PHPMailer (recommended for reliable delivery)
// Enforce sender domain: SMTP account must be a university email.
$__smtpUser = getenv('SMTP_USERNAME') ?: '';
$__smtpEnabledRaw = (getenv('SMTP_ENABLED') ?: '0') === '1';
$__smtpIsUniversity = $__smtpUser && preg_match('/@' . preg_quote(UNIVERSITY_DOMAIN, '/') . '$/i', $__smtpUser);
define('SMTP_ENABLED', $__smtpEnabledRaw && $__smtpIsUniversity);
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', $__smtpUser);
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
// Allowed: 'tls', 'ssl', or ''
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
// Derive a reasonable default sender from the current host.
$__mailHost = preg_replace('/:\\d+$/', '', $__host ?? 'localhost');
$__defaultFrom = 'no-reply@' . ($__mailHost ?: 'localhost');
$__smtpFrom = (defined('SMTP_ENABLED') && SMTP_ENABLED && defined('SMTP_USERNAME') && SMTP_USERNAME)
	? SMTP_USERNAME
	: '';
define('MAIL_FROM', $__smtpFrom ?: $__defaultFrom);
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_REPLY_TO', $__smtpFrom ?: ('support@' . ($__mailHost ?: 'localhost')));

define('LISTINGS_PER_PAGE', 18);
?>