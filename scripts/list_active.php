<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$rows = Database::fetchAll('SELECT id, title, seller_id, created_at FROM listings WHERE status = ? ORDER BY created_at DESC', ['active']);

echo "Total active listings: " . count($rows) . "\n\n";
foreach ($rows as $r) {
    echo sprintf("#%d | %s | seller=%d | %s\n", $r['id'], $r['title'], $r['seller_id'], $r['created_at']);
}
