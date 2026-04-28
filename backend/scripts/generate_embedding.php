<?php
/**
 * Hintergrund-Skript zur Embedding-Generierung
 * Wird asynchron von Pet::generateEmbeddingAsync() aufgerufen
 */

// CLI-Only (kein Browser-Zugriff)
if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

// Argumente prüfen
if ($argc < 3) {
    exit("Usage: php generate_embedding.php <pet_id> <image_path>\n");
}

$pet_id = intval($argv[1]);
$image_path = $argv[2];

// Config und Database laden
require_once __DIR__ . '/../config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Python AI-Service aufrufen (gleiches Endpoint-Format wie im Finder-Flow)
    $ai_service_url = 'http://localhost:8002/api/generate_embedding';
    
    // Vollständiger Pfad zur Bilddatei
    if (!file_exists($image_path)) {
        error_log("Embedding-Generierung fehlgeschlagen: Datei nicht gefunden: $image_path");
        exit(1);
    }
    
    $ch = curl_init($ai_service_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'image_path' => $image_path,
            'pet_id' => $pet_id
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("Embedding-Generierung CURL-Fehler: " . curl_error($ch));
        curl_close($ch);
        exit(1);
    }
    
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Embedding-Generierung fehlgeschlagen. HTTP-Code: $http_code");
        exit(1);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['embedding'])) {
        error_log("Embedding-Generierung: Kein Embedding in Antwort");
        exit(1);
    }
    
    // Base64-decodiertes binäres Embedding
    $embedding_binary = base64_decode($result['embedding']);
    
    // Vorhandene Primärbilder zurücksetzen, neues Embedding als primär speichern
    $stmt_reset_primary = $db->prepare("UPDATE pet_images SET is_primary = FALSE WHERE pet_id = :pet_id");
    $stmt_reset_primary->bindParam(':pet_id', $pet_id);
    $stmt_reset_primary->execute();

    $stmt = $db->prepare("
        INSERT INTO pet_images (pet_id, image_path, embedding, embedding_model, is_primary)
        VALUES (:pet_id, :image_path, :embedding, 'resnet50', TRUE)
    ");

    $stmt->bindParam(':pet_id', $pet_id);
    $stmt->bindParam(':image_path', $image_path);
    $stmt->bindParam(':embedding', $embedding_binary, PDO::PARAM_LOB);

    if ($stmt->execute()) {
        error_log("Embedding erfolgreich generiert für Pet ID: $pet_id");
        exit(0);
    } else {
        error_log("Embedding-Generierung: Datenbank-Fehler beim Speichern");
        exit(1);
    }
    
} catch (Exception $e) {
    error_log("Embedding-Generierung Exception: " . $e->getMessage());
    exit(1);
}
