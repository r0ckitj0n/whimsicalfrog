<?php
/**
 * Migration Shortcut: Create customer_notes Table
 * Deploy this to the root of the live site and visit /migrate_notes.php
 */
require_once __DIR__ . '/api/config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS customer_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(32) NOT NULL,
        note_text TEXT NOT NULL,
        author_username VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    Database::execute($sql);

    // Also ensure users_meta exists
    $sqlMeta = "CREATE TABLE IF NOT EXISTS users_meta (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        meta_key VARCHAR(191) NOT NULL,
        meta_value TEXT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_meta (user_id, meta_key),
        KEY idx_user (user_id),
        KEY idx_key (meta_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    Database::execute($sqlMeta);

    echo "<h1>Migration Success!</h1>";
    echo "<p>Table <strong>customer_notes</strong> created successfully.</p>";
    echo "<p><em>This file will now delete itself for security.</em></p>";
} catch (Exception $e) {
    echo "<h1>Migration Failed</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit(1);
}

// Security: Self-destruct after execution
unlink(__FILE__);
