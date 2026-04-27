<?php
/**
 * Seed 20 dummy listings across existing categories.
 *
 * Run: php scripts/seed_dummy_listings.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.\n";
    exit(1);
}

function ensureUser(string $fullName, string $email, string $password = 'Password123!'): int {
    $existing = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing && isset($existing['id'])) {
        return (int)$existing['id'];
    }

    $hash = hashPassword($password);
    return Database::insert(
        'INSERT INTO users (full_name, email, password_hash, is_verified) VALUES (?,?,?,1)',
        [$fullName, $email, $hash]
    );
}

function getCategoryIdBySlug(string $slug): int {
    $row = Database::fetchOne('SELECT id FROM categories WHERE slug = ?', [$slug]);
    if (!$row || !isset($row['id'])) {
        throw new RuntimeException("Missing category slug: {$slug}");
    }
    return (int)$row['id'];
}

function listingExists(string $title): bool {
    $row = Database::fetchOne('SELECT 1 FROM listings WHERE title = ? LIMIT 1', [$title]);
    return (bool)$row;
}

$sellers = [
    ensureUser('Alex Kato', 'alex.kato@stud.umu.ac.ug'),
    ensureUser('Maria Nankya', 'maria.nankya@stud.umu.ac.ug'),
    ensureUser('Brian Ssekandi', 'brian.ssekandi@stud.umu.ac.ug'),
    ensureUser('Sarah Atukunda', 'sarah.atukunda@stud.umu.ac.ug'),
];

$cat = [
    'electronics' => getCategoryIdBySlug('electronics'),
    'textbooks' => getCategoryIdBySlug('textbooks'),
    'clothing' => getCategoryIdBySlug('clothing'),
    'appliances' => getCategoryIdBySlug('appliances'),
    'furniture' => getCategoryIdBySlug('furniture'),
    'sports' => getCategoryIdBySlug('sports'),
    'stationery' => getCategoryIdBySlug('stationery'),
    'other' => getCategoryIdBySlug('other'),
];

$listings = [
    // Electronics
    ['Wireless Earbuds (Good)', 'electronics', 65000, 'good', 'Clean sound, charging case included.'],
    ['HP Laptop Charger 65W', 'electronics', 45000, 'like_new', 'Works with most HP 19.5V laptops.'],
    ['Samsung A12 (Used)', 'electronics', 280000, 'fair', 'Battery ok, minor scratches, dual SIM.'],

    // Textbooks
    ['Calculus 1 & 2 Notes (Printed)', 'textbooks', 25000, 'good', 'Well-organized notes for first year.'],
    ['Introduction to Programming (C) Book', 'textbooks', 40000, 'good', 'Great for beginners, no torn pages.'],
    ['Microeconomics Past Papers Bundle', 'textbooks', 15000, 'like_new', 'Past papers + marking guides.'],

    // Clothing
    ['Hoodie (Maroon) Size M', 'clothing', 35000, 'good', 'Warm hoodie, lightly worn.'],
    ['Formal Shoes Size 42', 'clothing', 80000, 'like_new', 'Worn twice, very clean.'],

    // Appliances
    ['Electric Kettle 1.8L', 'appliances', 60000, 'good', 'Boils fast, works perfectly.'],
    ['Mini Blender (Portable)', 'appliances', 75000, 'like_new', 'Great for smoothies in hostel.'],

    // Furniture
    ['Study Table (Compact)', 'furniture', 120000, 'good', 'Solid wood, fits small rooms.'],
    ['Plastic Chair (Strong)', 'furniture', 20000, 'good', 'No cracks, stable.'],

    // Sports
    ['Football (Size 5)', 'sports', 35000, 'good', 'Good grip, used a few times.'],
    ['Skipping Rope', 'sports', 15000, 'new', 'Brand new, adjustable length.'],

    // Stationery
    ['Scientific Calculator (Casio)', 'stationery', 90000, 'like_new', 'All buttons working, includes cover.'],
    ['A4 Counter Books (Pack of 4)', 'stationery', 22000, 'new', 'New books, 200 pages each.'],
    ['Highlighters Set (6 colors)', 'stationery', 18000, 'new', 'Bright colors, sealed pack.'],

    // Other
    ['Room LED Strip Lights', 'other', 30000, 'like_new', 'RGB lights with remote controller.'],
    ['Backpack (Large)', 'other', 55000, 'good', 'Many pockets, zipper ok.'],
    ['USB Flash Drive 32GB', 'other', 25000, 'new', 'New, sealed.'],
];

$inserted = 0;
$skipped = 0;

foreach ($listings as $i => [$title, $slug, $price, $condition, $description]) {
    if (listingExists($title)) {
        $skipped++;
        continue;
    }

    $sellerId = $sellers[$i % count($sellers)];
    $categoryId = $cat[$slug];
    $isFeatured = ($i % 7 === 0) ? 1 : 0;

    Database::insert(
        'INSERT INTO listings (seller_id, category_id, title, description, price, condition_type, status, is_featured) VALUES (?,?,?,?,?,?,?,?)',
        [$sellerId, $categoryId, $title, $description, $price, $condition, 'active', $isFeatured]
    );

    $inserted++;
}

echo "Seed complete. Inserted: {$inserted}, skipped (already existed): {$skipped}.\n";
