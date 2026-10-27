<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Entferne /frontend/ Prefix falls vorhanden
if (strpos($uri, '/frontend/') === 0) {
    $uri = substr($uri, 9); // Entferne "/frontend"
}

if ($uri === '/' || $uri === '') {
    header('Location: /pages/index.html');
    exit;
}

if (strpos($uri, '/api/') === 0) {
    $parts = explode('/', trim($uri, '/'));
    $resource = $parts[1] ?? '';
    $rest = array_slice($parts, 2);
    $restPath = implode('/', $rest);

    if ($resource === 'auth') {
        $_SERVER['REQUEST_URI'] = '/api/' . $restPath;
        require __DIR__ . '/backend/api/auth.php';
        return true;
    }

    if ($resource === 'pets') {
        $_SERVER['REQUEST_URI'] = '/api/' . $restPath;
        require __DIR__ . '/backend/api/pets.php';
        return true;
    }

    if ($resource === 'finder') {
        $_SERVER['REQUEST_URI'] = '/api/' . $restPath;
        require __DIR__ . '/backend/api/finder.php';
        return true;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'API not found',
        'debug' => [
            'uri' => $uri,
            'parts' => $parts,
            'resource' => $resource,
            'restPath' => $restPath,
            'original_uri' => $_SERVER['REQUEST_URI']
        ]
    ]);
    return true;
}

// Upload-Dateien ausliefern (aus backend/uploads)
if (strpos($uri, '/uploads/') === 0) {
    $uploadsRoot = realpath(__DIR__ . '/backend/uploads');
    $path = realpath(__DIR__ . '/backend' . rawurldecode($uri));
    // Nur Dateien innerhalb von backend/uploads ausliefern (kein ../config/Config.php)
    if ($path !== false && $uploadsRoot !== false
        && strpos($path, $uploadsRoot . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
        // Dateityp ermitteln und setzen
        $mime_types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp'
        ];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $mime_types[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        readfile($path);
        return true;
    }
    
    // Datei nicht gefunden
    http_response_code(404);
    echo "404 Not Found - Upload File: $uri";
    return true;
}

// Frontend-Dateien ausliefern
$path = __DIR__ . '/frontend' . $uri;
if (file_exists($path) && !is_dir($path)) {
    return false;
}

http_response_code(404);
echo "404 Not Found - File: $uri";
return true;
