<?php
// includes/bootstrap.php — Loaded at the top of every page

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/auth.php';

// Optional Composer autoloader (e.g. PHPMailer)
$__autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($__autoload)) {
	require_once $__autoload;
}

require_once __DIR__ . '/helpers.php';

startSession();

// Timezone for Uganda
date_default_timezone_set('Africa/Kampala');
