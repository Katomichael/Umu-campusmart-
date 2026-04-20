<?php
// includes/helpers.php — Utility functions

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . APP_URL . $path);
    exit;
}

function flash(string $key, string $message): void {
    startSession();
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): string {
    startSession();
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function formatPrice(float $price): string {
    return 'UGX ' . number_format($price, 0, '.', ',');
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function stars(float $score): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="star ' . ($i <= round($score) ? 'filled' : '') . '">★</span>';
    }
    return $html;
}

function conditionBadge(string $cond): string {
    $map = [
        'new'      => ['New',      'badge-green'],
        'like_new' => ['Like New', 'badge-green'],
        'good'     => ['Good',     'badge-amber'],
        'fair'     => ['Fair',     'badge-amber'],
        'poor'     => ['Poor',     'badge-gray'],
    ];
    [$label, $cls] = $map[$cond] ?? ['—', 'badge-gray'];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

function uploadImage(array $file, string $subdir = 'listings'): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE)    return false;
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;

    $subdir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subdir) ?: 'uploads';

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
            finfo_close($finfo);
        }
    }
    $mime = $mime ?: ($file['type'] ?? '');

    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) return false;
    if (!in_array($mime, ALLOWED_TYPES, true)) return false;

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$mime] ?? null;
    if (!$ext) return false;

    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = uniqid('img_', true) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return 'uploads/' . $subdir . '/' . $filename;
}

function paginate(int $total, int $page, int $perPage, string $baseUrl): array {
    $pages  = (int) ceil($total / $perPage);
    $page   = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    return compact('total', 'pages', 'page', 'offset', 'baseUrl');
}

function getPaginationHTML(array $p): string {
    if ($p['pages'] <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $active = $i === $p['page'] ? ' active' : '';
        $sep    = strpos($p['baseUrl'], '?') !== false ? '&' : '?';
        $html  .= '<a href="' . e($p['baseUrl'] . $sep . 'page=' . $i) . '" class="page-btn' . $active . '">' . $i . '</a>';
    }
    return $html . '</div>';
}

function sendEmail(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) return false;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost';
    $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'CampusMart';
    $replyTo   = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : $fromEmail;

    // Prefer SMTP (PHPMailer) when configured.
    if (
        defined('SMTP_ENABLED') && SMTP_ENABLED &&
        defined('SMTP_HOST') && SMTP_HOST &&
        class_exists('PHPMailer\\PHPMailer\\PHPMailer')
    ) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $mail->SMTPAuth = true;
            $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';

            $enc = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($replyTo);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            if ($textBody) {
                $mail->AltBody = $textBody;
            }

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('sendEmail SMTP failed for ' . $to . ' subject=' . $subject . ' err=' . $e->getMessage());
            // Fall through to mail() below.
        }
    }

    $encodedFromName = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($fromName, 'UTF-8')
        : $fromName;
    $encodedSubject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8')
        : $subject;

    if (!function_exists('mail')) return false;

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $encodedFromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $replyTo;

    $body = $htmlBody;
    if ($textBody) {
        // Provide a very small plain-text fallback for clients that strip HTML.
        $body .= "\n\n<!--\n" . $textBody . "\n-->";
    }

    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    if (!$ok) {
        error_log('sendEmail failed for ' . $to . ' subject=' . $subject);
    }
    return (bool)$ok;
}

function sendWelcomeEmail(string $email, string $fullName): bool {
    $name = trim($fullName) ?: 'there';
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $subject = 'Welcome to ' . (defined('APP_NAME') ? APP_NAME : 'CampusMart');
    $loginUrl = (defined('APP_URL') ? APP_URL : '') . '/pages/login.php';

    $html = '<!doctype html><html><body style="font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;line-height:1.6;color:#1a1f2e">'
          . '<div style="max-width:560px;margin:0 auto;padding:20px">'
          . '<h2 style="margin:0 0 10px">Welcome, ' . $safeName . '!</h2>'
          . '<p style="margin:0 0 14px">Your CampusMart account has been created successfully.</p>'
          . '<p style="margin:0 0 18px"><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Sign in to your account</a></p>'
          . '<p style="margin:0;color:#6c757d;font-size:12px">If you did not create this account, you can ignore this email.</p>'
          . '</div></body></html>';

    $text = "Welcome, {$name}!\n\nYour CampusMart account has been created successfully.\nLogin: {$loginUrl}\n\nIf you did not create this account, you can ignore this email.";

    return sendEmail($email, $subject, $html, $text);
}

function adminAuditLog(string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void {
    $me = currentUser();
    if (!$me || ($me['role'] ?? '') !== 'admin') return;

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    try {
        Database::insert(
            'INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, meta, ip_address) VALUES (?,?,?,?,?,?)',
            [(int)$me['id'], $action, $entityType, $entityId, $metaJson, $ip ?: null]
        );
    } catch (Throwable $e) {
        // Best-effort only (e.g. table not migrated yet).
    }
}
