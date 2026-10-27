<?php
/**
 * Rebuild-Skript: erzeugt Embeddings fuer bestehende Tierbilder neu.
 *
 * Usage:
 *   php backend/scripts/rebuild_all_embeddings.php
 *   php backend/scripts/rebuild_all_embeddings.php --dry-run
 *   php backend/scripts/rebuild_all_embeddings.php --pet-id=4
 *   php backend/scripts/rebuild_all_embeddings.php --limit=20
 */

if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

require_once __DIR__ . '/../config/Database.php';

$opts = getopt('', ['dry-run', 'pet-id:', 'limit:']);
$dryRun = array_key_exists('dry-run', $opts);
$petIdFilter = isset($opts['pet-id']) ? (int)$opts['pet-id'] : null;
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : null;

$aiServiceUrl = AI_SERVICE_URL . '/api/generate_embedding';
$baseUploadDir = realpath(__DIR__ . '/../uploads/pets');

if ($baseUploadDir === false) {
    exit("Fehler: Upload-Verzeichnis backend/uploads/pets nicht gefunden.\n");
}

try {
    $db = Database::getInstance()->getConnection();

    $sql = "SELECT id, name, profile_image FROM pets";
    $params = [];

    if ($petIdFilter !== null && $petIdFilter > 0) {
        $sql .= " WHERE id = ?";
        $params[] = $petIdFilter;
    }

    $sql .= " ORDER BY id ASC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$pets) {
        exit("Keine Pets gefunden.\n");
    }

    $total = count($pets);
    $success = 0;
    $skipped = 0;
    $failed = 0;

    echo "=== EMBEDDING REBUILD START ===\n";
    echo "Gesamt: {$total}" . ($dryRun ? " (DRY RUN)" : "") . "\n\n";

    foreach ($pets as $pet) {
        $petId = (int)$pet['id'];
        $petName = $pet['name'] ?? ('pet_' . $petId);
        $imagePath = null;

        if (!empty($pet['profile_image'])) {
            $candidate = $pet['profile_image'];
            if (file_exists($candidate)) {
                $imagePath = realpath($candidate);
            } else {
                $local = $baseUploadDir . DIRECTORY_SEPARATOR . basename($candidate);
                if (file_exists($local)) {
                    $imagePath = realpath($local);
                }
            }
        }

        // Fallback: letztes gespeichertes Bild aus pet_images verwenden.
        if ($imagePath === null) {
            $imgStmt = $db->prepare(
                "SELECT image_path FROM pet_images WHERE pet_id = ? ORDER BY uploaded_at DESC, id DESC LIMIT 1"
            );
            $imgStmt->execute([$petId]);
            $imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC);

            if ($imgRow && !empty($imgRow['image_path'])) {
                $candidate = $imgRow['image_path'];
                if (file_exists($candidate)) {
                    $imagePath = realpath($candidate);
                } else {
                    $local = $baseUploadDir . DIRECTORY_SEPARATOR . basename($candidate);
                    if (file_exists($local)) {
                        $imagePath = realpath($local);
                    }
                }
            }
        }

        if ($imagePath === null) {
            echo "[SKIP] Pet {$petId} ({$petName}): Kein gueltiger Bildpfad gefunden\n";
            $skipped++;
            continue;
        }

        if ($dryRun) {
            echo "[DRY ] Pet {$petId} ({$petName}): wuerde Embedding neu erzeugen mit {$imagePath}\n";
            $success++;
            continue;
        }

        $ch = curl_init($aiServiceUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'image_path' => $imagePath,
                'pet_id' => $petId
            ]),
            CURLOPT_TIMEOUT => 45
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            echo "[FAIL] Pet {$petId} ({$petName}): cURL Fehler: {$curlError}\n";
            $failed++;
            continue;
        }

        if ($httpCode !== 200) {
            echo "[FAIL] Pet {$petId} ({$petName}): HTTP {$httpCode}\n";
            $failed++;
            continue;
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data) || empty($data['success']) || empty($data['embedding'])) {
            $msg = is_array($data) && isset($data['message']) ? $data['message'] : 'ungueltige AI-Antwort';
            echo "[FAIL] Pet {$petId} ({$petName}): {$msg}\n";
            $failed++;
            continue;
        }

        $embeddingBinary = base64_decode($data['embedding'], true);
        if ($embeddingBinary === false) {
            echo "[FAIL] Pet {$petId} ({$petName}): Base64 decode fehlgeschlagen\n";
            $failed++;
            continue;
        }

        try {
            $db->beginTransaction();

            $resetStmt = $db->prepare("UPDATE pet_images SET is_primary = FALSE WHERE pet_id = ?");
            $resetStmt->execute([$petId]);

            $insertStmt = $db->prepare(
                "INSERT INTO pet_images (pet_id, image_path, embedding, embedding_model, is_primary) VALUES (?, ?, ?, 'resnet50', TRUE)"
            );
            $insertStmt->bindParam(1, $petId, PDO::PARAM_INT);
            $insertStmt->bindParam(2, $imagePath, PDO::PARAM_STR);
            $insertStmt->bindParam(3, $embeddingBinary, PDO::PARAM_LOB);
            $insertStmt->execute();

            $db->commit();
            echo "[ OK ] Pet {$petId} ({$petName}): Embedding neu erstellt\n";
            $success++;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo "[FAIL] Pet {$petId} ({$petName}): DB Fehler: {$e->getMessage()}\n";
            $failed++;
        }
    }

    echo "\n=== EMBEDDING REBUILD ENDE ===\n";
    echo "Erfolgreich: {$success}\n";
    echo "Uebersprungen: {$skipped}\n";
    echo "Fehlgeschlagen: {$failed}\n";
    echo "Gesamt: {$total}\n";

    if ($dryRun) {
        echo "Hinweis: Das war ein Dry-Run. Keine Daten wurden veraendert.\n";
    }
} catch (Exception $e) {
    exit("Fataler Fehler: " . $e->getMessage() . "\n");
}
