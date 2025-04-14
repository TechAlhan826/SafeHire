<?php
/**
 * Custom router for PHP's built-in web server
 * This file is used when running the server with: php -S 0.0.0.0:5000
 */

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if the file exists directly
if (file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // If it's a PHP file, include it; otherwise, let the server handle it
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        include __DIR__ . $uri;
        return true;
    }
    return false; // Let the server handle non-PHP files (images, etc.)
}

// If API path is requested
if (strpos($uri, '/api/') === 0) {
    // Route all API requests to the API index.php
    include __DIR__ . '/api/index.php';
    return true;
}

// Fallback to the welcome page
include __DIR__ . '/api/index.php';
return true;