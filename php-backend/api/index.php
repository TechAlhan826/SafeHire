<?php
/**
 * API Router
 * 
 * Main entry point for the API, handles all requests and routes them to appropriate controllers
 */

// Load configuration first
require_once __DIR__ . '/../config/config.php';

// Set content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGINS);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle root API path
if ($_SERVER['REQUEST_URI'] === '/api' || $_SERVER['REQUEST_URI'] === '/api/') {
    $response = [
        'status' => 'success',
        'message' => 'Welcome to SafeHire API',
        'version' => 'v1.0',
        'endpoints' => [
            'auth' => ['login', 'register', 'profile'],
            'users' => ['profile', 'freelancers'],
            'projects' => ['search', 'recent'],
            'bids' => ['submit', 'accept', 'reject'],
            'contracts' => ['create', 'details'],
            'messages' => ['send', 'conversation'],
            'payments' => ['process', 'history']
        ]
    ];
    echo json_encode($response);
    exit;
}

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Set up authentication middleware
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
$auth = new AuthMiddleware($db);

// Load helper functions
require_once __DIR__ . '/../utils/Helpers.php';

// Parse the URL to determine the endpoint
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/api';
$request_path = parse_url($request_uri, PHP_URL_PATH);

if (strpos($request_path, $base_path) === 0) {
    $request_path = substr($request_path, strlen($base_path));
}

// Remove trailing slash if present
$request_path = rtrim($request_path, '/');

// Split the path into segments
$segments = explode('/', trim($request_path, '/'));
$endpoint = isset($segments[0]) ? $segments[0] : '';
$action = isset($segments[1]) ? $segments[1] : '';
$id = isset($segments[2]) ? $segments[2] : null;
$sub_action = isset($segments[3]) ? $segments[3] : '';

// Default response for invalid routes
$response = [
    'status' => 'error',
    'message' => 'Invalid endpoint',
    'code' => 404
];

// Route the request to the appropriate controller
switch ($endpoint) {
    case 'auth':
        require_once __DIR__ . '/../controllers/AuthController.php';
        $controller = new AuthController($db);
        
        switch ($action) {
            case 'register':
                $response = $controller->register();
                break;
                
            case 'login':
                $response = $controller->login();
                break;
                
            case 'profile':
                $auth_data = $auth->authenticate();
                if (!$auth_data) {
                    $response = $auth->unauthorizedResponse();
                    break;
                }
                $response = $controller->getProfile($auth_data['user_id']);
                break;
                
            case '2fa':
                $auth_data = $auth->authenticate();
                if (!$auth_data) {
                    $response = $auth->unauthorizedResponse();
                    break;
                }
                if ($sub_action === 'disable') {
                    $response = $controller->disable2FA($auth_data['user_id']);
                } else {
                    $response = $controller->setup2FA($auth_data['user_id']);
                }
                break;
                
            case 'reset-password':
                $response = $controller->requestPasswordReset();
                break;
                
            case 'set-password':
                $response = $controller->resetPassword();
                break;
                
            default:
                $response['message'] = 'Invalid auth endpoint';
                break;
        }
        break;
        
    case 'users':
        require_once __DIR__ . '/../controllers/UserController.php';
        $controller = new UserController($db);
        
        // Get authentication data
        $auth_data = $auth->authenticate();
        
        if (!$auth_data) {
            $response = $auth->unauthorizedResponse();
            break;
        }
        
        switch ($action) {
            case '':
                // Get all users (admin only)
                if ($auth->isAdmin($auth_data)) {
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $type = isset($_GET['type']) ? $_GET['type'] : null;
                    $response = $controller->getUsers($type, $page, $limit);
                } else {
                    $response = $auth->forbiddenResponse();
                }
                break;
                
            case 'profile':
                if ($id && $auth->isSameUser($auth_data, $id)) {
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $response = $controller->getUserById($id);
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
                        $response = $controller->updateProfile($id);
                    }
                } else {
                    $response = $auth->forbiddenResponse();
                }
                break;
                
            case 'password':
                if ($auth->isSameUser($auth_data, $id)) {
                    $response = $controller->changePassword($id);
                } else {
                    $response = $auth->forbiddenResponse();
                }
                break;
                
            case 'freelancers':
                $response = $controller->searchFreelancers();
                break;
                
            case 'top':
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $response = $controller->getTopFreelancers($limit);
                break;
                
            case 'portfolio':
                if ($auth->isSameUser($auth_data, $id)) {
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $response = $controller->uploadPortfolio($id);
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $sub_action !== '') {
                        $response = $controller->deletePortfolioItem($id, $sub_action);
                    }
                } else {
                    $response = $auth->forbiddenResponse();
                }
                break;
                
            case 'directory':
                $response = $controller->getDirectory();
                break;
                
            default:
                // Get user by ID if action is a numeric ID
                if (is_numeric($action)) {
                    $response = $controller->getUserById($action);
                } else {
                    $response['message'] = 'Invalid users endpoint';
                }
                break;
        }
        break;
        
    case 'projects':
        require_once __DIR__ . '/../controllers/ProjectController.php';
        $controller = new ProjectController($db);
        
        // Get authentication data
        $auth_data = $auth->authenticate();
        
        if (!$auth_data) {
            $response = $auth->unauthorizedResponse();
            break;
        }
        
        switch ($action) {
            case '':
                // Get all projects or create a new one
                if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                    $status = isset($_GET['status']) ? $_GET['status'] : null;
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $response = $controller->getProjects($status, $page, $limit);
                } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if ($auth->isClient($auth_data)) {
                        $response = $controller->createProject($auth_data['user_id']);
                    } else {
                        $response = $auth->forbiddenResponse('Only clients can create projects');
                    }
                }
                break;
                
            case 'search':
                $response = $controller->searchProjects();
                break;
                
            case 'client':
                if (is_numeric($id)) {
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $response = $controller->getProjectsByClientId($id, $page, $limit);
                } else {
                    $response['message'] = 'Invalid client ID';
                }
                break;
                
            case 'recent':
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
                $response = $controller->getRecentProjects($limit);
                break;
                
            case 'ai-matches':
                if (is_numeric($id)) {
                    $response = $controller->getAIMatches($id);
                } else {
                    $response['message'] = 'Invalid project ID';
                }
                break;
                
            case 'dates':
                if (is_numeric($id) && $auth->isClient($auth_data)) {
                    $response = $controller->setProjectDates($id, $auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only clients can set project dates');
                }
                break;
                
            default:
                // Get, update, or delete a project by ID if action is a numeric ID
                if (is_numeric($action)) {
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $response = $controller->getProjectById($action);
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
                        if ($auth->isClient($auth_data)) {
                            $response = $controller->updateProject($action, $auth_data['user_id']);
                        } else {
                            $response = $auth->forbiddenResponse('Only clients can update projects');
                        }
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                        if ($auth->isClient($auth_data)) {
                            $response = $controller->deleteProject($action, $auth_data['user_id']);
                        } else {
                            $response = $auth->forbiddenResponse('Only clients can delete projects');
                        }
                    }
                } else {
                    $response['message'] = 'Invalid projects endpoint';
                }
                break;
        }
        break;
        
    case 'bids':
        require_once __DIR__ . '/../controllers/BidController.php';
        $controller = new BidController($db);
        
        // Get authentication data
        $auth_data = $auth->authenticate();
        
        if (!$auth_data) {
            $response = $auth->unauthorizedResponse();
            break;
        }
        
        switch ($action) {
            case 'project':
                if (is_numeric($id)) {
                    $status = isset($_GET['status']) ? $_GET['status'] : null;
                    $response = $controller->getProjectBids($id, $status);
                } else {
                    $response['message'] = 'Invalid project ID';
                }
                break;
                
            case 'freelancer':
                if (is_numeric($id)) {
                    $status = isset($_GET['status']) ? $_GET['status'] : null;
                    $response = $controller->getFreelancerBids($id, $status);
                } else {
                    $response['message'] = 'Invalid freelancer ID';
                }
                break;
                
            case 'submit':
                if ($auth->isFreelancer($auth_data)) {
                    $response = $controller->submitBid($auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only freelancers can submit bids');
                }
                break;
                
            case 'accept':
                if (is_numeric($id) && $auth->isClient($auth_data)) {
                    $response = $controller->acceptBid($id, $auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only clients can accept bids');
                }
                break;
                
            case 'reject':
                if (is_numeric($id) && $auth->isClient($auth_data)) {
                    $response = $controller->rejectBid($id, $auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only clients can reject bids');
                }
                break;
                
            case 'accepted':
                if (is_numeric($id)) {
                    $response = $controller->getAcceptedBid($id);
                } else {
                    $response['message'] = 'Invalid project ID';
                }
                break;
                
            default:
                // Update or delete a bid by ID if action is a numeric ID
                if (is_numeric($action) && $auth->isFreelancer($auth_data)) {
                    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
                        $response = $controller->updateBid($action, $auth_data['user_id']);
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                        $response = $controller->deleteBid($action, $auth_data['user_id']);
                    }
                } else {
                    $response['message'] = 'Invalid bids endpoint';
                }
                break;
        }
        break;
        
    case 'payments':
        require_once __DIR__ . '/../controllers/PaymentController.php';
        $controller = new PaymentController($db);
        
        // Webhook callback doesn't require authentication
        if ($action === 'webhook') {
            $response = $controller->processPaymentCallback();
            break;
        }
        
        // Get authentication data
        $auth_data = $auth->authenticate();
        
        if (!$auth_data) {
            $response = $auth->unauthorizedResponse();
            break;
        }
        
        switch ($action) {
            case 'user':
                $role = isset($_GET['role']) ? $_GET['role'] : 'payer';
                $response = $controller->getUserPayments($auth_data['user_id'], $role);
                break;
                
            case 'contract':
                if (is_numeric($id)) {
                    $response = $controller->getContractPayments($id);
                } else {
                    $response['message'] = 'Invalid contract ID';
                }
                break;
                
            case 'advance':
                if (is_numeric($id) && $auth->isClient($auth_data)) {
                    $response = $controller->createAdvancePayment($id, $auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only clients can make advance payments');
                }
                break;
                
            case 'milestone':
                if (is_numeric($id) && $auth->isClient($auth_data)) {
                    $response = $controller->createMilestonePayment($id, $auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only clients can make milestone payments');
                }
                break;
                
            case 'final':
                if (is_numeric($id) && $auth->isClient($auth_data)) {
                    $response = $controller->createFinalPayment($id, $auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only clients can make final payments');
                }
                break;
                
            case 'redeem':
                if (is_numeric($id) && $auth->isFreelancer($auth_data)) {
                    $response = $controller->redeemPayment($id, $auth_data['user_id']);
                } else {
                    $response = $auth->forbiddenResponse('Only freelancers can redeem payments');
                }
                break;
                
            default:
                // Get payment by ID if action is a numeric ID
                if (is_numeric($action)) {
                    $response = $controller->getPaymentById($action);
                } else {
                    $response['message'] = 'Invalid payments endpoint';
                }
                break;
        }
        break;
        
    case 'chat':
        require_once __DIR__ . '/../controllers/ChatController.php';
        $controller = new ChatController($db);
        
        // Get authentication data
        $auth_data = $auth->authenticate();
        
        if (!$auth_data) {
            $response = $auth->unauthorizedResponse();
            break;
        }
        
        switch ($action) {
            case 'history':
                if (is_numeric($id) && is_numeric($sub_action)) {
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                    $response = $controller->getChatHistory($auth_data['user_id'], $id, $sub_action, $page, $limit);
                } else {
                    $response['message'] = 'Invalid user ID or project ID';
                }
                break;
                
            case 'send':
                $response = $controller->sendMessage($auth_data['user_id']);
                break;
                
            case 'unread':
                $response = $controller->getUnreadCount($auth_data['user_id']);
                break;
                
            case 'conversations':
                $response = $controller->getConversations($auth_data['user_id']);
                break;
                
            case 'mark-read':
                if (is_numeric($id) && is_numeric($sub_action)) {
                    $response = $controller->markAsRead($auth_data['user_id'], $id, $sub_action);
                } else {
                    $response['message'] = 'Invalid sender ID or project ID';
                }
                break;
                
            case 'notify':
                if ($auth->isAdmin($auth_data) && is_numeric($id) && is_numeric($sub_action)) {
                    $response = $controller->sendSystemNotification($id, $sub_action);
                } else {
                    $response = $auth->forbiddenResponse('Only admins can send system notifications');
                }
                break;
                
            case 'project':
                if (is_numeric($id)) {
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $response = $controller->getLatestProjectMessages($id, $limit);
                } else {
                    $response['message'] = 'Invalid project ID';
                }
                break;
                
            default:
                $response['message'] = 'Invalid chat endpoint';
                break;
        }
        break;
        
    case 'admin':
        require_once __DIR__ . '/../controllers/AdminController.php';
        $controller = new AdminController($db);
        
        // Get authentication data
        $auth_data = $auth->authenticate();
        
        if (!$auth_data || !$auth->isAdmin($auth_data)) {
            $response = $auth->forbiddenResponse('Admin access required');
            break;
        }
        
        switch ($action) {
            case 'dashboard':
                $response = $controller->getDashboardStats();
                break;
                
            case 'users':
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $response = $controller->getAllUsers($page, $limit);
                break;
                
            case 'verify-user':
                if (is_numeric($id)) {
                    $response = $controller->toggleUserVerification($id);
                } else {
                    $response['message'] = 'Invalid user ID';
                }
                break;
                
            case 'ban-user':
                if (is_numeric($id)) {
                    $response = $controller->banUser($id);
                } else {
                    $response['message'] = 'Invalid user ID';
                }
                break;
                
            case 'disputes':
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $response = $controller->getDisputes($page, $limit);
                break;
                
            case 'reports':
                $period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
                $response = $controller->getFinancialReports($period);
                break;
                
            case 'logs':
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $response = $controller->getAuditLogs($page, $limit);
                break;
                
            default:
                $response['message'] = 'Invalid admin endpoint';
                break;
        }
        break;
}

// Set the HTTP status code
$status_code = isset($response['code']) ? $response['code'] : 200;
http_response_code($status_code);

// Remove code from the response
if (isset($response['code'])) {
    unset($response['code']);
}

// Return the response as JSON
echo json_encode($response);
?>
