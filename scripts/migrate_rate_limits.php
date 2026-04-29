<?php
/**
 * Migration: Create rate_limits table for brute-force protection
 * Run: php scripts/migrate_rate_limits.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Database::query('
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            endpoint VARCHAR(100) NOT NULL COMMENT "login, register, etc",
            attempt_count INT DEFAULT 1,
            first_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            lockout_until DATETIME,
            UNIQUE KEY unique_ip_endpoint (ip_address, endpoint),
            INDEX idx_cleanup (last_attempt_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    
    echo "✓ rate_limits table created successfully.\n";
} catch (Exception $e) {
    echo "✗ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
