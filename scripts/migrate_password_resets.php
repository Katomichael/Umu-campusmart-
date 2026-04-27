<?php
// One-time migration: create password_resets table (for forgot/reset password).

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "CampusMart migration: password_resets\n\n";

Database::query(
    "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        requested_ip VARCHAR(45),
        requested_ua VARCHAR(255),
        UNIQUE KEY uq_token (token_hash),
        INDEX idx_user_created (user_id, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
);

echo "OK: password_resets table is ready.\n";
