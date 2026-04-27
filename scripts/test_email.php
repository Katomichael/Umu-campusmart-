<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Usage:
//   php scripts/test_email.php recipient@example.com

header('Content-Type: text/plain; charset=UTF-8');

$to = $argv[1] ?? '';
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Usage: php scripts/test_email.php recipient@example.com\n";
    exit(2);
}

echo "Email diagnostics\n";
echo "- MAIL_ENABLED: " . (defined('MAIL_ENABLED') && MAIL_ENABLED ? 'true' : 'false') . "\n";
echo "- SMTP_ENABLED: " . (defined('SMTP_ENABLED') && SMTP_ENABLED ? 'true' : 'false') . "\n";
echo "- SMTP_HOST: " . (defined('SMTP_HOST') ? (SMTP_HOST ?: '(empty)') : '(undef)') . "\n";
echo "- SMTP_PORT: " . (defined('SMTP_PORT') ? (string)SMTP_PORT : '(undef)') . "\n";
echo "- SMTP_USERNAME: " . (defined('SMTP_USERNAME') ? (SMTP_USERNAME ?: '(empty)') : '(undef)') . "\n";
echo "- MAIL_FROM: " . (defined('MAIL_FROM') ? (MAIL_FROM ?: '(empty)') : '(undef)') . "\n\n";

$subject = 'CampusMart email test';
$html = '<p>This is a test email from CampusMart.</p>';
$text = 'This is a test email from CampusMart.';

$ok = sendEmail($to, $subject, $html, $text);

echo "sendEmail() returned: " . ($ok ? 'true' : 'false') . "\n";
if (!$ok) {
    echo "If you are on XAMPP, this usually means PHP mail() is not configured and SMTP is not enabled.\n";
}
