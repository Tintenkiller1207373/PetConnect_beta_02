<?php
/**
 * Test Script - Similarity zwischen Finder und vermisstem Tier prüfen
 */

require_once __DIR__ . '/config/Database.php';

$db = Database::getInstance()->getConnection();
$ai_service_url = 'http://localhost:8002/api';

echo "=== SIMILARITY TEST ===\n\n";

// 1. Vermisstes Tier holen
$stmt = $db->query(
    "SELECT p.id, p.name, pi.embedding
     FROM pets p
     JOIN pet_images pi ON p.id = pi.pet_id
     WHERE p.is_missing = TRUE AND pi.is_primary = TRUE
     LIMIT 1"
);
$missing_pet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$missing_pet) {
    die("Kein vermisstes Tier mit Embedding gefunden!\n");
}

echo "Vermisstes Tier: {$missing_pet['name']} (ID: {$missing_pet['id']})\n";
echo "Embedding Größe: " . strlen($missing_pet['embedding']) . " bytes\n\n";

// 2. Alle Finder Reports holen
$stmt = $db->query(
    "SELECT id, finder_contact, embedding, created_at
     FROM finder_reports
     ORDER BY created_at DESC"
);
$finder_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Anzahl Finder Reports: " . count($finder_reports) . "\n\n";

// 3. Jeden Finder Report mit dem vermissten Tier vergleichen
foreach ($finder_reports as $report) {
    echo "--- Finder Report ID: {$report['id']} ---\n";
    echo "Kontakt: {$report['finder_contact']}\n";
    echo "Erstellt: {$report['created_at']}\n";
    
    if (empty($report['embedding'])) {
        echo "⚠️  KEIN EMBEDDING!\n\n";
        continue;
    }
    
    echo "Embedding Größe: " . strlen($report['embedding']) . " bytes\n";
    
    // AI Service aufrufen
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $ai_service_url . '/compare_embeddings',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'embedding1' => base64_encode($report['embedding']),
            'embedding2' => base64_encode($missing_pet['embedding'])
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if ($data['success']) {
            $similarity = $data['similarity'];
            echo "✓ Similarity Score: " . round($similarity * 100, 2) . "%\n";
            
            // Bewertung
            if ($similarity >= 0.70) {
                echo "  → SEHR ÄHNLICH! 🎯\n";
            } elseif ($similarity >= 0.50) {
                echo "  → MÖGLICH MATCH 🤔\n";
            } elseif ($similarity >= 0.40) {
                echo "  → SCHWACHE ÄHNLICHKEIT ⚠️\n";
            } else {
                echo "  → NICHT ÄHNLICH ❌\n";
            }
            
            echo "  Threshold 0.45 (45%): " . ($similarity >= 0.45 ? "WÜRDE MATCHEN ✓" : "ZU NIEDRIG ✗") . "\n";
        } else {
            echo "✗ AI Service Error: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "✗ HTTP Error: $http_code\n";
        if ($curl_error) {
            echo "  cURL Error: $curl_error\n";
        }
        echo "  Response: $response\n";
    }
    
    echo "\n";
}

echo "=== TEST COMPLETE ===\n";
?>
