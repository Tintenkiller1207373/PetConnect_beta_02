<?php
/**
 * Temporäres Skript zum Aktualisieren des Datenbankschemas
 */

require_once __DIR__ . '/backend/config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Aktualisiere pets-Tabelle...\n";
    
    $sql = "ALTER TABLE pets MODIFY COLUMN profile_image VARCHAR(255) NULL";
    $db->exec($sql);
    
    echo "✓ Spalte profile_image akzeptiert jetzt NULL-Werte\n";
    
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
?>
