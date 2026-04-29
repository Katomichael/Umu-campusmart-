<?php
/**
 * Migration: Add image_path column to messages table
 * Purpose: Enable image sharing in the chatroom
 * 
 * Usage: php scripts/migrate_message_images.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    // Check if column already exists
    $checkColumn = Database::fetchOne(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_NAME = 'messages' AND COLUMN_NAME = 'image_path' AND TABLE_SCHEMA = ?",
        [DB_NAME]
    );

    if ($checkColumn) {
        echo "✓ Column 'image_path' already exists in messages table\n";
        exit(0);
    }

    // Add the image_path column
    Database::query(
        "ALTER TABLE messages ADD COLUMN image_path VARCHAR(255) AFTER content"
    );

    echo "✓ Successfully added 'image_path' column to messages table\n";
    echo "  Column type: VARCHAR(255)\n";
    echo "  Stores relative path to uploaded message images\n";

} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
