<?php
/**
 * Test Finder Submit direkt
 */

// Simuliere einen POST-Request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/submit';

// Simuliere File Upload
$test_image = __DIR__ . '/uploads/pets/pet_4_1773056783.jpg';
if (!file_exists($test_image)) {
    die("Test-Bild nicht gefunden: $test_image\n");
}

// Kopiere in tmp
$tmp_path = sys_get_temp_dir() . '/test_upload_' . time() . '.jpg';
copy($test_image, $tmp_path);

$_FILES['image'] = [
    'name' => 'test.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $tmp_path,
    'error' => 0,
    'size' => filesize($tmp_path)
];

$_POST = [
    'location' => 'Test Location',
    'latitude' => '48.1351',
    'longitude' => '11.5820',
    'finder_contact' => 'test@test.com',
    'finder_phone' => '0123456789',
    'description' => 'Test Finder Report'
];

echo "=== TESTING FINDER SUBMIT ===\n";
echo "Image exists: " . (file_exists($tmp_path) ? "YES" : "NO") . "\n";
echo "Image size: " . filesize($tmp_path) . " bytes\n\n";

// Lade Finder API
require_once __DIR__ . '/api/finder.php';

// Cleanup
unlink($tmp_path);
?>
