<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Step 1 - PHP is working</h2>";

require_once __DIR__ . '/config.php';
echo "<h2>Step 2 - config.php loaded</h2>";

require_once __DIR__ . '/includes/Database.php';
echo "<h2>Step 3 - Database.php loaded</h2>";

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    echo "<h2 style='color:green'>Step 4 - Database connected! ✅</h2>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Step 4 - Connection FAILED ❌</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

require_once __DIR__ . '/includes/auth.php';
echo "<h2>Step 5 - auth.php loaded</h2>";

require_once __DIR__ . '/includes/helpers.php';
echo "<h2>Step 6 - helpers.php loaded</h2>";

echo "<h2 style='color:green'>All done - no errors! ✅</h2>";