<?php
/**
 * Remove listings created on a specific date (safe cleanup).
 * Usage: php scripts/remove_test_batch.php [YYYY-MM-DD]
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$date = $argv[1] ?? '2026-04-28';

$rows = Database::fetchAll(
    'SELECT id, title FROM listings WHERE DATE(created_at) = ? LIMIT 1000',
    [$date]
);

if (!$rows) {
    echo "No listings found for date {$date}.\n";
    exit(0);
}

$ids = array_map(fn($r) => (int)$r['id'], $rows);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

// Collect image paths for cleanup
$images = Database::fetchAll(
    "SELECT image_path FROM listing_images WHERE listing_id IN ($placeholders)",
    $ids
);

$deletedFiles = 0;
foreach ($images as $img) {
    if (empty($img['image_path'])) continue;
    // image_path is stored like 'uploads/listings/filename.jpg'
    $relative = preg_replace('#^uploads/?#', '', $img['image_path']);
    $full = UPLOAD_DIR . $relative; // UPLOAD_DIR ends with '/public/uploads/'
    if (file_exists($full) && is_file($full)) {
        if (@unlink($full)) {
            $deletedFiles++;
        }
    }
}

// Delete listings (cascades will remove listing_images and related rows)
Database::query("DELETE FROM listings WHERE id IN ($placeholders)", $ids);

// Report
echo "Removed " . count($ids) . " listings created on {$date}.\n";
echo "Deleted image files: {$deletedFiles}.\n";

$remaining = Database::fetchOne('SELECT COUNT(*) AS c FROM listings WHERE status = ?', ['active']);
echo "Remaining active listings: " . ($remaining['c'] ?? 0) . "\n";
