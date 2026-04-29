<?php
/**
 * Ensure upload directories exist and have proper permissions
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$dirs = [
    UPLOAD_DIR . 'listings/',
    UPLOAD_DIR . 'avatars/',
    UPLOAD_DIR . 'messages/',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0755, true)) {
            echo "✓ Created directory: " . str_replace(UPLOAD_DIR, 'uploads/', $dir) . "\n";
        } else {
            echo "✗ Failed to create directory: " . str_replace(UPLOAD_DIR, 'uploads/', $dir) . "\n";
            exit(1);
        }
    } else {
        echo "✓ Directory exists: " . str_replace(UPLOAD_DIR, 'uploads/', $dir) . "\n";
    }
    
    // Check if writable
    if (!is_writable($dir)) {
        if (@chmod($dir, 0755)) {
            echo "  ✓ Set permissions to 0755\n";
        } else {
            echo "  ✗ Cannot write to directory (check permissions)\n";
        }
    }
}

echo "\n✓ All upload directories verified.\n";
?>
