<?php
/**
 * SafeHire Backend - Main Entry Point
 * 
 * This file serves as the main entry point for the SafeHire backend application.
 * It handles API requests, sets up the environment, and initializes the database.
 */

// CORS headers for cross-domain requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include configuration files
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';

// Include utility files
require_once __DIR__ . '/utils/Helpers.php';
require_once __DIR__ . '/utils/JWT.php';

// Create database connection
$databaseManager = new DatabaseManager();
$db = $databaseManager->getConnection();

// Check if database is properly set up and create tables if needed
$databaseManager->createTablesIfNotExist();

// Handle the welcome page
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Welcome to SafeHire Backend API',
    'version' => API_VERSION,
    'documentation' => 'Visit /api for API documentation',
    'modules' => [
        'Auth' => [
            'Login', 'Register', 'Password Reset', '2FA'
        ],
        'Projects' => [
            'Create', 'Browse', 'Search', 'AI Matching'
        ],
        'Bids' => [
            'Submit', 'Accept', 'Reject'
        ],
        'Contracts' => [
            'Milestones', 'Reviews', 'Disputes'
        ],
        'Payments' => [
            'Escrow', 'Release', 'History'
        ],
        'Chat' => [
            'Messages', 'Notifications', 'File Sharing'
        ],
        'Users' => [
            'Profile', 'Skills', 'Portfolio', 'Ratings'
        ]
    ]
]);
