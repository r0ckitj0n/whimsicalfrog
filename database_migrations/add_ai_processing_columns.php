<?php
/**
 * Database Migration: Add AI Processing Columns
 * 
 * Adds columns to support AI image processing tracking
 * Run this script once to update the database schema
 */

require_once __DIR__ . '/../api/config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    echo "Starting AI processing columns migration...\n";
    
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE item_images");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $columnsToAdd = [
        'processed_with_ai' => 'TINYINT(1) DEFAULT 0',
        'original_path' => 'VARCHAR(500) NULL',
        'processing_date' => 'TIMESTAMP NULL',
        'ai_trim_data' => 'JSON NULL'
    ];
    
    $addedColumns = [];
    
    foreach ($columnsToAdd as $columnName => $columnDefinition) {
        if (!in_array($columnName, $columns)) {
            $sql = "ALTER TABLE item_images ADD COLUMN {$columnName} {$columnDefinition}";
            $pdo->exec($sql);
            $addedColumns[] = $columnName;
            echo "✓ Added column: {$columnName}\n";
        } else {
            echo "- Column already exists: {$columnName}\n";
        }
    }
    
    // Add indexes for better performance
    $indexesToAdd = [
        'idx_processed_with_ai' => 'processed_with_ai',
        'idx_processing_date' => 'processing_date'
    ];
    
    foreach ($indexesToAdd as $indexName => $indexColumn) {
        try {
            $sql = "CREATE INDEX {$indexName} ON item_images ({$indexColumn})";
            $pdo->exec($sql);
            echo "✓ Added index: {$indexName}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "- Index already exists: {$indexName}\n";
            } else {
                echo "! Warning: Could not create index {$indexName}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Update existing records to set default values
    if (!empty($addedColumns)) {
        echo "\nUpdating existing records...\n";
        
        // Set processed_with_ai to 0 for all existing records
        $stmt = $pdo->prepare("UPDATE item_images SET processed_with_ai = 0 WHERE processed_with_ai IS NULL");
        $stmt->execute();
        $updatedRows = $stmt->rowCount();
        echo "✓ Updated {$updatedRows} existing records with default AI processing status\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "The item_images table now supports AI processing tracking.\n\n";
    
    // Show current table structure
    echo "Current item_images table structure:\n";
    echo "=====================================\n";
    $stmt = $pdo->query("DESCRIBE item_images");
    $tableStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tableStructure as $column) {
        $nullable = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column['Default'] !== null ? "DEFAULT '{$column['Default']}'" : '';
        echo sprintf("%-20s %-20s %-10s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $nullable, 
            $default
        );
    }
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 