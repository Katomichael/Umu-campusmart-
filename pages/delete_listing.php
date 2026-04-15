<?php
// pages/delete_listing.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$me = currentUser();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/index.php');

$listing = Database::fetchOne('SELECT seller_id FROM listings WHERE id = ?', [$id]);
if (!$listing) redirect('/index.php');
if ($listing['seller_id'] != $me['id'] && $me['role'] !== 'admin') redirect('/index.php');

Database::query('DELETE FROM listings WHERE id = ?', [$id]);
flash('success', 'Listing deleted.');
redirect('/index.php');
