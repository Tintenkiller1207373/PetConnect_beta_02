<?php
/**
 * PetConnect - Pet Management API
 * Endpoints: create, upload, missing, list, update
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Pet.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/', '', $path);

$response = ['success' => false, 'message' => 'Unbekannte API'];

try {
    $user = new User();
    $pet = new Pet();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Session Token aus verschiedenen Quellen extrahieren
    $session_token = '';
    
    // 1. Authorization Header
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (isset($headers['Authorization'])) {
        $session_token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $session_token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    
    // 2. Falls nicht im Header, dann aus JSON body
    if (empty($session_token) && isset($input['session_token'])) {
        $session_token = $input['session_token'];
    }
    
    // 3. Falls nicht in JSON, dann aus POST (für FormData)
    if (empty($session_token) && isset($_POST['session_token'])) {
        $session_token = $_POST['session_token'];
    }
    
    // 4. Falls nicht in POST, dann aus GET query params
    if (empty($session_token) && isset($_GET['session_token'])) {
        $session_token = $_GET['session_token'];
    }
    
    $user_id = $user->validateSession($session_token);
    
    if (!$user_id) {
        $response = ['success' => false, 'message' => 'Unauthorized', 'debug' => [
            'token_found' => !empty($session_token),
            'token_length' => strlen($session_token),
            'has_post' => !empty($_POST),
            'post_keys' => array_keys($_POST)
        ]];
    }
    elseif ($path === 'register' && $method === 'POST') {
        // POST /api/pets/register - Tier erstellen mit Bild
        $data = !empty($_POST) ? $_POST : $input;
        
        $response = $pet->createPet(
            $user_id,
            $data['name'] ?? '',
            $data['species'] ?? '',
            $data['breed'] ?? '',
            $data['color'] ?? '',
            $data['age'] ?? 0,
            $data['weight'] ?? 0,
            $data['distinguishing_features'] ?? '',
            $data['microchip_id'] ?? null
        );
        
        // Wenn erfolgreich und Bild vorhanden, Bild hochladen
        if ($response['success'] && isset($_FILES['profile_image'])) {
            $pet_id = $response['data']['pet_id'];
            $upload_response = $pet->uploadProfileImage($pet_id, $_FILES['profile_image']);
            
            if ($upload_response['success']) {
                // Aktualisierte Pet-Daten zurückgeben
                $response['data']['pet']['profile_image'] = $upload_response['image'];
                $response['message'] .= ' und Bild hochgeladen';
            }
        }
    }
    elseif ($path === 'pet/create' && $method === 'POST') {
        // Daten aus $_POST oder JSON lesen
        $data = !empty($_POST) ? $_POST : $input;
        
        $response = $pet->createPet(
            $user_id,
            $data['name'] ?? '',
            $data['species'] ?? '',
            $data['breed'] ?? '',
            $data['color'] ?? '',
            $data['age'] ?? 0,
            $data['weight'] ?? 0,
            $data['distinguishing_features'] ?? '',
            $data['microchip_id'] ?? null
        );
    }
    elseif ($path === 'pet/upload' && $method === 'POST') {
        if (!isset($_FILES['image'])) {
            $response = ['success' => false, 'message' => 'Kein Bild uploadiert'];
        } else {
            $pet_id = $input['pet_id'] ?? $_POST['pet_id'] ?? '';
            $response = $pet->uploadProfileImage($pet_id, $_FILES['image']);
        }
    }
    elseif ($path === 'pet/list' && $method === 'GET') {
        $pets_data = $pet->getUserPets($user_id);
        $response = [
            'success' => true,
            'pets' => $pets_data
        ];
    }
    elseif ($path === 'my-pets' && $method === 'GET') {
        // GET /api/pets/my-pets
        $pets_data = $pet->getUserPets($user_id);
        $response = [
            'success' => true,
            'data' => [
                'pets' => $pets_data,
                'total' => count($pets_data)
            ]
        ];
    }
    elseif (preg_match('/^(\d+)$/', $path, $matches) && $method === 'GET') {
        // GET /api/pets/{pet_id} - Einzelnes Tier abrufen
        $pet_id = $matches[1];
        $pet_data = $pet->getPetDetails($pet_id);
        
        if ($pet_data) {
            // Prüfen ob das Tier dem User gehört
            if ($pet_data['user_id'] == $user_id) {
                $response = [
                    'success' => true,
                    'data' => $pet_data
                ];
            } else {
                $response = ['success' => false, 'message' => 'Nicht autorisiert'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Tier nicht gefunden'];
        }
    }
    elseif ($path === 'pet/missing' && $method === 'POST') {
        $data = !empty($_POST) ? $_POST : $input;
        
        $response = $pet->markAsMissing(
            $data['pet_id'] ?? '',
            $user_id,
            $data['location_last_seen'] ?? $data['location'] ?? '',
            $data['date_missing'] ?? date('Y-m-d'),
            $data['description'] ?? '',
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['reward_amount'] ?? $data['reward'] ?? null
        );
    }
    elseif ($path === 'report-missing' && $method === 'POST') {
        // Alias für pet/missing
        $data = !empty($_POST) ? $_POST : $input;
        
        $response = $pet->markAsMissing(
            $data['pet_id'] ?? '',
            $user_id,
            $data['location_last_seen'] ?? $data['location'] ?? '',
            $data['date_missing'] ?? date('Y-m-d'),
            $data['description'] ?? '',
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['reward_amount'] ?? $data['reward'] ?? null
        );
    }
    elseif ($path === 'pet/update' && $method === 'POST') {
        $data = !empty($_POST) ? $_POST : $input;
        
        $response = $pet->updatePet(
            $data['pet_id'] ?? '',
            $user_id,
            $data['data'] ?? []
        );
    }
    elseif ($path === 'pet/resolve' && $method === 'POST') {
        $data = !empty($_POST) ? $_POST : $input;
        
        $response = $pet->resolveMissing(
            $data['pet_id'] ?? '',
            $user_id
        );
    }
    elseif ($path === 'delete' && $method === 'POST') {
        // DELETE /api/pets/delete
        $data = !empty($_POST) ? $_POST : $input;
        
        $response = $pet->deletePet(
            $data['pet_id'] ?? '',
            $user_id
        );
    }
    elseif ($path === 'pets/missing' && $method === 'GET') {
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        $missing_pets = $pet->getMissingPets($limit, $offset);
        
        $response = [
            'success' => true,
            'pets' => $missing_pets,
            'count' => count($missing_pets),
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server Error: ' . $e->getMessage()];
}

http_response_code($response['success'] ? 200 : 400);
echo json_encode($response);
?>
