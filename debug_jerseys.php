<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$search = 'jerseys';

// Test 1: LIKE search (what the code should be doing)
echo "=== Test 1: LIKE Search for '$search' ===\n";
$result = Database::fetchAll(
    "SELECT l.id, l.title, l.description, l.status
     FROM listings l
     WHERE l.status='active' AND (l.title LIKE ? OR l.description LIKE ?)",
    ['%' . $search . '%', '%' . $search . '%']
);
echo "Results: " . count($result) . "\n";
foreach ($result as $r) {
    echo "  #" . $r['id'] . " | " . $r['title'] . "\n";
}

// Test 2: Singular form
echo "\n=== Test 2: LIKE Search for 'jersey' ===\n";
$result = Database::fetchAll(
    "SELECT l.id, l.title, l.status
     FROM listings l
     WHERE l.status='active' AND (l.title LIKE ? OR l.description LIKE ?)",
    ['%jersey%', '%jersey%']
);
echo "Results: " . count($result) . "\n";
foreach ($result as $r) {
    echo "  #" . $r['id'] . " | " . $r['title'] . "\n";
}

// Test 3: Check all status values in DB
echo "\n=== Test 3: All listings by status ===\n";
$statuses = Database::fetchAll(
    "SELECT status, COUNT(*) as cnt FROM listings GROUP BY status"
);
foreach ($statuses as $s) {
    echo "  Status '" . $s['status'] . "': " . $s['cnt'] . "\n";
}

// Test 4: All jersey titles regardless of status
echo "\n=== Test 4: ALL jersey listings (any status) ===\n";
$result = Database::fetchAll(
    "SELECT l.id, l.title, l.status
     FROM listings l
     WHERE (l.title LIKE ? OR l.description LIKE ?)",
    ['%jersey%', '%jersey%']
);
echo "Results: " . count($result) . "\n";
foreach ($result as $r) {
    echo "  #" . $r['id'] . " | " . $r['title'] . " [status=" . $r['status'] . "]\n";
}
