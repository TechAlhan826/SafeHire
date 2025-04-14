<?php
/**
 * Application Configuration
 * 
 * Define global configurations and load environment variables
 */

// Enable error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Include database class
require_once __DIR__ . '/Database.php';

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $env_lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if they exist
            if (preg_match('/^"(.+)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.+)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Constants
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost:8000/api');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_jwt_secret_key');
define('JWT_EXPIRY', 3600); // Token expiry in seconds

// API endpoint base
define('API_VERSION', 'v1');

// Payment gateway settings
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: '');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');

// Google Meet API
define('GOOGLE_API_KEY', getenv('GOOGLE_API_KEY') ?: '');

// Zoom API
define('ZOOM_API_KEY', getenv('ZOOM_API_KEY') ?: '');
define('ZOOM_API_SECRET', getenv('ZOOM_API_SECRET') ?: '');

// CORS Settings
define('ALLOWED_ORIGINS', getenv('ALLOWED_ORIGINS') ?: '*');
?>
