<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Backward compatible endpoint: redirect to the unified reset page.
if (isLoggedIn()) redirect('/index.php');

header('Location: ' . APP_URL . '/pages/reset_password.php');
exit;
