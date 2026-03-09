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
    
    // Python AI-Service aufrufen
    $ai_service_url = 'http://localhost:8002/extract_embedding';
    
    // Vollständiger Pfad zur Bilddatei
    if (!file_exists($image_path)) {
        error_log("Embedding-Generierung fehlgeschlagen: Datei nicht gefunden: $image_path");
        exit(1);
    }
    
    $ch = curl_init($ai_service_url);
    
    $file = new CURLFile($image_path, mime_content_type($image_path), basename($image_path));
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => $file],
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
    
    // In Datenbank speichern (REPLACE für Idempotenz)
    $stmt = $db->prepare("
        REPLACE INTO pet_images (pet_id, image_path, embedding, created_at) 
        VALUES (:pet_id, :image_path, :embedding, NOW())
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
