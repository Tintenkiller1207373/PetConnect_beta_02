<?php
/**
 * Debug Script - Datenbank Status prüfen
 */

require_once __DIR__ . '/config/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== PETCONNECT DATABASE DIAGNOSTICS ===\n\n";

// 1. Alle Tiere
$stmt = $db->query("SELECT COUNT(*) as total FROM pets");
$total_pets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "1. Gesamt Tiere in DB: " . $total_pets . "\n";

// 2. Vermisste Tiere
$stmt = $db->query("SELECT COUNT(*) as total FROM pets WHERE is_missing = TRUE");
$missing_pets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "2. Vermisste Tiere (is_missing=TRUE): " . $missing_pets . "\n";

// 3. Missing Reports
$stmt = $db->query("SELECT COUNT(*) as total FROM missing_reports");
$missing_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "3. Missing Reports Gesamt: " . $missing_reports . "\n";

$stmt = $db->query("SELECT COUNT(*) as total FROM missing_reports WHERE status='active'");
$active_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "4. Active Missing Reports: " . $active_reports . "\n";

// 4. Pet Images
$stmt = $db->query("SELECT COUNT(*) as total FROM pet_images");
$total_images = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "5. Gesamt Pet Images: " . $total_images . "\n";

$stmt = $db->query("SELECT COUNT(*) as total FROM pet_images WHERE is_primary = TRUE");
$primary_images = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "6. Primary Images: " . $primary_images . "\n";

$stmt = $db->query("SELECT COUNT(*) as total FROM pet_images WHERE embedding IS NOT NULL");
$images_with_embedding = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "7. Images mit Embeddings: " . $images_with_embedding . "\n";

// 5. Finder Reports
$stmt = $db->query("SELECT COUNT(*) as total FROM finder_reports");
$finder_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "8. Finder Reports: " . $finder_reports . "\n";

// 6. Match Results
$stmt = $db->query("SELECT COUNT(*) as total FROM match_results");
$match_results = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "9. Match Results: " . $match_results . "\n\n";

// DETAILS FÜR VERMISSTE TIERE
echo "=== VERMISSTE TIERE DETAILS ===\n";
$stmt = $db->query(
    "SELECT p.id, p.name, p.species, p.is_missing, 
            mr.status as report_status,
            pi.id as image_id, pi.is_primary,
            CASE WHEN pi.embedding IS NULL THEN 'NEIN' ELSE 'JA' END as has_embedding
     FROM pets p
     LEFT JOIN missing_reports mr ON p.id = mr.pet_id
     LEFT JOIN pet_images pi ON p.id = pi.pet_id
     WHERE p.is_missing = TRUE"
);

$details = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($details) > 0) {
    foreach ($details as $pet) {
        echo "\nPet ID: {$pet['id']} - {$pet['name']} ({$pet['species']})\n";
        echo "  - is_missing: " . ($pet['is_missing'] ? 'TRUE' : 'FALSE') . "\n";
        echo "  - report_status: " . ($pet['report_status'] ?? 'KEIN REPORT') . "\n";
        echo "  - image_id: " . ($pet['image_id'] ?? 'KEIN BILD') . "\n";
        echo "  - is_primary: " . ($pet['is_primary'] ? 'TRUE' : 'FALSE') . "\n";
        echo "  - has_embedding: " . $pet['has_embedding'] . "\n";
    }
} else {
    echo "\n⚠️  KEINE vermissten Tiere gefunden!\n";
    echo "Bitte melden Sie zuerst ein Tier als vermisst über die Dashboard-Seite.\n";
}

echo "\n=== FINDER REPORTS DETAILS ===\n";
$stmt = $db->query(
    "SELECT fr.id, fr.created_at, fr.finder_contact,
            CASE WHEN fr.embedding IS NULL THEN 'NEIN' ELSE 'JA' END as has_embedding
     FROM finder_reports fr
     ORDER BY fr.created_at DESC
     LIMIT 5"
);

$finder_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($finder_details) > 0) {
    foreach ($finder_details as $report) {
        echo "\nFinder Report ID: {$report['id']}\n";
        echo "  - Erstellt: {$report['created_at']}\n";
        echo "  - Kontakt: {$report['finder_contact']}\n";
        echo "  - has_embedding: {$report['has_embedding']}\n";
    }
} else {
    echo "\nKeine Finder Reports vorhanden.\n";
}

echo "\n=== DIAGNOSE COMPLETE ===\n";
?>
