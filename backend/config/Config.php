<?php
/**
 * PetConnect - Application Configuration
 * Werte werden aus Umgebungsvariablen gelesen (z.B. in Coolify gesetzt),
 * die zweiten Argumente sind die lokalen Entwicklungs-Defaults.
 */

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    $lower = strtolower($value);
    if ($lower === 'true') return true;
    if ($lower === 'false') return false;
    return $value;
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int)env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'petconnect'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// APPLICATION SETTINGS
// ============================================================================

define('APP_NAME', 'PetConnect');
define('APP_VERSION', '1.0.0-beta');
define('APP_ENV', env('APP_ENV', 'development')); // development | staging | production
define('APP_DEBUG', (bool)env('APP_DEBUG', true)); // Set to false in production

// ============================================================================
// SECURITY SETTINGS
// ============================================================================

define('SESSION_TIMEOUT', 604800); // 7 days in seconds
define('SESSION_SECURE_COOKIE', (bool)env('SESSION_SECURE_COOKIE', false)); // Set to true in production (HTTPS)
define('BCRYPT_COST', 12); // Password hashing cost

// ============================================================================
// FILE UPLOAD SETTINGS
// ============================================================================

define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_DIR_PETS', __DIR__ . '/../uploads/pets/');
define('UPLOAD_DIR_FINDER', __DIR__ . '/../uploads/finder/');

// ============================================================================
// AI SERVICE CONFIGURATION
// ============================================================================

define('AI_SERVICE_URL', rtrim(env('AI_SERVICE_URL', 'http://localhost:8002'), '/'));
define('AI_SERVICE_TIMEOUT', 30); // seconds
define('AI_SIMILARITY_THRESHOLD', 0.65); // Minimum match score (0.0 - 1.0)
define('AI_EMBEDDING_MODEL', 'resnet50');

// ============================================================================
// EMAIL CONFIGURATION (for notifications)
// ============================================================================

define('MAIL_FROM', 'noreply@petconnect.local');
define('MAIL_FROM_NAME', 'PetConnect Team');

// SMTP Configuration (für Production)
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');

// ============================================================================
// CORS & API SETTINGS
// ============================================================================

define('ALLOWED_ORIGINS', [
    'http://localhost:8001',
    'http://localhost:3000',
    'http://127.0.0.1'
]);

define('API_RATE_LIMIT', 100); // Requests per minute
define('API_PAGINATION_DEFAULT', 50);
define('API_PAGINATION_MAX', 100);

// ============================================================================
// LOGGING
// ============================================================================

define('LOG_ENABLED', true);
define('LOG_FILE', __DIR__ . '/../logs/petconnect.log');
define('LOG_LEVEL', 'info'); // debug | info | warning | error

// ============================================================================
// Feature Flags
// ============================================================================

define('FEATURE_GPU_ACCELERATION', false); // Enable GPU for AI service
define('FEATURE_EMAIL_NOTIFICATIONS', false); // Send email alerts
define('FEATURE_SMS_NOTIFICATIONS', false); // Send SMS alerts
define('FEATURE_GEOLOCATION', true); // Use GPS coordinates
define('FEATURE_SOCIAL_SHARING', false); // Share on social media

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get config value with fallback
 */
function config($key, $default = null) {
    $configs = [
        'db.host' => DB_HOST,
        'db.name' => DB_NAME,
        'db.user' => DB_USER,
        'app.name' => APP_NAME,
        'ai.url' => AI_SERVICE_URL,
        'ai.threshold' => AI_SIMILARITY_THRESHOLD,
    ];
    
    return $configs[$key] ?? $default;
}

?>
