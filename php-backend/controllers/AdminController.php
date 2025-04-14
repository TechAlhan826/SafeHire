<?php
/**
 * Admin Controller
 * 
 * Handles admin-specific operations, user management, and system analytics
 */
class AdminController {
    // Database connection and models
    private $conn;
    private $user;
    private $project;
    private $payment;
    private $contract;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize models
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/Project.php';
        require_once __DIR__ . '/../models/Payment.php';
        require_once __DIR__ . '/../models/Contract.php';
        
        $this->user = new User($db);
        $this->project = new Project($db);
        $this->payment = new Payment($db);
        $this->contract = new Contract($db);
    }
    
    /**
     * Get system dashboard statistics
     * 
     * @return array Response data
     */
    public function getDashboardStats() {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user counts
        $query = "SELECT COUNT(*) as total, user_type FROM users GROUP BY user_type";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $user_stats = $stmt->fetchAll();
        
        $user_counts = [
            'total' => 0,
            'freelancers' => 0,
            'clients' => 0,
            'admins' => 0
        ];
        
        foreach ($user_stats as $stat) {
            $user_counts['total'] += $stat['total'];
            switch ($stat['user_type']) {
                case 'freelancer':
                    $user_counts['freelancers'] = $stat['total'];
                    break;
                case 'client':
                    $user_counts['clients'] = $stat['total'];
                    break;
                case 'admin':
                    $user_counts['admins'] = $stat['total'];
                    break;
            }
        }
        
        // Get project counts
        $query = "SELECT COUNT(*) as total, status FROM projects GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $project_stats = $stmt->fetchAll();
        
        $project_counts = [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0
        ];
        
        foreach ($project_stats as $stat) {
            $project_counts['total'] += $stat['total'];
            switch ($stat['status']) {
                case 'open':
                    $project_counts['open'] = $stat['total'];
                    break;
                case 'in_progress':
                    $project_counts['in_progress'] = $stat['total'];
                    break;
                case 'completed':
                    $project_counts['completed'] = $stat['total'];
                    break;
                case 'cancelled':
                    $project_counts['cancelled'] = $stat['total'];
                    break;
            }
        }
        
        // Get payment statistics
        $query = "SELECT SUM(amount) as total, COUNT(*) as count, status FROM payments GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $payment_stats = $stmt->fetchAll();
        
        $payment_counts = [
            'total_amount' => 0,
            'total_count' => 0,
            'completed_amount' => 0,
            'pending_amount' => 0
        ];
        
        foreach ($payment_stats as $stat) {
            $payment_counts['total_count'] += $stat['count'];
            switch ($stat['status']) {
                case 'completed':
                    $payment_counts['completed_amount'] = $stat['total'];
                    $payment_counts['total_amount'] += $stat['total'];
                    break;
                case 'pending':
                    $payment_counts['pending_amount'] = $stat['total'];
                    break;
            }
        }
        
        // Get recent users
        $query = "SELECT id, username, email, user_type, created_at FROM users ORDER BY created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $recent_users = $stmt->fetchAll();
        
        // Get recent projects
        $query = "SELECT p.id, p.title, p.budget, p.status, p.created_at, u.username as client_name 
                  FROM projects p 
                  JOIN users u ON p.client_id = u.id 
                  ORDER BY p.created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $recent_projects = $stmt->fetchAll();
        
        // Get recent payments
        $query = "SELECT p.id, p.amount, p.status, p.created_at, p.payment_type, 
                  u1.username as payer_name, u2.username as payee_name 
                  FROM payments p
                  JOIN users u1 ON p.payer_id = u1.id
                  JOIN users u2 ON p.payee_id = u2.id
                  ORDER BY p.created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $recent_payments = $stmt->fetchAll();
        
        return [
            'status' => 'success',
            'data' => [
                'users' => $user_counts,
                'projects' => $project_counts,
                'payments' => $payment_counts,
                'recent' => [
                    'users' => $recent_users,
                    'projects' => $recent_projects,
                    'payments' => $recent_payments
                ]
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get all users with admin filtering
     * 
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Response data
     */
    public function getAllUsers($page = 1, $limit = 20) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Apply filters if provided
        $filters = [];
        $params = [];
        
        if (isset($_GET['user_type']) && !empty($_GET['user_type'])) {
            $filters[] = "user_type = :user_type";
            $params[':user_type'] = $_GET['user_type'];
        }
        
        if (isset($_GET['is_verified']) && ($_GET['is_verified'] === '0' || $_GET['is_verified'] === '1')) {
            $filters[] = "is_verified = :is_verified";
            $params[':is_verified'] = $_GET['is_verified'];
        }
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters[] = "(username LIKE :search OR email LIKE :search)";
            $params[':search'] = '%' . $_GET['search'] . '%';
        }
        
        // Build the query
        $query = "SELECT id, username, email, user_type, profile_image, skills, 
                  bio, hourly_rate, location, rating, is_verified, created_at, 
                  last_login, active_status, two_factor_enabled 
                  FROM users";
        
        if (!empty($filters)) {
            $query .= " WHERE " . implode(" AND ", $filters);
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as total FROM users";
        
        if (!empty($filters)) {
            $count_query .= " WHERE " . implode(" AND ", $filters);
        }
        
        $count_stmt = $this->conn->prepare($count_query);
        
        // Bind parameters for count query
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        
        $count_stmt->execute();
        $count_result = $count_stmt->fetch();
        $total = $count_result['total'];
        
        return [
            'status' => 'success',
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ],
            'code' => 200
        ];
    }
    
    /**
     * Update user verification status
     * 
     * @param int $id User ID
     * @return array Response data
     */
    public function toggleUserVerification($id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user by ID
        if (!$this->user->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'User not found',
                'code' => 404
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate verification status
        if (!isset($data['is_verified']) || ($data['is_verified'] !== 0 && $data['is_verified'] !== 1)) {
            return [
                'status' => 'error',
                'message' => 'Invalid verification status',
                'code' => 400
            ];
        }
        
        // Update verification status
        $query = "UPDATE users SET is_verified = :is_verified WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":is_verified", $data['is_verified'], PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return [
                'status' => 'success',
                'message' => 'User verification status updated successfully',
                'data' => [
                    'id' => $id,
                    'is_verified' => $data['is_verified']
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to update user verification status',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get all disputes
     * 
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Response data
     */
    public function getDisputes($page = 1, $limit = 20) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // For demo purposes, we'll create a mock disputes table query
        // In a real app, this would be a proper table
        
        $query = "SELECT d.id, d.contract_id, d.project_id, d.client_id, d.freelancer_id, 
                  d.reason, d.status, d.created_at, d.updated_at, 
                  p.title as project_title, 
                  u1.username as client_name, 
                  u2.username as freelancer_name 
                  FROM disputes d
                  JOIN projects p ON d.project_id = p.id
                  JOIN users u1 ON d.client_id = u1.id
                  JOIN users u2 ON d.freelancer_id = u2.id
                  ORDER BY d.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        // Since we don't have a disputes table in this demo, we'll return mock data
        return [
            'status' => 'success',
            'message' => 'This is a demo endpoint. In a real application, this would retrieve disputes from the database.',
            'data' => [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'total_pages' => 0
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get system financial reports
     * 
     * @param string $period Report period (daily, weekly, monthly, yearly)
     * @return array Response data
     */
    public function getFinancialReports($period = 'monthly') {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Validate period
        $valid_periods = ['daily', 'weekly', 'monthly', 'yearly'];
        if (!in_array($period, $valid_periods)) {
            return [
                'status' => 'error',
                'message' => 'Invalid period. Valid options are: ' . implode(', ', $valid_periods),
                'code' => 400
            ];
        }
        
        // Define date format and grouping based on period
        switch ($period) {
            case 'daily':
                $date_format = '%Y-%m-%d';
                $group_by = "DATE(created_at)";
                $days_back = 30; // Last 30 days
                break;
            case 'weekly':
                $date_format = '%Y-%U';
                $group_by = "YEARWEEK(created_at)";
                $days_back = 90; // Last ~3 months
                break;
            case 'monthly':
                $date_format = '%Y-%m';
                $group_by = "DATE_FORMAT(created_at, '%Y-%m')";
                $days_back = 365; // Last year
                break;
            case 'yearly':
                $date_format = '%Y';
                $group_by = "YEAR(created_at)";
                $days_back = 1825; // Last 5 years
                break;
        }
        
        // Calculate start date
        $start_date = date('Y-m-d', strtotime("-$days_back days"));
        
        // Get payment data
        $query = "SELECT 
                  DATE_FORMAT(created_at, '$date_format') as period,
                  SUM(amount) as total,
                  COUNT(*) as count,
                  payment_type,
                  status
                  FROM payments
                  WHERE created_at >= :start_date
                  GROUP BY period, payment_type, status
                  ORDER BY period";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->execute();
        $payment_data = $stmt->fetchAll();
        
        // Organize data by period and type
        $reports = [];
        foreach ($payment_data as $row) {
            if (!isset($reports[$row['period']])) {
                $reports[$row['period']] = [
                    'period' => $row['period'],
                    'total_amount' => 0,
                    'total_count' => 0,
                    'advance_payments' => 0,
                    'milestone_payments' => 0,
                    'final_payments' => 0,
                    'completed_payments' => 0,
                    'pending_payments' => 0
                ];
            }
            
            // Add to totals
            if ($row['status'] === 'completed') {
                $reports[$row['period']]['total_amount'] += $row['total'];
                $reports[$row['period']]['completed_payments'] += $row['total'];
            } else if ($row['status'] === 'pending') {
                $reports[$row['period']]['pending_payments'] += $row['total'];
            }
            
            $reports[$row['period']]['total_count'] += $row['count'];
            
            // Add by payment type
            switch ($row['payment_type']) {
                case 'advance':
                    $reports[$row['period']]['advance_payments'] += $row['total'];
                    break;
                case 'milestone':
                    $reports[$row['period']]['milestone_payments'] += $row['total'];
                    break;
                case 'final':
                    $reports[$row['period']]['final_payments'] += $row['total'];
                    break;
            }
        }
        
        // Convert associative array to indexed array
        $result = array_values($reports);
        
        return [
            'status' => 'success',
            'data' => $result,
            'code' => 200
        ];
    }
    
    /**
     * Ban a user
     * 
     * @param int $id User ID
     * @return array Response data
     */
    public function banUser($id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user by ID
        if (!$this->user->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'User not found',
                'code' => 404
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate ban status
        if (!isset($data['active_status']) || ($data['active_status'] !== 0 && $data['active_status'] !== 1)) {
            return [
                'status' => 'error',
                'message' => 'Invalid active status. Use 0 to ban, 1 to unban.',
                'code' => 400
            ];
        }
        
        // Update active status
        $query = "UPDATE users SET active_status = :active_status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":active_status", $data['active_status'], PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return [
                'status' => 'success',
                'message' => $data['active_status'] ? 'User has been unbanned' : 'User has been banned',
                'data' => [
                    'id' => $id,
                    'active_status' => $data['active_status']
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to update user status',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get admin audit logs
     * 
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Response data
     */
    public function getAuditLogs($page = 1, $limit = 50) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // For demo purposes, we'll create a mock audit logs table query
        // In a real app, this would be a proper table
        
        $query = "SELECT al.id, al.user_id, al.action, al.details, al.ip_address, al.created_at, 
                  u.username as user_name
                  FROM audit_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  ORDER BY al.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        // Since we don't have an audit_logs table in this demo, we'll return mock data
        return [
            'status' => 'success',
            'message' => 'This is a demo endpoint. In a real application, this would retrieve audit logs from the database.',
            'data' => [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'total_pages' => 0
            ],
            'code' => 200
        ];
    }
}
?>
