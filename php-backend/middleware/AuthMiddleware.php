<?php
/**
 * Authentication Middleware
 * 
 * Handles authentication verification for API requests
 */
class AuthMiddleware {
    // Properties
    private $jwt;
    private $user;
    private $db;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
        
        // Initialize JWT utility
        require_once __DIR__ . '/../utils/JWT.php';
        $this->jwt = new JWT();
        
        // Initialize user model
        require_once __DIR__ . '/../models/User.php';
        $this->user = new User($db);
    }
    
    /**
     * Authenticate the request
     * 
     * @return array|null Authentication data if valid, null if invalid
     */
    public function authenticate() {
        // Get Authorization header
        $headers = getallheaders();
        $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // Check if header exists and is Bearer token
        if (empty($auth_header) || strpos($auth_header, 'Bearer ') !== 0) {
            return null;
        }
        
        // Extract token
        $token = trim(substr($auth_header, 7));
        
        // Verify token
        try {
            $decoded = $this->jwt->validate($token);
            
            // Check if user exists
            if (!$this->user->getById($decoded->id)) {
                return null;
            }
            
            // Check if user is active
            if ($this->user->active_status != 1) {
                return null;
            }
            
            // Return authentication data
            return [
                'user_id' => $decoded->id,
                'email' => $decoded->email,
                'user_type' => $decoded->user_type
            ];
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Check if the authenticated user has admin role
     * 
     * @param array $auth Authentication data
     * @return bool True if user is admin, false otherwise
     */
    public function isAdmin($auth) {
        if (!$auth || !isset($auth['user_type'])) {
            return false;
        }
        
        return $auth['user_type'] === 'admin';
    }
    
    /**
     * Check if the authenticated user has client role
     * 
     * @param array $auth Authentication data
     * @return bool True if user is client, false otherwise
     */
    public function isClient($auth) {
        if (!$auth || !isset($auth['user_type'])) {
            return false;
        }
        
        return $auth['user_type'] === 'client';
    }
    
    /**
     * Check if the authenticated user has freelancer role
     * 
     * @param array $auth Authentication data
     * @return bool True if user is freelancer, false otherwise
     */
    public function isFreelancer($auth) {
        if (!$auth || !isset($auth['user_type'])) {
            return false;
        }
        
        return $auth['user_type'] === 'freelancer';
    }
    
    /**
     * Check if the authenticated user matches a specific user ID
     * 
     * @param array $auth Authentication data
     * @param int $user_id User ID to check against
     * @return bool True if user IDs match, false otherwise
     */
    public function isSameUser($auth, $user_id) {
        if (!$auth || !isset($auth['user_id'])) {
            return false;
        }
        
        return $auth['user_id'] === $user_id;
    }
    
    /**
     * Generate response for unauthorized access
     * 
     * @param string $message Custom error message
     * @return array Error response
     */
    public function unauthorizedResponse($message = 'Unauthorized access') {
        return [
            'status' => 'error',
            'message' => $message,
            'code' => 401
        ];
    }
    
    /**
     * Generate response for forbidden access
     * 
     * @param string $message Custom error message
     * @return array Error response
     */
    public function forbiddenResponse($message = 'Forbidden access') {
        return [
            'status' => 'error',
            'message' => $message,
            'code' => 403
        ];
    }
    
    /**
     * Log authentication attempt
     * 
     * @param string $email User email
     * @param bool $success Whether the authentication was successful
     * @param string $ip_address IP address of the request
     * @return void
     */
    public function logAuthAttempt($email, $success, $ip_address) {
        // In a real implementation, this would log the authentication attempt to a database
        // For this demo, we'll skip the actual logging
        
        // Example query:
        // $query = "INSERT INTO auth_logs (email, success, ip_address, created_at) VALUES (:email, :success, :ip_address, NOW())";
        // $stmt = $this->db->prepare($query);
        // $stmt->bindParam(':email', $email);
        // $stmt->bindParam(':success', $success, PDO::PARAM_BOOL);
        // $stmt->bindParam(':ip_address', $ip_address);
        // $stmt->execute();
    }
    
    /**
     * Get rate limit status for an IP address
     * 
     * @param string $ip_address IP address to check
     * @return bool True if rate limit is exceeded, false otherwise
     */
    public function isRateLimited($ip_address) {
        // In a real implementation, this would check a rate limit store (Redis, Memcached, etc.)
        // For this demo, we'll always return false (not rate limited)
        
        return false;
    }
    
    /**
     * Record rate limit attempt
     * 
     * @param string $ip_address IP address to record
     * @return void
     */
    public function recordRateLimitAttempt($ip_address) {
        // In a real implementation, this would increment a counter in a rate limit store
        // For this demo, we'll skip the actual recording
    }
}
?>
