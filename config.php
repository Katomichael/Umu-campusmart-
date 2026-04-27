<?php
// CampusMart Configuration

// --- Database Configuration (Hybrid: Render & Local) ---
// If 'DB_HOST' environment variable exists, use it; otherwise default to local XAMPP
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: 3307);
define('DB_USER', getenv('DB_USER') ?: 'root');
// In Render you used DB_PASSWORD, in local you use an empty string
define('DB_PASS', getenv('DB_PASSWORD') ?: ''); 
define('DB_NAME', getenv('DB_NAME') ?: 'campusmart_db');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CampusMart');

// Auto-detect host
$__appBasePath = '/campusmart-php';
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// RENDER_EXTERNAL_URL is automatically provided by Render
define('APP_URL', getenv('RENDER_EXTERNAL_URL') ?: ($__scheme . '://' . $__host . $__appBasePath));

define('UNIVERSITY_NAME', 'Uganda Martyrs University');
define('UNIVERSITY_DOMAIN', 'stud.umu.ac.ug');

define('SESSION_NAME', 'campusmart_session');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);

define('UPLOAD_DIR',    __DIR__ . '/public/uploads/');
define('UPLOAD_URL',    APP_URL . '/public/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// --- Email Configuration ---
define('MAIL_ENABLED', true);
$__smtpUser = getenv('SMTP_USERNAME') ?: '';
$__smtpEnabledRaw = (getenv('SMTP_ENABLED') ?: '0') === '1';
$__smtpRequireUni = (getenv('SMTP_REQUIRE_UNIVERSITY_DOMAIN') ?: '0') === '1';
$__smtpIsUniversity = $__smtpUser && preg_match('/@' . preg_quote(UNIVERSITY_DOMAIN, '/') . '$/i', $__smtpUser);
define('SMTP_ENABLED', $__smtpEnabledRaw && (!$__smtpRequireUni || $__smtpIsUniversity));
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', $__smtpUser);
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');

$__mailHost = preg_replace('/:\d+$/', '', $__host ?? 'localhost');
$__defaultFrom = 'no-reply@' . ($__mailHost ?: 'localhost');
$__smtpFrom = (defined('SMTP_ENABLED') && SMTP_ENABLED && defined('SMTP_USERNAME') && SMTP_USERNAME)
    ? SMTP_USERNAME
    : '';
define('MAIL_FROM', $__smtpFrom ?: $__defaultFrom);
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_REPLY_TO', $__smtpFrom ?: ('support@' . ($__mailHost ?: 'localhost')));

define('LISTINGS_PER_PAGE', 18);