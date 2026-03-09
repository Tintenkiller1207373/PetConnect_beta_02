<?php
/**
 * PetConnect - User Model
 * Handling von Benutzerregistrierung, Login, Session Management
 */

require_once __DIR__ . '/../config/Database.php';

class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $first_name;
    private $last_name;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Benutzer registrieren
     * 
     * @param string $username
     * @param string $email
     * @param string $password (wird gehasht)
     * @param string $first_name
     * @param string $last_name
     * @return array ['success' => bool, 'message' => string, 'user_id' => int]
     */
    public function register($username, $email, $password, $first_name = '', $last_name = '') {
        try {
            // Validierung
            if (strlen($username) < 3) {
                return ['success' => false, 'message' => 'Benutzername muss mindestens 3 Zeichen lang sein'];
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Ungültige E-Mail-Adresse'];
            }
            if (strlen($password) < 8) {
                return ['success' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein'];
            }
            
            // Prüfe auf doppelte Benutzernamen/E-Mails
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Benutzername oder E-Mail bereits vergeben'];
            }
            
            // Password hashen (bcrypt)
            $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Benutzer einfügen
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username, $email, $password_hash, $first_name, $last_name]);
            
            $user_id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Benutzerkonto erfolgreich erstellt',
                'user_id' => $user_id
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler bei der Registrierung: ' . $e->getMessage()];
        }
    }
    
    /**
     * Benutzer-Login
     * 
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'session_token' => string]
     */
    public function login($email, $password) {
        try {
            // Benutzer finden
            $stmt = $this->db->prepare("SELECT id, username, password_hash FROM users WHERE email = ? AND is_active = TRUE");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Benutzer nicht gefunden'];
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Password verifizieren
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Falsches Passwort'];
            }
            
            // Session erstellen
            $session_token = bin2hex(random_bytes(32));
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt = $this->db->prepare(
                "INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))"
            );
            $stmt->execute([$user['id'], $session_token, $ip_address, $user_agent]);
            
            return [
                'success' => true,
                'message' => 'Login erfolgreich',
                'user_id' => $user['id'],
                'session_token' => $session_token
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login-Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Session validieren
     * 
     * @param string $session_token
     * @return int|false User ID oder false
     */
    public function validateSession($session_token) {
        try {
            $stmt = $this->db->prepare(
                "SELECT user_id FROM sessions 
                 WHERE session_token = ? AND expires_at > NOW()"
            );
            $stmt->execute([$session_token]);
            
            if ($stmt->rowCount() > 0) {
                $session = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $session['user_id'];
                return $session['user_id'];
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Logout - Session löschen
     */
    public function logout($session_token) {
        try {
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_token = ?");
            return $stmt->execute([$session_token]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Benutzerdaten abrufen
     */
    public function getUserData($user_id) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, username, email, first_name, last_name, phone, location, profile_picture, created_at 
                 FROM users WHERE id = ?"
            );
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Benutzerdaten aktualisieren
     */
    public function updateProfile($user_id, $first_name, $last_name, $phone, $location) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET first_name = ?, last_name = ?, phone = ?, location = ? 
                 WHERE id = ?"
            );
            return $stmt->execute([$first_name, $last_name, $phone, $location, $user_id]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Passwort ändern
     */
    public function changePassword($user_id, $old_password, $new_password) {
        try {
            // Altes Passwort verifizieren
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($old_password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Altes Passwort ist falsch'];
            }
            
            // Neues Passwort hashen und speichern
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $user_id]);
            
            return ['success' => true, 'message' => 'Passwort erfolgreich geändert'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    public function getId() {
        return $this->id;
    }
}
?>
