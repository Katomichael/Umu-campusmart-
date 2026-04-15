<?php
/**
 * Remove dummy users created by scripts/seed_dummy_listings.php.
 *
 * Safety: only deletes a user if they have zero references in core tables.
 * Run: php scripts/remove_dummy_users.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$emails = [
    'alex.kato@stud.umu.ac.ug',
    'maria.nankya@stud.umu.ac.ug',
    'brian.ssekandi@stud.umu.ac.ug',
    'sarah.atukunda@stud.umu.ac.ug',
];

function refCount(string $sql, array $params): int {
    $row = Database::fetchOne($sql, $params);
    return (int)($row['c'] ?? 0);
}

$deleted = 0;
$skipped = 0;

foreach ($emails as $email) {
    $user = Database::fetchOne('SELECT id, email, full_name FROM users WHERE email = ? LIMIT 1', [$email]);
    if (!$user) {
        echo "Not found: {$email}\n";
        continue;
    }

    $uid = (int)$user['id'];

    $refs = [
        'listings.seller_id' => refCount('SELECT COUNT(*) AS c FROM listings WHERE seller_id = ?', [$uid]),
        'messages.sender_id' => refCount('SELECT COUNT(*) AS c FROM messages WHERE sender_id = ?', [$uid]),
        'messages.receiver_id' => refCount('SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ?', [$uid]),
        'reviews.reviewer_id' => refCount('SELECT COUNT(*) AS c FROM reviews WHERE reviewer_id = ?', [$uid]),
        'reviews.reviewed_id' => refCount('SELECT COUNT(*) AS c FROM reviews WHERE reviewed_id = ?', [$uid]),
        'reports.reporter_id' => refCount('SELECT COUNT(*) AS c FROM reports WHERE reporter_id = ?', [$uid]),
        'saved_listings.user_id' => refCount('SELECT COUNT(*) AS c FROM saved_listings WHERE user_id = ?', [$uid]),
    ];

    $totalRefs = array_sum($refs);

    if ($totalRefs !== 0) {
        $skipped++;
        echo "SKIP {$email} (uid={$uid}) — has references: " . json_encode($refs) . "\n";
        continue;
    }

    Database::query('DELETE FROM users WHERE id = ? LIMIT 1', [$uid]);
    $deleted++;
    echo "Deleted: {$email} (uid={$uid})\n";
}

echo "\nDummy user cleanup complete. Deleted: {$deleted}, skipped: {$skipped}.\n";
