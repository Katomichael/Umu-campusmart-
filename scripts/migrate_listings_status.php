<?php
// One-time migration: ensure listings.status supports pending/rejected and normalize blank statuses.

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "CampusMart migration: listings.status\n\n";

$col = Database::fetchOne("SHOW COLUMNS FROM listings LIKE 'status'");
if (!$col) {
    echo "ERROR: listings.status column not found.\n";
    exit(1);
}

$type = (string)($col['Type'] ?? '');
echo "Before:\n";
echo "  Type: {$type}\n";
echo "  Null: " . ($col['Null'] ?? '') . "\n";
echo "  Default: " . (array_key_exists('Default', $col) ? (string)($col['Default'] ?? 'NULL') : '') . "\n\n";

$desired = "enum('pending','active','rejected','sold','reserved','removed')";
$needsAlter = stripos($type, "enum(") === 0 && stripos($type, "'pending'") === false;

if ($needsAlter) {
    echo "Altering listings.status to {$desired} ...\n";
    Database::query(
        "ALTER TABLE listings MODIFY status {$desired} NOT NULL DEFAULT 'pending'"
    );
    echo "ALTER OK\n\n";
} else {
    echo "No ALTER needed (pending already supported).\n\n";
}

echo "Normalizing blank/NULL statuses to 'pending'...\n";
Database::query("UPDATE listings SET status='pending' WHERE status IS NULL OR status='' ");
$fixed = Database::fetchOne("SELECT ROW_COUNT() AS n");
echo "Updated rows: " . (int)($fixed['n'] ?? 0) . "\n\n";

$col2 = Database::fetchOne("SHOW COLUMNS FROM listings LIKE 'status'");
echo "After:\n";
echo "  Type: " . ($col2['Type'] ?? '') . "\n";
echo "  Null: " . ($col2['Null'] ?? '') . "\n";
echo "  Default: " . (array_key_exists('Default', $col2) ? (string)($col2['Default'] ?? 'NULL') : '') . "\n\n";

echo "Counts by status:\n";
$rows = Database::fetchAll('SELECT status, COUNT(*) AS n FROM listings GROUP BY status ORDER BY status');
foreach ($rows as $r) {
    echo ($r['status'] ?? 'NULL') . ': ' . ($r['n'] ?? 0) . "\n";
}

echo "\nDone.\n";
