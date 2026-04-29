<?php
require_once __DIR__ . '/includes/bootstrap.php';

echo "=== Search Diagnosis ===\n\n";

// Test 1: Count active listings
$total = Database::fetchOne('SELECT COUNT(*) AS c FROM listings WHERE status = ?', ['active']);
echo "1. Total active listings: " . ($total['c'] ?? 0) . "\n\n";

// Test 2: Try FULLTEXT search for "sneakers"
echo "2. FULLTEXT search for 'sneakers*':\n";
try {
    $result = Database::fetchAll(
        "SELECT id, title FROM listings WHERE MATCH(title, description) AGAINST(? IN BOOLEAN MODE) LIMIT 5",
        ['sneakers*']
    );
    echo "   Results: " . count($result) . "\n";
    foreach ($result as $r) {
        echo "   - #" . $r['id'] . " " . $r['title'] . "\n";
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// Test 3: Try LIKE search for "sneakers"
echo "\n3. LIKE search for 'sneakers':\n";
try {
    $result = Database::fetchAll(
        "SELECT id, title FROM listings WHERE (title LIKE ? OR description LIKE ?) LIMIT 5",
        ['%sneakers%', '%sneakers%']
    );
    echo "   Results: " . count($result) . "\n";
    foreach ($result as $r) {
        echo "   - #" . $r['id'] . " " . $r['title'] . "\n";
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Check if listings have images
echo "\n4. Listings without primary images:\n";
try {
    $result = Database::fetchAll(
        "SELECT l.id, l.title, 
                (SELECT COUNT(*) FROM listing_images WHERE listing_id = l.id) AS total_images,
                (SELECT COUNT(*) FROM listing_images WHERE listing_id = l.id AND is_primary = 1) AS primary_images
         FROM listings l WHERE l.status = 'active' LIMIT 5"
    );
    foreach ($result as $r) {
        echo sprintf("   #%d %s | total=%d, primary=%d\n", 
            $r['id'], $r['title'], $r['total_images'], $r['primary_images']);
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Check FULLTEXT index exists
echo "\n5. FULLTEXT index status on listings table:\n";
try {
    $indexes = Database::fetchAll("SHOW INDEX FROM listings WHERE Key_name LIKE '%ft_%'");
    if (empty($indexes)) {
        echo "   WARNING: No FULLTEXT index found!\n";
    } else {
        echo "   FULLTEXT indexes found: " . count($indexes) . "\n";
        foreach ($indexes as $idx) {
            echo "   - " . ($idx['Key_name'] ?? 'unknown') . "\n";
        }
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
