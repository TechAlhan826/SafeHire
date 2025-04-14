<?php
/**
 * User Model
 * 
 * Handles all database operations related to users
 */
class User {
    // Database connection and table name
    private $conn;
    private $table_name = "users";

    // Object properties
    public $id;
    public $username;
    public $email;
    public $password;
    public $user_type; // 'freelancer', 'client', 'admin'
    public $profile_image;
    public $skills;
    public $portfolio;
    public $bio;
    public $hourly_rate;
    public $location;
    public $availability;
    public $rating;
    public $is_verified;
    public $created_at;
    public $updated_at;
    public $last_login;
    public $active_status;
    public $two_factor_enabled;
    public $two_factor_secret;

    /**
     * Constructor with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all users
     * 
     * @param string $type User type filter
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of users
     */
    public function getAll($type = null, $limit = 10, $offset = 0) {
        $query = "SELECT id, username, email, user_type, profile_image, skills, 
                  bio, hourly_rate, location, rating, is_verified, created_at 
                  FROM " . $this->table_name;
        
        // Filter by user type if provided
        if ($type) {
            $query .= " WHERE user_type = :user_type";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        if ($type) {
            $stmt->bindParam(":user_type", $type);
        }
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get user by ID
     * 
     * @param int $id User ID
     * @return bool Success or failure
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $id = htmlspecialchars(strip_tags($id));
        $stmt->bindParam(":id", $id);
        
        // Execute query
        $stmt->execute();
        
        // Check if any row returned
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            
            // Set properties
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->user_type = $row['user_type'];
            $this->profile_image = $row['profile_image'];
            $this->skills = $row['skills'];
            $this->portfolio = $row['portfolio'];
            $this->bio = $row['bio'];
            $this->hourly_rate = $row['hourly_rate'];
            $this->location = $row['location'];
            $this->availability = $row['availability'];
            $this->rating = $row['rating'];
            $this->is_verified = $row['is_verified'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->last_login = $row['last_login'];
            $this->active_status = $row['active_status'];
            $this->two_factor_enabled = $row['two_factor_enabled'];
            $this->two_factor_secret = $row['two_factor_secret'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Get user by email
     * 
     * @param string $email User email
     * @return bool Success or failure
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $email = htmlspecialchars(strip_tags($email));
        $stmt->bindParam(":email", $email);
        
        // Execute query
        $stmt->execute();
        
        // Check if any row returned
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            
            // Set properties
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->password = $row['password'];
            $this->user_type = $row['user_type'];
            // Set other properties...
            
            return true;
        }
        
        return false;
    }

    /**
     * Create new user
     * 
     * @return bool Operation result
     */
    public function create() {
        // Query with PostgreSQL RETURNING statement
        $query = "INSERT INTO " . $this->table_name . " 
                  (username, email, password, user_type, profile_image, skills, 
                   portfolio, bio, hourly_rate, location, availability, is_verified, 
                   active_status, two_factor_enabled) 
                  VALUES 
                  (:username, :email, :password, :user_type, :profile_image, :skills, 
                   :portfolio, :bio, :hourly_rate, :location, :availability, :is_verified, 
                   :active_status, :two_factor_enabled)
                  RETURNING id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        // Hash password
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        $this->user_type = htmlspecialchars(strip_tags($this->user_type));
        $this->profile_image = $this->profile_image ?? null;
        $this->skills = $this->skills ?? null;
        $this->portfolio = $this->portfolio ?? null;
        $this->bio = htmlspecialchars(strip_tags($this->bio ?? ''));
        $this->hourly_rate = $this->hourly_rate ?? 0;
        $this->location = htmlspecialchars(strip_tags($this->location ?? ''));
        $this->availability = $this->availability ?? 'available';
        $this->is_verified = $this->is_verified ?? 0;
        $this->active_status = $this->active_status ?? 1;
        $this->two_factor_enabled = $this->two_factor_enabled ?? 0;
        
        // Bind values
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":user_type", $this->user_type);
        $stmt->bindParam(":profile_image", $this->profile_image);
        $stmt->bindParam(":skills", $this->skills);
        $stmt->bindParam(":portfolio", $this->portfolio);
        $stmt->bindParam(":bio", $this->bio);
        $stmt->bindParam(":hourly_rate", $this->hourly_rate);
        $stmt->bindParam(":location", $this->location);
        $stmt->bindParam(":availability", $this->availability);
        $stmt->bindParam(":is_verified", $this->is_verified);
        $stmt->bindParam(":active_status", $this->active_status);
        $stmt->bindParam(":two_factor_enabled", $this->two_factor_enabled);
        
        // Execute query
        try {
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->id = $row['id'];
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Update user
     * 
     * @return bool Operation result
     */
    public function update() {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                  username = :username,
                  email = :email,
                  profile_image = :profile_image,
                  skills = :skills,
                  portfolio = :portfolio,
                  bio = :bio,
                  hourly_rate = :hourly_rate,
                  location = :location,
                  availability = :availability,
                  updated_at = NOW()
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->profile_image = $this->profile_image ?? null;
        $this->skills = $this->skills ?? null;
        $this->portfolio = $this->portfolio ?? null;
        $this->bio = htmlspecialchars(strip_tags($this->bio ?? ''));
        $this->hourly_rate = $this->hourly_rate ?? 0;
        $this->location = htmlspecialchars(strip_tags($this->location ?? ''));
        $this->availability = $this->availability ?? 'available';
        
        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":profile_image", $this->profile_image);
        $stmt->bindParam(":skills", $this->skills);
        $stmt->bindParam(":portfolio", $this->portfolio);
        $stmt->bindParam(":bio", $this->bio);
        $stmt->bindParam(":hourly_rate", $this->hourly_rate);
        $stmt->bindParam(":location", $this->location);
        $stmt->bindParam(":availability", $this->availability);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Update user password
     * 
     * @param string $password New password
     * @return bool Operation result
     */
    public function updatePassword($password) {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET password = :password, updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and hash password
        $password = password_hash($password, PASSWORD_BCRYPT);
        
        // Bind values
        $stmt->bindParam(":password", $password);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Update last login
     * 
     * @return bool Operation result
     */
    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . " 
                  SET last_login = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind value
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Search freelancers by skills
     * 
     * @param string $skills Skills to search for
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of matching freelancers
     */
    public function searchBySkills($skills, $limit = 10, $offset = 0) {
        // PostgreSQL uses array types
        $query = "SELECT id, username, email, profile_image, skills, bio, 
                  hourly_rate, location, rating, availability 
                  FROM " . $this->table_name . " 
                  WHERE user_type = 'freelancer' 
                  AND skills LIKE :skill 
                  ORDER BY rating DESC 
                  LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values with wildcard for LIKE pattern
        $skillParam = '%' . $skills . '%';
        $stmt->bindParam(":skill", $skillParam);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Toggle 2FA status
     * 
     * @param bool $status New 2FA status
     * @return bool Operation result
     */
    public function toggle2FA($status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET two_factor_enabled = :status 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $status = $status ? 1 : 0;
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->two_factor_enabled = $status;
            return true;
        }
        
        return false;
    }

    /**
     * Set 2FA secret
     * 
     * @param string $secret 2FA secret
     * @return bool Operation result
     */
    public function set2FASecret($secret) {
        $query = "UPDATE " . $this->table_name . " 
                  SET two_factor_secret = :secret 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":secret", $secret);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->two_factor_secret = $secret;
            return true;
        }
        
        return false;
    }

    /**
     * Get top-rated freelancers
     * 
     * @param int $limit Limit results
     * @return array List of top-rated freelancers
     */
    public function getTopFreelancers($limit = 10) {
        $query = "SELECT id, username, email, profile_image, skills, bio, 
                  hourly_rate, location, rating 
                  FROM " . $this->table_name . " 
                  WHERE user_type = 'freelancer' 
                  ORDER BY rating DESC 
                  LIMIT :limit";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind value
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>
