<?php
/**
 * Update Script: Add species column to finder_reports table
 */

require_once __DIR__ . '/backend/config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM finder_reports LIKE 'species'");
    if ($stmt->rowCount() > 0) {
        echo "Species column already exists!\n";
        exit(0);
    }
    
    // Add species column
    $sql = "ALTER TABLE finder_reports 
            ADD COLUMN species ENUM('dog', 'cat') DEFAULT NULL AFTER image_path";
    
    $db->exec($sql);
    
    echo "✓ Species column added successfully to finder_reports table!\n";
    echo "You can now filter matching by species.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
