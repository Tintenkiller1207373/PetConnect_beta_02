<?php
/**
 * PetConnect - Finder Model
 * Handling der Finder-Reports und KI-basierter Zuordnung
 */

require_once __DIR__ . '/../config/Database.php';

class Finder {
    private $db;
    private $upload_dir = __DIR__ . '/../uploads/finder/';
    private $ai_service_url = 'http://localhost:8002/api';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Finder Report mit Bild erstellen und KI-Analyse starten
     */
    public function submitFinderReport($file, $species, $location, $latitude = null, $longitude = null, $finder_contact = null, $finder_phone = null, $description = null) {
        try {
            // Bild validieren
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                return ['success' => false, 'message' => 'Nur JPG, PNG und GIF erlaubt'];
            }
            
            if ($file['size'] > 10 * 1024 * 1024) {
                return ['success' => false, 'message' => 'Datei zu groß (max. 10MB)'];
            }
            
            // Speichern
            $filename = 'finder_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = $this->upload_dir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                return ['success' => false, 'message' => 'Upload fehlgeschlagen'];
            }
            
            // Embedding generieren
            $embedding_result = $this->generateFinderEmbedding($upload_path);
            if (!$embedding_result['success']) {
                return $embedding_result;
            }
            
            // Finder Report in DB speichern
            $sql = "INSERT INTO finder_reports (image_path, species, embedding, location_found, latitude, longitude, finder_contact, finder_phone, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $embedding_binary = base64_decode($embedding_result['embedding']);
            $stmt->execute([
                $upload_path,
                $species,
                $embedding_binary,
                $location,
                $latitude,
                $longitude,
                $finder_contact,
                $finder_phone,
                $description
            ]);
            
            $finder_report_id = $this->db->lastInsertId();
            
            // KI-Abgleich starten (Binary Embedding übergeben!) - nur gleiche Tierart
            $matches = $this->findMatches($finder_report_id, $embedding_binary, $species);
            
            return [
                'success' => true,
                'message' => 'Bericht erfolgreich eingereicht',
                'finder_report_id' => $finder_report_id,
                'matches' => $matches
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Embedding für Finder-Foto generieren
     */
    private function generateFinderEmbedding($image_path) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->ai_service_url . '/generate_embedding',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['image_path' => $image_path]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $data = json_decode($response, true);
                if ($data['success']) {
                    return ['success' => true, 'embedding' => $data['embedding']];
                }
            }
            
            return ['success' => false, 'message' => 'Embedding-Generierung fehlgeschlagen'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }

    /**
     * Absoluten Dateipfad fuer ein vermisstes Tierbild ermitteln.
     */
    private function resolveMissingPetImagePath($profile_image, $stored_image_path = null) {
        $candidates = [];

        if (!empty($stored_image_path)) {
            $candidates[] = $stored_image_path;
            $candidates[] = __DIR__ . '/../uploads/pets/' . basename($stored_image_path);
        }

        if (!empty($profile_image)) {
            $candidates[] = $profile_image;
            $candidates[] = __DIR__ . '/../uploads/pets/' . basename($profile_image);
        }

        foreach ($candidates as $candidate) {
            if (!empty($candidate) && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Fehlendes Embedding fuer vermisstes Tier aus vorhandenem Bild erzeugen und persistieren.
     */
    private function ensureMissingPetEmbedding($pet_id, $profile_image, $stored_image_path = null, $existing_image_id = null) {
        $resolved_path = $this->resolveMissingPetImagePath($profile_image, $stored_image_path);
        if (!$resolved_path) {
            return null;
        }

        $embedding_result = $this->generateFinderEmbedding($resolved_path);
        if (empty($embedding_result['success']) || empty($embedding_result['embedding'])) {
            return null;
        }

        $embedding_binary = base64_decode($embedding_result['embedding'], true);
        if ($embedding_binary === false) {
            return null;
        }

        if (!empty($existing_image_id)) {
            $stmt = $this->db->prepare(
                "UPDATE pet_images
                 SET embedding = ?, image_path = ?, embedding_model = 'resnet50', is_primary = TRUE
                 WHERE id = ?"
            );
            $stmt->execute([$embedding_binary, $resolved_path, $existing_image_id]);
        } else {
            $stmt = $this->db->prepare("UPDATE pet_images SET is_primary = FALSE WHERE pet_id = ?");
            $stmt->execute([$pet_id]);

            $stmt = $this->db->prepare(
                "INSERT INTO pet_images (pet_id, image_path, embedding, embedding_model, is_primary)
                 VALUES (?, ?, ?, 'resnet50', TRUE)"
            );
            $stmt->execute([$pet_id, $resolved_path, $embedding_binary]);
        }

        return $embedding_binary;
    }
    
    /**
     * Ähnliche vermisste Tiere finden (Cosine Similarity)
     * Nur gleiche Tierart wird verglichen!
    * Threshold: 0.45 = 45% Ähnlichkeit (bessere Recall bei variierenden Handyfotos)
     */
    private function findMatches($finder_report_id, $finder_embedding, $species, $threshold = 0.45) {
        try {
            // DEBUG: Log Start
            error_log("=== FINDER MATCHING START ===");
            error_log("Finder Report ID: " . $finder_report_id);
            error_log("Species Filter: " . $species);
            error_log("Threshold: " . $threshold);
            
            // Alle vermissten Tiere holen; Embeddings koennen fehlen und werden dann on-demand erzeugt.
            $stmt = $this->db->prepare(
                "SELECT pi.id as image_id, pi.embedding, pi.image_path, p.id as pet_id, p.name, p.species, p.breed, p.profile_image,
                        u.id as user_id, u.email, u.phone, mr.location_last_seen, mr.latitude, mr.longitude
                 FROM pets p
                 JOIN users u ON p.user_id = u.id
                 JOIN missing_reports mr ON p.id = mr.pet_id
                 LEFT JOIN pet_images pi ON pi.pet_id = p.id AND pi.is_primary = TRUE
                 WHERE p.is_missing = TRUE AND mr.status = 'active' AND p.species = ?"
            );
            $stmt->execute([$species]);
            $missing_pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // DEBUG: Log Anzahl vermisster Tiere
            error_log("Gefundene vermisste Tiere (species=$species): " . count($missing_pets));
            
            if (count($missing_pets) === 0) {
                error_log("ACHTUNG: Keine vermissten Tiere der Art '$species' gefunden!");
                error_log("Prüfe ob: is_missing=TRUE, mr.status='active', pi.is_primary=TRUE, species=$species");
            }
            
            $matches = [];
            
            // Python-Service aufrufen für Cosine Similarity
            foreach ($missing_pets as $pet) {
                error_log("Vergleiche mit Pet ID: " . $pet['pet_id'] . " (" . $pet['name'] . ")");
                error_log("Hat Embedding: " . (isset($pet['embedding']) && !empty($pet['embedding']) ? "JA" : "NEIN"));
                
                $pet_embedding = $pet['embedding'];
                if (empty($pet_embedding)) {
                    error_log("Pet " . $pet['pet_id'] . " hat kein Embedding - versuche On-Demand-Generierung.");
                    $pet_embedding = $this->ensureMissingPetEmbedding(
                        $pet['pet_id'],
                        $pet['profile_image'] ?? null,
                        $pet['image_path'] ?? null,
                        $pet['image_id'] ?? null
                    );
                }

                if (empty($pet_embedding)) {
                    error_log("SKIP: Pet " . $pet['pet_id'] . " hat weiterhin kein nutzbares Embedding!");
                    continue;
                }
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $this->ai_service_url . '/compare_embeddings',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'embedding1' => base64_encode($finder_embedding),
                        'embedding2' => base64_encode($pet_embedding)
                    ]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 10
                ]);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                error_log("AI Service Response Code: " . $http_code);
                
                if ($http_code === 200) {
                    $data = json_decode($response, true);
                    error_log("Similarity Score: " . ($data['similarity'] ?? 'N/A'));
                    
                    if ($data['success'] && $data['similarity'] >= $threshold) {
                        error_log("✓ MATCH! Pet " . $pet['pet_id'] . " - Similarity: " . $data['similarity']);
                        
                        // Bildpfad korrigieren: uploads/pets/filename
                        $image_path = $pet['profile_image'];
                        if ($image_path && !str_starts_with($image_path, 'uploads/')) {
                            $image_path = 'uploads/pets/' . basename($image_path);
                        }
                        
                        // Adresse formatieren (falls nur Koordinaten)
                        $location = $pet['location_last_seen'];
                        if (preg_match('/^Koordinaten:/', $location) && isset($pet['latitude']) && isset($pet['longitude'])) {
                            // Formatiere mit Koordinaten-Link
                            $lat = $pet['latitude'];
                            $lng = $pet['longitude'];
                            $location = "Koordinaten: $lat, $lng (GPS)";
                        }
                        
                        $matches[] = [
                            'pet_id' => $pet['pet_id'],
                            'name' => $pet['name'],
                            'species' => $pet['species'],
                            'breed' => $pet['breed'],
                            'similarity' => round($data['similarity'], 4),
                            'owner_email' => $pet['email'],
                            'owner_phone' => $pet['phone'],
                            'location_last_seen' => $location,
                            'image' => $image_path,
                            'latitude' => $pet['latitude'] ?? null,
                            'longitude' => $pet['longitude'] ?? null
                        ];
                        
                        // In Match Results Tabelle speichern
                        $stmt = $this->db->prepare(
                            "INSERT INTO match_results (finder_report_id, pet_id, similarity_score) 
                             VALUES (?, ?, ?)"
                        );
                        $stmt->execute([$finder_report_id, $pet['pet_id'], $data['similarity']]);
                    } else {
                        error_log("✗ Zu niedrig: Pet " . $pet['pet_id'] . " - Score: " . ($data['similarity'] ?? 'N/A') . " < " . $threshold);
                    }
                } else {
                    error_log("✗ AI Service Error für Pet " . $pet['pet_id'] . ": HTTP " . $http_code);
                }
            }
            
            // Nach Ähnlichkeit sortieren
            usort($matches, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            error_log("=== GESAMT MATCHES: " . count($matches) . " ===");
            
            return array_slice($matches, 0, 10); // Top 10
        } catch (Exception $e) {
            error_log("ERROR in findMatches: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Finder Report Status abrufen
     */
    public function getFinderReport($finder_report_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM finder_reports WHERE id = ?");
            $stmt->execute([$finder_report_id]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($report) {
                // Matches abrufen
                $stmt = $this->db->prepare(
                    "SELECT mr.pet_id, mr.similarity_score, p.name, p.species, p.breed, p.profile_image,
                            u.email, u.phone, mr.user_notified
                     FROM match_results mr
                     JOIN pets p ON mr.pet_id = p.id
                     JOIN users u ON p.user_id = u.id
                     WHERE mr.finder_report_id = ?
                     ORDER BY mr.similarity_score DESC"
                );
                $stmt->execute([$finder_report_id]);
                $report['matches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $report;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Alle Finder Reports abrufen (Admin)
     */
    public function getAllFinderReports($limit = 50, $offset = 0) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, location_found, latitude, longitude, finder_contact, finder_phone, 
                        created_at, processed 
                 FROM finder_reports 
                 ORDER BY created_at DESC 
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Besitzer benachrichtigen (wenn Match gefunden)
     */
    public function notifyOwner($match_id) {
        try {
            $stmt = $this->db->prepare(
                "SELECT mr.finder_report_id, mr.pet_id, mr.similarity_score, p.name, 
                        u.email, u.first_name, fr.location_found, fr.finder_phone
                 FROM match_results mr
                 JOIN pets p ON mr.pet_id = p.id
                 JOIN users u ON p.user_id = u.id
                 JOIN finder_reports fr ON mr.finder_report_id = fr.id
                 WHERE mr.id = ?"
            );
            $stmt->execute([$match_id]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$match) {
                return ['success' => false, 'message' => 'Match nicht gefunden'];
            }
            
            // Email vorbereiten (Sie müssen PHPMailer oder ähnlich verwenden)
            $to = $match['email'];
            $subject = "Möglicher Fund für Ihr Haustier: " . $match['name'];
            $message = "Hallo " . $match['first_name'] . ",\n\n";
            $message .= "Es wurde ein möglicher Fund für Ihr vermisstes Haustier '" . $match['name'] . "' gemeldet!\n";
            $message .= "Ähnlichkeitsgrad: " . round($match['similarity_score'] * 100, 1) . "%\n";
            $message .= "Fund-Standort: " . $match['location_found'] . "\n";
            $message .= "Kontakt Finder: " . $match['finder_phone'] . "\n\n";
            $message .= "Bitte melden Sie sich auf der Platform an, um mehr Details zu sehen.\n";
            
            // E-Mail senden (hier idealer Weise mit Queue-System)
            mail($to, $subject, $message, "From: noreply@petconnect.local");
            
            // Als notifiziert markieren
            $stmt = $this->db->prepare(
                "UPDATE match_results SET user_notified = TRUE, notification_sent_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$match_id]);
            
            return ['success' => true, 'message' => 'Besitzer benachrichtigt'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
}
?>
