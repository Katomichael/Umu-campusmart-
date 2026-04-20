<?php
// pages/edit_listing.php — just passes through to create_listing.php with id param
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/index.php');

// Verify ownership (admins can edit any listing)
$me = currentUser();
$listing = Database::fetchOne('SELECT seller_id FROM listings WHERE id = ?', [$id]);
if (!$listing || ($listing['seller_id'] != $me['id'] && !isAdmin())) redirect('/index.php');

// Forward to create_listing with edit mode
header('Location: ' . APP_URL . '/pages/create_listing.php?id=' . $id);
exit;
