<?php
/**
 * PetConnect - DB-Initialisierung beim Container-Start
 * Wartet auf MySQL, importiert database/schema.sql falls die Tabellen fehlen
 * und ergänzt Spalten, die in älteren Schema-Versionen fehlten.
 */

if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../backend/config/Config.php';

$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
$pdo = null;

for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $e) {
        echo "[init-db] Warte auf MySQL (" . DB_HOST . ":" . DB_PORT . "), Versuch $attempt/30: " . $e->getMessage() . "\n";
        sleep(2);
    }
}

if (!$pdo) {
    fwrite(STDERR, "[init-db] MySQL nicht erreichbar - Abbruch\n");
    exit(1);
}

try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', DB_NAME) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Ohne CREATE-Recht: Datenbank muss bereits existieren (z.B. via MYSQL_DATABASE)
}
$pdo->exec("USE `" . str_replace('`', '', DB_NAME) . "`");

$hasUsers = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();

if (!$hasUsers) {
    echo "[init-db] Tabellen fehlen - importiere database/schema.sql\n";
    $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
    // Kommentarzeilen entfernen, dann an ';' trennen
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    echo "[init-db] Schema importiert\n";
} else {
    // Migrationen für bestehende Datenbanken
    $hasSpecies = $pdo->query("SHOW COLUMNS FROM finder_reports LIKE 'species'")->fetchColumn();
    if (!$hasSpecies) {
        $pdo->exec("ALTER TABLE finder_reports ADD COLUMN species ENUM('dog', 'cat') DEFAULT NULL AFTER image_path");
        echo "[init-db] Spalte finder_reports.species ergänzt\n";
    }
    $pdo->exec("ALTER TABLE pets MODIFY COLUMN profile_image VARCHAR(255) NULL");
    echo "[init-db] Datenbank bereit\n";
}
