<?php
/**
 * Remove the 20 dummy listings created by scripts/seed_dummy_listings.php.
 *
 * Run: php scripts/remove_dummy_listings.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$titles = [
    'Wireless Earbuds (Good)',
    'HP Laptop Charger 65W',
    'Samsung A12 (Used)',
    'Calculus 1 & 2 Notes (Printed)',
    'Introduction to Programming (C) Book',
    'Microeconomics Past Papers Bundle',
    'Hoodie (Maroon) Size M',
    'Formal Shoes Size 42',
    'Electric Kettle 1.8L',
    'Mini Blender (Portable)',
    'Study Table (Compact)',
    'Plastic Chair (Strong)',
    'Football (Size 5)',
    'Skipping Rope',
    'Scientific Calculator (Casio)',
    'A4 Counter Books (Pack of 4)',
    'Highlighters Set (6 colors)',
    'Room LED Strip Lights',
    'Backpack (Large)',
    'USB Flash Drive 32GB',
];

$placeholders = implode(',', array_fill(0, count($titles), '?'));

$before = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS c FROM listings WHERE title IN ($placeholders)",
    $titles
)['c'] ?? 0);

Database::query(
    "DELETE FROM listings WHERE title IN ($placeholders)",
    $titles
);

$after = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS c FROM listings WHERE title IN ($placeholders)",
    $titles
)['c'] ?? 0);

$deleted = $before - $after;

echo "Dummy listing cleanup complete. Deleted: {$deleted}. Remaining matches: {$after}.\n";
