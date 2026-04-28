<?php
/**
 * PetConnect - Authentication API
 * Endpoints: register, login, logout, validateSession
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/User.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/', '', $path);

$response = ['success' => false, 'message' => 'Unbekannte API'];

try {
    $user = new User();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($path === 'register' && $method === 'POST') {
        $response = $user->register(
            $input['username'] ?? '',
            $input['email'] ?? '',
            $input['password'] ?? '',
            $input['first_name'] ?? '',
            $input['last_name'] ?? ''
        );
    }
    elseif ($path === 'login' && $method === 'POST') {
        $response = $user->login(
            $input['email'] ?? '',
            $input['password'] ?? ''
        );
    }
    elseif ($path === 'logout' && $method === 'POST') {
        $session_token = $input['session_token'] ?? '';
        if ($user->logout($session_token)) {
            $response = ['success' => true, 'message' => 'Logout erfolgreich'];
        } else {
            $response = ['success' => false, 'message' => 'Logout fehlgeschlagen'];
        }
    }
    elseif ($path === 'validate_session' && $method === 'POST') {
        $session_token = $input['session_token'] ?? '';
        $user_id = $user->validateSession($session_token);
        
        if ($user_id) {
            $user_data = $user->getUserData($user_id);
            $response = [
                'success' => true,
                'message' => 'Session valid',
                'user' => $user_data
            ];
        } else {
            $response = ['success' => false, 'message' => 'Session invalid'];
        }
    }
    elseif (($path === 'check' || $path === 'auth/check') && $method === 'GET') {
        // Extract session token from Authorization header or query param
        $session_token = '';
        $debug_info = ['path' => $path, 'method' => $method];
        
        // Try Apache headers
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $debug_info['apache_headers_available'] = true;
            if (isset($headers['Authorization'])) {
                $session_token = str_replace('Bearer ', '', $headers['Authorization']);
                $debug_info['token_source'] = 'apache_headers';
            }
        }
        // Try getallheaders
        elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $debug_info['getallheaders_available'] = true;
            if (isset($headers['Authorization'])) {
                $session_token = str_replace('Bearer ', '', $headers['Authorization']);
                $debug_info['token_source'] = 'getallheaders';
            }
        }
        // Try $_SERVER
        if (!$session_token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $session_token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
            $debug_info['token_source'] = 'HTTP_AUTHORIZATION';
        }
        // Try query param
        if (!$session_token && isset($_GET['session_token'])) {
            $session_token = $_GET['session_token'];
            $debug_info['token_source'] = 'query_param';
        }
        
        $debug_info['token_found'] = !empty($session_token);
        $debug_info['token_length'] = strlen($session_token);
        
        if ($session_token) {
            $user_id = $user->validateSession($session_token);
            $debug_info['user_id_validated'] = $user_id;
            
            if ($user_id) {
                $user_data = $user->getUserData($user_id);
                $response = [
                    'success' => true,
                    'data' => [
                        'is_logged_in' => true,
                        'user' => $user_data
                    ],
                    'debug' => $debug_info
                ];
            } else {
                $response = [
                    'success' => true,
                    'data' => ['is_logged_in' => false, 'reason' => 'Invalid session token'],
                    'debug' => $debug_info
                ];
            }
        } else {
            $response = [
                'success' => true,
                'data' => ['is_logged_in' => false, 'reason' => 'No session token provided'],
                'debug' => $debug_info
            ];
        }
    }
    elseif ($path === 'profile' && $method === 'GET') {
        $session_token = $_GET['session_token'] ?? '';
        $user_id = $user->validateSession($session_token);
        
        if ($user_id) {
            $user_data = $user->getUserData($user_id);
            $response = [
                'success' => true,
                'user' => $user_data
            ];
        } else {
            $response = ['success' => false, 'message' => 'Unauthorized'];
        }
    }
    elseif ($path === 'profile' && $method === 'POST') {
        $session_token = $input['session_token'] ?? '';
        $user_id = $user->validateSession($session_token);
        
        if ($user_id) {
            $result = $user->updateProfile(
                $user_id,
                $input['first_name'] ?? '',
                $input['last_name'] ?? '',
                $input['phone'] ?? '',
                $input['location'] ?? ''
            );
            $response = [
                'success' => $result,
                'message' => $result ? 'Profil aktualisiert' : 'Update fehlgeschlagen'
            ];
        } else {
            $response = ['success' => false, 'message' => 'Unauthorized'];
        }
    }
    elseif ($path === 'change_password' && $method === 'POST') {
        $session_token = $input['session_token'] ?? '';
        $user_id = $user->validateSession($session_token);
        
        if ($user_id) {
            $response = $user->changePassword(
                $user_id,
                $input['old_password'] ?? '',
                $input['new_password'] ?? ''
            );
        } else {
            $response = ['success' => false, 'message' => 'Unauthorized'];
        }
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server Error: ' . $e->getMessage()];
}

http_response_code($response['success'] ? 200 : 400);
echo json_encode($response);
?>
