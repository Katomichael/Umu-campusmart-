<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Simulate search query for "sneakers"
$_GET['search'] = 'sneakers';

// Mimic the search logic from index.php
$search = trim($_GET['search'] ?? '');
$where  = ['l.status = ?'];
$params = ['active'];

if ($search) {
  // Use LIKE for reliable search (FULLTEXT index not working as expected)
  $where[]  = '(l.title LIKE ? OR l.description LIKE ?)';
  $params[] = '%' . $search . '%';
  $params[] = '%' . $search . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$result = Database::fetchAll(
    "SELECT l.id, l.title FROM listings l $whereSql LIMIT 10",
    $params
);

echo "Search for 'sneakers' returns " . count($result) . " results:\n";
foreach ($result as $r) {
    echo "  - #" . $r['id'] . " " . $r['title'] . "\n";
}
