<?php
/**
 * User Controller
 * 
 * Handles user profile operations, searching, and directory functions
 */
class UserController {
    // Database connection and models
    private $conn;
    private $user;
    private $review;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize models
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/Review.php';
        
        $this->user = new User($db);
        $this->review = new Review($db);
    }
    
    /**
     * Get all users with optional filtering
     * 
     * @param string $type User type filter
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Response data
     */
    public function getUsers($type = null, $page = 1, $limit = 10) {
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
        
        // Get users
        $users = $this->user->getAll($type, $limit, $offset);
        
        return [
            'status' => 'success',
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($users) // Note: would use a separate count query in production
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id User ID
     * @return array Response data
     */
    public function getUserById($id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
        
        // Get user reviews
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $offset = ($page - 1) * $limit;
        
        $reviews = $this->review->getByUserId($id, $limit, $offset);
        
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
                'reviews' => $reviews
            ],
            'code' => 200
        ];
    }
    
    /**
     * Update user profile
     * 
     * @param int $id User ID
     * @return array Response data
     */
    public function updateProfile($id) {
        // Check if request method is PUT
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
        
        // Set updatable fields
        if (isset($data['username'])) $this->user->username = $data['username'];
        if (isset($data['bio'])) $this->user->bio = $data['bio'];
        if (isset($data['skills'])) $this->user->skills = $data['skills'];
        if (isset($data['portfolio'])) $this->user->portfolio = $data['portfolio'];
        if (isset($data['hourly_rate'])) $this->user->hourly_rate = $data['hourly_rate'];
        if (isset($data['location'])) $this->user->location = $data['location'];
        if (isset($data['availability'])) $this->user->availability = $data['availability'];
        
        // Handle profile image upload (simplified)
        if (isset($_FILES['profile_image'])) {
            $target_dir = __DIR__ . "/../uploads/profile/";
            $file_name = uniqid() . "_" . basename($_FILES["profile_image"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $this->user->profile_image = "/uploads/profile/" . $file_name;
            }
        }
        
        // Update the user
        if ($this->user->update()) {
            return [
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'profile_image' => $this->user->profile_image,
                    'skills' => $this->user->skills,
                    'bio' => $this->user->bio,
                    'hourly_rate' => $this->user->hourly_rate,
                    'location' => $this->user->location,
                    'availability' => $this->user->availability
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to update profile',
                'code' => 500
            ];
        }
    }
    
    /**
     * Change user password
     * 
     * @param int $id User ID
     * @return array Response data
     */
    public function changePassword($id) {
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
        
        // Validate required fields
        if (empty($data['current_password']) || empty($data['new_password'])) {
            return [
                'status' => 'error',
                'message' => 'Current password and new password are required',
                'code' => 400
            ];
        }
        
        // Verify current password
        if (!password_verify($data['current_password'], $this->user->password)) {
            return [
                'status' => 'error',
                'message' => 'Current password is incorrect',
                'code' => 401
            ];
        }
        
        // Validate password strength
        if (strlen($data['new_password']) < 8) {
            return [
                'status' => 'error',
                'message' => 'Password must be at least 8 characters long',
                'code' => 400
            ];
        }
        
        // Update password
        if ($this->user->updatePassword($data['new_password'])) {
            return [
                'status' => 'success',
                'message' => 'Password changed successfully',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to change password',
                'code' => 500
            ];
        }
    }
    
    /**
     * Search freelancers by skills
     * 
     * @return array Response data
     */
    public function searchFreelancers() {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get search parameters
        $skills = isset($_GET['skills']) ? $_GET['skills'] : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // Search freelancers
        if (!empty($skills)) {
            $freelancers = $this->user->searchBySkills($skills, $limit, $offset);
        } else {
            // Get all freelancers if no skills provided
            $freelancers = $this->user->getAll('freelancer', $limit, $offset);
        }
        
        return [
            'status' => 'success',
            'data' => $freelancers,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($freelancers) // Note: would use a separate count query in production
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get top-rated freelancers
     * 
     * @param int $limit Number of freelancers to return
     * @return array Response data
     */
    public function getTopFreelancers($limit = 10) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get top freelancers
        $freelancers = $this->user->getTopFreelancers($limit);
        
        return [
            'status' => 'success',
            'data' => $freelancers,
            'code' => 200
        ];
    }
    
    /**
     * Upload portfolio item
     * 
     * @param int $id User ID
     * @return array Response data
     */
    public function uploadPortfolio($id) {
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
        
        // Handle file upload (simplified)
        if (isset($_FILES['portfolio_file'])) {
            $target_dir = __DIR__ . "/../uploads/portfolio/";
            $file_name = uniqid() . "_" . basename($_FILES["portfolio_file"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["portfolio_file"]["tmp_name"], $target_file)) {
                // Get current portfolio items
                $portfolio = json_decode($this->user->portfolio ?? '[]', true);
                
                // Add new item
                $portfolio[] = [
                    'title' => $_POST['title'] ?? 'Portfolio Item',
                    'description' => $_POST['description'] ?? '',
                    'file_path' => "/uploads/portfolio/" . $file_name,
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                
                // Update user portfolio
                $this->user->portfolio = json_encode($portfolio);
                
                if ($this->user->update()) {
                    return [
                        'status' => 'success',
                        'message' => 'Portfolio item uploaded successfully',
                        'data' => end($portfolio),
                        'code' => 200
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Unable to update portfolio',
                        'code' => 500
                    ];
                }
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to upload file',
                    'code' => 500
                ];
            }
        } else {
            return [
                'status' => 'error',
                'message' => 'No file uploaded',
                'code' => 400
            ];
        }
    }
    
    /**
     * Delete portfolio item
     * 
     * @param int $id User ID
     * @param int $item_index Portfolio item index
     * @return array Response data
     */
    public function deletePortfolioItem($id, $item_index) {
        // Check if request method is DELETE
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
        
        // Get current portfolio items
        $portfolio = json_decode($this->user->portfolio ?? '[]', true);
        
        // Check if item exists
        if (!isset($portfolio[$item_index])) {
            return [
                'status' => 'error',
                'message' => 'Portfolio item not found',
                'code' => 404
            ];
        }
        
        // Remove the item
        array_splice($portfolio, $item_index, 1);
        
        // Update user portfolio
        $this->user->portfolio = json_encode($portfolio);
        
        if ($this->user->update()) {
            return [
                'status' => 'success',
                'message' => 'Portfolio item deleted successfully',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to update portfolio',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get directory of users
     * 
     * @return array Response data
     */
    public function getDirectory() {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get filter parameters
        $user_type = isset($_GET['user_type']) ? $_GET['user_type'] : null;
        $skills = isset($_GET['skills']) ? $_GET['skills'] : null;
        $rating = isset($_GET['rating']) ? (float)$_GET['rating'] : null;
        $location = isset($_GET['location']) ? $_GET['location'] : null;
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // For simplicity, we'll just use getAll and searchBySkills
        // In a production app, you'd create a more sophisticated search method
        
        if (!empty($skills)) {
            $users = $this->user->searchBySkills($skills, $limit, $offset);
        } else {
            $users = $this->user->getAll($user_type, $limit, $offset);
        }
        
        // Filter by rating and location (client-side filtering for demo)
        if ($rating !== null || $location !== null) {
            $users = array_filter($users, function($user) use ($rating, $location) {
                $rating_match = $rating === null || $user['rating'] >= $rating;
                $location_match = $location === null || stripos($user['location'], $location) !== false;
                return $rating_match && $location_match;
            });
        }
        
        return [
            'status' => 'success',
            'data' => array_values($users), // Reset array keys
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($users) // Note: would use a separate count query in production
            ],
            'code' => 200
        ];
    }
}
?>
