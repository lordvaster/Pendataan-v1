<?php
// Author: Zeday @join.co.id
/**
 * Configuration File
 * Load environment variables and define constants
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        die("Error: .env file not found. Please copy .env.example to .env and configure it.");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/../.env');

// Helper function to get environment variable
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    
    // Convert string boolean to actual boolean
    if (strtolower($value) === 'true') return true;
    if (strtolower($value) === 'false') return false;
    if (strtolower($value) === 'null') return null;
    
    return $value;
}

// Define constants
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'db_pajak_kendaraan'));

define('RECAPTCHA_SITE_KEY', env('RECAPTCHA_SITE_KEY', ''));
define('RECAPTCHA_SECRET_KEY', env('RECAPTCHA_SECRET_KEY', ''));

define('APP_URL', env('APP_URL', 'http://localhost/PAJAK'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));

define('SESSION_LIFETIME', env('SESSION_LIFETIME', 3600));
define('SESSION_SECURE', env('SESSION_SECURE', false));
define('SESSION_HTTPONLY', env('SESSION_HTTPONLY', true));

define('MAX_FILE_SIZE', env('MAX_FILE_SIZE', 5242880)); // 5MB
define('ALLOWED_IMAGE_TYPES', explode(',', env('ALLOWED_IMAGE_TYPES', 'image/jpeg,image/png,image/jpg')));
define('ALLOWED_DOC_TYPES', explode(',', env('ALLOWED_DOC_TYPES', 'application/pdf')));

define('CSRF_TOKEN_NAME', env('CSRF_TOKEN_NAME', 'csrf_token'));
define('RATE_LIMIT_LOGIN', env('RATE_LIMIT_LOGIN', 5));
define('RATE_LIMIT_FORM', env('RATE_LIMIT_FORM', 10));
define('RECAPTCHA_ENFORCE', env('RECAPTCHA_ENFORCE', false));

// Upload directories
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('KTP_DIR', UPLOAD_DIR . 'ktp/');
define('STNK_DIR', UPLOAD_DIR . 'stnk/');
define('KENDARAAN_DIR', UPLOAD_DIR . 'kendaraan/');

// Log directory
define('LOG_DIR', __DIR__ . '/../logs/');

// Ensure directories exist
$dirs = [UPLOAD_DIR, KTP_DIR, STNK_DIR, KENDARAAN_DIR, LOG_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
