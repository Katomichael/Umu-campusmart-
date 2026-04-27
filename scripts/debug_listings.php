<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "DB OK\n\n";

echo "Schema for listings.status:\n";
try {
    $col = Database::fetchOne("SHOW COLUMNS FROM listings LIKE 'status'");
    if ($col) {
        echo 'Type: ' . ($col['Type'] ?? '') . "\n";
        echo 'Null: ' . ($col['Null'] ?? '') . "\n";
        echo 'Default: ' . (array_key_exists('Default', $col) ? (string)($col['Default'] ?? 'NULL') : '') . "\n";
    } else {
        echo "(status column not found)\n";
    }
} catch (Throwable $e) {
    echo "(failed to inspect schema: {$e->getMessage()})\n";
}

echo "Counts by status:\n";
$rows = Database::fetchAll('SELECT status, COUNT(*) AS n FROM listings GROUP BY status ORDER BY status');
if (!$rows) {
    echo "(no listings found)\n";
} else {
    foreach ($rows as $r) {
        echo ($r['status'] ?? 'NULL') . ': ' . ($r['n'] ?? 0) . "\n";
    }
}

echo "\nLatest 10 listings:\n";
$latest = Database::fetchAll('SELECT id, title, status, seller_id, created_at FROM listings ORDER BY created_at DESC LIMIT 10');
foreach ($latest as $l) {
    echo '#' . ($l['id'] ?? '?')
        . ' [' . ($l['status'] ?? 'NULL') . '] '
        . ($l['title'] ?? '')
        . ' seller=' . ($l['seller_id'] ?? '?')
        . ' ' . ($l['created_at'] ?? '')
        . "\n";
}
