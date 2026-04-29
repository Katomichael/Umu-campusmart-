<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$date = $argv[1] ?? null;
if (!$date) {
    echo "Usage: php scripts/inspect_by_date.php YYYY-MM-DD\n";
    exit(1);
}

$rows = Database::fetchAll(
    "SELECT l.id, l.title, l.created_at, l.seller_id, u.full_name, u.email,
            (SELECT COUNT(*) FROM listing_images li WHERE li.listing_id = l.id) AS image_count
     FROM listings l
     JOIN users u ON u.id = l.seller_id
     WHERE DATE(l.created_at) = ?
     ORDER BY l.id",
    [$date]
);

if (!$rows) {
    echo "No listings found for date {$date}.\n";
    exit(0);
}

echo "Listings created on {$date}: " . count($rows) . "\n\n";
foreach ($rows as $r) {
    echo sprintf(
        "#%d | %s | seller=%d %s <%s> | %s | images=%d\n",
        (int)$r['id'],
        (string)$r['title'],
        (int)$r['seller_id'],
        (string)$r['full_name'],
        (string)$r['email'],
        (string)$r['created_at'],
        (int)$r['image_count']
    );
}
