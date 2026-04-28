<?php
/**
 * PetConnect - Pet Model
 * Handling von Tierregistrierung, Verwaltung und Status
 */

require_once __DIR__ . '/../config/Database.php';

class Pet {
    private $db;
    private $upload_dir = __DIR__ . '/../uploads/pets/';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Neues Tier registrieren
     */
    public function createPet($user_id, $name, $species, $breed, $color, $age, $weight, $features, $microchip_id = null) {
        try {
            // Validierung
            if (!in_array($species, ['dog', 'cat'])) {
                return ['success' => false, 'message' => 'Ungültige Tierart'];
            }
            
            $sql = "INSERT INTO pets (user_id, name, species, breed, color, age, weight, distinguishing_features, microchip_id, profile_image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $user_id, $name, $species, $breed, $color, $age, $weight, $features, $microchip_id, ''
            ]);
            
            $pet_id = $this->db->lastInsertId();
            
            // Pet-Daten für Response laden
            $stmt = $this->db->prepare("SELECT * FROM pets WHERE id = ?");
            $stmt->execute([$pet_id]);
            $pet_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'message' => 'Tier erfolgreich registriert',
                'data' => [
                    'pet_id' => $pet_id,
                    'pet' => $pet_data
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Tierprofilbild hochladen (und an KI-Service senden)
     */
    public function uploadProfileImage($pet_id, $file) {
        try {
            // Validierung
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $file['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                return ['success' => false, 'message' => 'Nur JPG, PNG und GIF erlaubt'];
            }
            
            if ($file['size'] > 10 * 1024 * 1024) { // 10MB max
                return ['success' => false, 'message' => 'Datei zu groß (max. 10MB)'];
            }
            
            // Speichern
            $new_filename = 'pet_' . $pet_id . '_' . time() . '.' . $ext;
            $upload_path = $this->upload_dir . $new_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                return ['success' => false, 'message' => 'Upload fehlgeschlagen'];
            }
            
            // In DB speichern
            $stmt = $this->db->prepare("UPDATE pets SET profile_image = ? WHERE id = ?");
            $stmt->execute([$new_filename, $pet_id]);
            
            // Embedding asynchron generieren (blockiert nicht mehr)
            $this->generateEmbeddingAsync($pet_id, $upload_path);
            
            return [
                'success' => true,
                'message' => 'Bild erfolgreich hochgeladen',
                'image' => $new_filename,
                'embedding' => 'processing' // Wird im Hintergrund verarbeitet
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**     * Embedding asynchron generieren (blockiert nicht)
     */
    private function generateEmbeddingAsync($pet_id, $image_path) {
        // PHP asynchroner Aufruf mit exec im Hintergrund
        $scriptPath = __DIR__ . '/../scripts/generate_embedding.php';
        $command = sprintf(
            'php "%s" %d "%s" > /dev/null 2>&1 &',
            $scriptPath,
            $pet_id,
            $image_path
        );
        
        // Windows-kompatibel machen
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = sprintf(
                'start /B php "%s" %d "%s" > NUL 2>&1',
                $scriptPath,
                $pet_id,
                $image_path
            );
        }
        
        exec($command);
        return ['success' => true, 'message' => 'Embedding wird im Hintergrund generiert'];
    }
    
    /**     * Embedding generieren (ruft Python-KI-Service auf)
     */
    private function generateEmbedding($pet_id, $image_path) {
        try {
            // Python-Service aufrufen (FastAPI auf Port 8000)
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'http://localhost:8002/api/generate_embedding',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['image_path' => $image_path, 'pet_id' => $pet_id]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $data = json_decode($response, true);
                if ($data['success']) {
                    // Embedding speichern
                    $embedding_binary = base64_decode($data['embedding']);
                    $stmt = $this->db->prepare(
                        "INSERT INTO pet_images (pet_id, image_path, embedding, is_primary) 
                         VALUES (?, ?, ?, TRUE)"
                    );
                    $stmt->execute([$pet_id, $image_path, $embedding_binary]);
                    
                    return ['success' => true, 'embedding_id' => $this->db->lastInsertId()];
                }
            }
            
            return ['success' => false, 'message' => 'Embedding-Generierung fehlgeschlagen'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Tier als vermisst markieren
     */
    public function markAsMissing($pet_id, $user_id, $location, $date_missing, $description, $latitude = null, $longitude = null, $reward = null) {
        try {
            $stmt = $this->db->prepare("UPDATE pets SET is_missing = TRUE WHERE id = ? AND user_id = ?");
            $stmt->execute([$pet_id, $user_id]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Tier nicht gefunden'];
            }
            
            // Missing Report erstellen
            $sql = "INSERT INTO missing_reports (pet_id, user_id, location_last_seen, latitude, longitude, date_missing, description, reward_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$pet_id, $user_id, $location, $latitude, $longitude, $date_missing, $description, $reward]);
            
            return [
                'success' => true,
                'message' => 'Tier als vermisst markiert',
                'report_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Alle vermissten Tiere abrufen (für öffentliche Seite)
     */
    public function getMissingPets($limit = 50, $offset = 0) {
        try {
            $sql = "SELECT p.*, u.first_name, u.last_name, u.phone,
                           mr.location_last_seen, mr.date_missing, mr.description, mr.reward_amount,
                           mr.created_at AS report_created_at
                    FROM pets p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN missing_reports mr ON mr.id = (
                        SELECT m2.id
                        FROM missing_reports m2
                        WHERE m2.pet_id = p.id AND m2.status = 'active'
                        ORDER BY m2.created_at DESC
                        LIMIT 1
                    )
                    WHERE p.is_missing = TRUE OR mr.id IS NOT NULL
                    ORDER BY COALESCE(mr.created_at, p.updated_at) DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Benutzers Tiere abrufen
     */
    public function getUserPets($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM pets WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Tierdetails abrufen
     */
    public function getPetDetails($pet_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM pets WHERE id = ?");
            $stmt->execute([$pet_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Tier aktualisieren
     */
    public function updatePet($pet_id, $user_id, $data) {
        try {
            $allowed_fields = ['name', 'breed', 'color', 'age', 'weight', 'distinguishing_features', 'microchip_id'];
            $update_fields = [];
            $params = [];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($update_fields)) {
                return ['success' => false, 'message' => 'Keine Felder zum Aktualisieren'];
            }
            
            $params[] = $pet_id;
            $params[] = $user_id;
            
            $sql = "UPDATE pets SET " . implode(', ', $update_fields) . " WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Tier aktualisiert'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Vermissten-Status für gefundenes Tier auflösen
     */
    public function resolveMissing($pet_id, $user_id) {
        try {
            $stmt = $this->db->prepare("UPDATE pets SET is_missing = FALSE WHERE id = ? AND user_id = ?");
            $stmt->execute([$pet_id, $user_id]);
            
            // Missing Report als 'resolved' markieren
            $stmt = $this->db->prepare(
                "UPDATE missing_reports SET status = 'resolved', resolved_at = NOW() 
                 WHERE pet_id = ? AND status = 'active'"
            );
            $stmt->execute([$pet_id]);
            
            return ['success' => true, 'message' => 'Vermisstenfall aufgelöst'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Tier löschen (und alle zugehörigen Daten)
     */
    public function deletePet($pet_id, $user_id) {
        try {
            // Prüfen ob Tier dem User gehört
            $stmt = $this->db->prepare("SELECT profile_image FROM pets WHERE id = ? AND user_id = ?");
            $stmt->execute([$pet_id, $user_id]);
            $pet = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pet) {
                return ['success' => false, 'message' => 'Tier nicht gefunden oder keine Berechtigung'];
            }
            
            // Profilbild löschen (falls nicht default)
            if ($pet['profile_image'] && $pet['profile_image'] !== 'default.jpg') {
                $image_path = $this->upload_dir . $pet['profile_image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            // Pet-Bilder aus pet_images löschen
            $stmt = $this->db->prepare("SELECT image_path FROM pet_images WHERE pet_id = ?");
            $stmt->execute([$pet_id]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($images as $img) {
                if (file_exists($this->upload_dir . $img['image_path'])) {
                    unlink($this->upload_dir . $img['image_path']);
                }
            }
            
            // Datenbank-Einträge löschen (CASCADE sollte das meiste übernehmen)
            $stmt = $this->db->prepare("DELETE FROM pet_images WHERE pet_id = ?");
            $stmt->execute([$pet_id]);
            
            $stmt = $this->db->prepare("DELETE FROM missing_reports WHERE pet_id = ?");
            $stmt->execute([$pet_id]);
            
            $stmt = $this->db->prepare("DELETE FROM pets WHERE id = ? AND user_id = ?");
            $stmt->execute([$pet_id, $user_id]);
            
            return ['success' => true, 'message' => 'Tier erfolgreich gelöscht'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }
}
?>
