<?php
/**
 * Debug-Skript zum Testen der API-Performance
 */

// Zeitmessung für /auth/check
$start = microtime(true);

$ch = curl_init('http://localhost/api/auth/check');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . ($_GET['token'] ?? 'test_token')
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$auth_time = microtime(true) - $start;

echo "=== /auth/check Test ===\n";
echo "Zeit: " . number_format($auth_time * 1000, 2) . " ms\n";
echo "HTTP Code: $http_code\n";
echo "Response: $response\n\n";

// Zeitmessung für /pets/{id}
if (isset($_GET['pet_id'])) {
    $start = microtime(true);
    
    $ch = curl_init('http://localhost/api/pets/' . $_GET['pet_id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . ($_GET['token'] ?? 'test_token')
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $pets_time = microtime(true) - $start;
    
    echo "=== /pets/{id} Test ===\n";
    echo "Zeit: " . number_format($pets_time * 1000, 2) . " ms\n";
    echo "HTTP Code: $http_code\n";
    echo "Response: $response\n";
}
