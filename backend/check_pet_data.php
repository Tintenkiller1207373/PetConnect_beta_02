<?php
require_once __DIR__ . '/config/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== PET DATA CHECK ===\n\n";

$stmt = $db->query('
    SELECT p.id, p.name, p.profile_image, pi.image_path, mr.location_last_seen 
    FROM pets p 
    JOIN pet_images pi ON p.id = pi.pet_id 
    JOIN missing_reports mr ON p.id = mr.pet_id 
    WHERE p.id = 4 AND pi.is_primary = TRUE
    LIMIT 1
');

$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Pet ID: " . $result['id'] . "\n";
echo "Name: " . $result['name'] . "\n";
echo "profile_image (pets table): " . ($result['profile_image'] ?? 'NULL') . "\n";
echo "image_path (pet_images table): " . ($result['image_path'] ?? 'NULL') . "\n";
echo "location_last_seen: " . ($result['location_last_seen'] ?? 'NULL') . "\n";

echo "\n=== FILE CHECK ===\n";
if ($result['profile_image']) {
    echo "profile_image exists: " . (file_exists($result['profile_image']) ? "YES" : "NO") . "\n";
}
if ($result['image_path']) {
    echo "image_path exists: " . (file_exists($result['image_path']) ? "YES" : "NO") . "\n";
}
?>
