<?php
/**
 * PetConnect - Finder API
 * Öffentliche Endpoints für Finder Reports (keine Authentication erforderlich)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/Finder.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/', '', $path);

// Debug logging
error_log("Finder API - Method: $method, Path: $path, URI: " . $_SERVER['REQUEST_URI']);

$response = ['success' => false, 'message' => 'Unbekannte API'];

try {
    $finder = new Finder();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if ($path === 'submit' && $method === 'POST') {
        // File und Form Data verarbeiten
        error_log("Finder submit endpoint hit");
        error_log("FILES: " . print_r($_FILES, true));
        error_log("POST: " . print_r($_POST, true));
        
        if (!isset($_FILES['image'])) {
            $response = ['success' => false, 'message' => 'Kein Bild uploadiert'];
        } elseif (!isset($_POST['species']) || empty($_POST['species'])) {
            $response = ['success' => false, 'message' => 'Bitte wähle die Tierart aus'];
        } else {
            $response = $finder->submitFinderReport(
                $_FILES['image'],
                $_POST['species'],
                $_POST['location'] ?? '',
                $_POST['latitude'] ?? null,
                $_POST['longitude'] ?? null,
                $_POST['finder_contact'] ?? '',
                $_POST['finder_phone'] ?? '',
                $_POST['description'] ?? ''
            );
        }
    }
    elseif ($path === 'report' && $method === 'GET') {
        $finder_report_id = $_GET['id'] ?? '';
        $report = $finder->getFinderReport($finder_report_id);
        
        if ($report) {
            $response = [
                'success' => true,
                'report' => $report
            ];
        } else {
            $response = ['success' => false, 'message' => 'Report nicht gefunden'];
        }
    }
    elseif ($path === 'reports' && $method === 'GET') {
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        $reports = $finder->getAllFinderReports($limit, $offset);
        
        $response = [
            'success' => true,
            'reports' => $reports,
            'count' => count($reports)
        ];
    }
    elseif ($path === 'notify' && $method === 'POST') {
        $match_id = $input['match_id'] ?? '';
        $response = $finder->notifyOwner($match_id);
    }
    else {
        // Debug: Zeige welcher Path erwartet wurde
        $response = [
            'success' => false, 
            'message' => 'Unbekannte API', 
            'debug' => [
                'path' => $path,
                'method' => $method,
                'expected' => 'submit, report, reports, or notify'
            ]
        ];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server Error: ' . $e->getMessage()];
}

http_response_code($response['success'] ? 200 : 400);
echo json_encode($response);
?>
