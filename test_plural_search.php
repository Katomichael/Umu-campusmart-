<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

function testSearch($searchTerm) {
    echo "\n=== Testing search for: '$searchTerm' ===\n";
    
    $search = $searchTerm;
    $searchTerms = [$search];
    
    // Add plural variant if search ends with 's'
    if (substr($search, -1) === 's' && strlen($search) > 1) {
        $searchTerms[] = substr($search, 0, -1); // Remove trailing 's'
    }
    // Add singular variant if search doesn't end with 's'
    elseif (substr($search, -1) !== 's') {
        $searchTerms[] = $search . 's'; // Add 's'
    }
    
    echo "Search terms to use: " . implode(', ', $searchTerms) . "\n";
    
    $params = ['active'];
    $termClauses = [];
    foreach ($searchTerms as $term) {
        $termClauses[] = '(l.title LIKE ? OR l.description LIKE ?)';
        $params[] = '%' . $term . '%';
        $params[] = '%' . $term . '%';
    }
    
    $where = 'WHERE l.status = ? AND (' . implode(' OR ', $termClauses) . ')';
    
    $result = Database::fetchAll(
        "SELECT l.id, l.title FROM listings l $where",
        $params
    );
    
    echo "Results: " . count($result) . "\n";
    foreach ($result as $r) {
        echo "  #" . $r['id'] . " | " . $r['title'] . "\n";
    }
}

testSearch('jerseys');
testSearch('jersey');
testSearch('shoe');
testSearch('shoes');
