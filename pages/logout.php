<?php
// pages/logout.php
require_once __DIR__ . '/../includes/bootstrap.php';
sessionLogout();
redirect('/pages/login.php');
