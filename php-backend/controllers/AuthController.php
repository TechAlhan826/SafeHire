<?php
/**
 * Authentication Controller
 * 
 * Handles user registration, login, logout, password reset, and 2FA
 */
class AuthController {
    // Database connection and models
    private $conn;
    private $user;
    private $jwt;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize models
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../utils/JWT.php';
        
        $this->user = new User($db);
        $this->jwt = new JWT();
    }
    
    /**
     * User registration
     * 
     * @return array Response data
     */
    public function register() {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        $required_fields = ['username', 'email', 'password', 'user_type'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return [
                    'status' => 'error',
                    'message' => ucfirst($field) . ' is required',
                    'code' => 400
                ];
            }
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'error',
                'message' => 'Invalid email format',
                'code' => 400
            ];
        }
        
        // Validate password strength
        if (strlen($data['password']) < 8) {
            return [
                'status' => 'error',
                'message' => 'Password must be at least 8 characters long',
                'code' => 400
            ];
        }
        
        // Check if email already exists
        if ($this->user->getByEmail($data['email'])) {
            return [
                'status' => 'error',
                'message' => 'Email already in use',
                'code' => 409
            ];
        }
        
        // Set user properties
        $this->user->username = $data['username'];
        $this->user->email = $data['email'];
        $this->user->password = $data['password']; // Model will handle hashing
        $this->user->user_type = $data['user_type'];
        $this->user->skills = $data['skills'] ?? null;
        $this->user->bio = $data['bio'] ?? null;
        $this->user->hourly_rate = $data['hourly_rate'] ?? 0;
        $this->user->location = $data['location'] ?? null;
        $this->user->availability = $data['availability'] ?? 'available';
        
        // Create the user
        if ($this->user->create()) {
            // Generate JWT token
            $token = $this->jwt->generate([
                'id' => $this->user->id,
                'email' => $this->user->email,
                'user_type' => $this->user->user_type
            ]);
            
            // Update last login
            $this->user->updateLastLogin();
            
            return [
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $this->user->id,
                        'username' => $this->user->username,
                        'email' => $this->user->email,
                        'user_type' => $this->user->user_type
                    ]
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to register user',
                'code' => 500
            ];
        }
    }
    
    /**
     * User login
     * 
     * @return array Response data
     */
    public function login() {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        if (empty($data['email']) || empty($data['password'])) {
            return [
                'status' => 'error',
                'message' => 'Email and password are required',
                'code' => 400
            ];
        }
        
        // Check if email exists
        if (!$this->user->getByEmail($data['email'])) {
            return [
                'status' => 'error',
                'message' => 'Invalid credentials',
                'code' => 401
            ];
        }
        
        // Verify password
        if (!password_verify($data['password'], $this->user->password)) {
            return [
                'status' => 'error',
                'message' => 'Invalid credentials',
                'code' => 401
            ];
        }
        
        // Check if 2FA is enabled
        if ($this->user->two_factor_enabled) {
            if (empty($data['code'])) {
                return [
                    'status' => 'two_factor_required',
                    'message' => '2FA code is required',
                    'code' => 200
                ];
            }
            
            // Verify 2FA code
            require_once __DIR__ . '/../utils/GoogleAuthenticator.php';
            $ga = new GoogleAuthenticator();
            $check = $ga->verifyCode($this->user->two_factor_secret, $data['code'], 2);
            
            if (!$check) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid 2FA code',
                    'code' => 401
                ];
            }
        }
        
        // Generate JWT token
        $token = $this->jwt->generate([
            'id' => $this->user->id,
            'email' => $this->user->email,
            'user_type' => $this->user->user_type
        ]);
        
        // Update last login
        $this->user->updateLastLogin();
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'user_type' => $this->user->user_type,
                    'profile_image' => $this->user->profile_image,
                    'skills' => $this->user->skills,
                    'bio' => $this->user->bio,
                    'rating' => $this->user->rating
                ]
            ],
            'code' => 200
        ];
    }
    
    /**
     * Setup 2FA for user
     * 
     * @param int $user_id User ID
     * @return array Response data
     */
    public function setup2FA($user_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user by ID
        if (!$this->user->getById($user_id)) {
            return [
                'status' => 'error',
                'message' => 'User not found',
                'code' => 404
            ];
        }
        
        // Generate new secret
        require_once __DIR__ . '/../utils/GoogleAuthenticator.php';
        $ga = new GoogleAuthenticator();
        $secret = $ga->createSecret();
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // If code is provided, verify it
        if (!empty($data['code'])) {
            $check = $ga->verifyCode($secret, $data['code'], 2);
            
            if ($check) {
                // Save secret and enable 2FA
                $this->user->set2FASecret($secret);
                $this->user->toggle2FA(true);
                
                return [
                    'status' => 'success',
                    'message' => '2FA has been enabled',
                    'code' => 200
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Invalid verification code',
                    'code' => 400
                ];
            }
        }
        
        // Generate QR code URL
        $qrCodeUrl = $ga->getQRCodeGoogleUrl(
            'SafeHire:' . $this->user->email,
            $secret
        );
        
        return [
            'status' => 'success',
            'message' => '2FA setup initialized',
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl
            ],
            'code' => 200
        ];
    }
    
    /**
     * Disable 2FA for user
     * 
     * @param int $user_id User ID
     * @return array Response data
     */
    public function disable2FA($user_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user by ID
        if (!$this->user->getById($user_id)) {
            return [
                'status' => 'error',
                'message' => 'User not found',
                'code' => 404
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Verify password
        if (empty($data['password']) || !password_verify($data['password'], $this->user->password)) {
            return [
                'status' => 'error',
                'message' => 'Invalid password',
                'code' => 401
            ];
        }
        
        // Disable 2FA
        $this->user->toggle2FA(false);
        
        return [
            'status' => 'success',
            'message' => '2FA has been disabled',
            'code' => 200
        ];
    }
    
    /**
     * Request password reset
     * 
     * @return array Response data
     */
    public function requestPasswordReset() {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate email
        if (empty($data['email'])) {
            return [
                'status' => 'error',
                'message' => 'Email is required',
                'code' => 400
            ];
        }
        
        // Check if email exists
        if (!$this->user->getByEmail($data['email'])) {
            return [
                'status' => 'success',
                'message' => 'If the email exists, a reset link has been sent',
                'code' => 200
            ];
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token (simplified, would use a proper password_reset table in production)
        // For demo, we'll just return the token
        
        return [
            'status' => 'success',
            'message' => 'If the email exists, a reset link has been sent',
            'data' => [
                'token' => $token, // In production, this would be sent via email
                'expires' => $expires
            ],
            'code' => 200
        ];
    }
    
    /**
     * Reset password
     * 
     * @return array Response data
     */
    public function resetPassword() {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        if (empty($data['token']) || empty($data['email']) || empty($data['password'])) {
            return [
                'status' => 'error',
                'message' => 'Token, email, and new password are required',
                'code' => 400
            ];
        }
        
        // Validate password strength
        if (strlen($data['password']) < 8) {
            return [
                'status' => 'error',
                'message' => 'Password must be at least 8 characters long',
                'code' => 400
            ];
        }
        
        // Check if email exists
        if (!$this->user->getByEmail($data['email'])) {
            return [
                'status' => 'error',
                'message' => 'Invalid email',
                'code' => 404
            ];
        }
        
        // Verify token (simplified, would check against stored token in production)
        // For demo, we'll assume the token is valid
        
        // Update password
        if ($this->user->updatePassword($data['password'])) {
            return [
                'status' => 'success',
                'message' => 'Password has been reset successfully',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to reset password',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get authenticated user's profile
     * 
     * @param int $user_id User ID
     * @return array Response data
     */
    public function getProfile($user_id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user by ID
        if (!$this->user->getById($user_id)) {
            return [
                'status' => 'error',
                'message' => 'User not found',
                'code' => 404
            ];
        }
        
        return [
            'status' => 'success',
            'data' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'email' => $this->user->email,
                'user_type' => $this->user->user_type,
                'profile_image' => $this->user->profile_image,
                'skills' => $this->user->skills,
                'portfolio' => $this->user->portfolio,
                'bio' => $this->user->bio,
                'hourly_rate' => $this->user->hourly_rate,
                'location' => $this->user->location,
                'availability' => $this->user->availability,
                'rating' => $this->user->rating,
                'is_verified' => $this->user->is_verified,
                'created_at' => $this->user->created_at,
                'two_factor_enabled' => $this->user->two_factor_enabled,
                'last_login' => $this->user->last_login
            ],
            'code' => 200
        ];
    }
}
?>
