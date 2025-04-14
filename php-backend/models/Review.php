<?php
/**
 * Review Model
 * 
 * Handles all database operations related to reviews
 */
class Review {
    // Database connection and table name
    private $conn;
    private $table_name = "reviews";

    // Object properties
    public $id;
    public $project_id;
    public $contract_id;
    public $reviewer_id;
    public $reviewee_id;
    public $rating;
    public $review_text;
    public $created_at;
    public $updated_at;
    public $review_type; // 'client_to_freelancer' or 'freelancer_to_client'

    /**
     * Constructor with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all reviews
     * 
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of reviews
     */
    public function getAll($limit = 10, $offset = 0) {
        $query = "SELECT r.*, p.title as project_title, 
                  u1.username as reviewer_name, u2.username as reviewee_name 
                  FROM " . $this->table_name . " r
                  JOIN projects p ON r.project_id = p.id
                  JOIN users u1 ON r.reviewer_id = u1.id
                  JOIN users u2 ON r.reviewee_id = u2.id
                  ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get review by ID
     * 
     * @param int $id Review ID
     * @return bool Success or failure
     */
    public function getById($id) {
        $query = "SELECT r.*, p.title as project_title, 
                  u1.username as reviewer_name, u1.profile_image as reviewer_image, 
                  u2.username as reviewee_name, u2.profile_image as reviewee_image 
                  FROM " . $this->table_name . " r
                  JOIN projects p ON r.project_id = p.id
                  JOIN users u1 ON r.reviewer_id = u1.id
                  JOIN users u2 ON r.reviewee_id = u2.id
                  WHERE r.id = :id LIMIT 1";
        
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
            $this->project_id = $row['project_id'];
            $this->contract_id = $row['contract_id'];
            $this->reviewer_id = $row['reviewer_id'];
            $this->reviewee_id = $row['reviewee_id'];
            $this->rating = $row['rating'];
            $this->review_text = $row['review_text'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->review_type = $row['review_type'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Get reviews by user ID (as reviewee)
     * 
     * @param int $user_id User ID
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of reviews
     */
    public function getByUserId($user_id, $limit = 10, $offset = 0) {
        $query = "SELECT r.*, p.title as project_title, 
                  u.username as reviewer_name, u.profile_image as reviewer_image 
                  FROM " . $this->table_name . " r
                  JOIN projects p ON r.project_id = p.id
                  JOIN users u ON r.reviewer_id = u.id
                  WHERE r.reviewee_id = :user_id 
                  ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $user_id = htmlspecialchars(strip_tags($user_id));
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get reviews by project ID
     * 
     * @param int $project_id Project ID
     * @return array List of reviews
     */
    public function getByProjectId($project_id) {
        $query = "SELECT r.*, u1.username as reviewer_name, u1.profile_image as reviewer_image, 
                  u2.username as reviewee_name, u2.profile_image as reviewee_image 
                  FROM " . $this->table_name . " r
                  JOIN users u1 ON r.reviewer_id = u1.id
                  JOIN users u2 ON r.reviewee_id = u2.id
                  WHERE r.project_id = :project_id 
                  ORDER BY r.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $project_id = htmlspecialchars(strip_tags($project_id));
        $stmt->bindParam(":project_id", $project_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Create new review
     * 
     * @return bool Operation result
     */
    public function create() {
        // Query
        $query = "INSERT INTO " . $this->table_name . " 
                  (project_id, contract_id, reviewer_id, reviewee_id, rating, 
                   review_text, review_type) 
                  VALUES 
                  (:project_id, :contract_id, :reviewer_id, :reviewee_id, :rating, 
                   :review_text, :review_type)";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->project_id = htmlspecialchars(strip_tags($this->project_id));
        $this->contract_id = htmlspecialchars(strip_tags($this->contract_id));
        $this->reviewer_id = htmlspecialchars(strip_tags($this->reviewer_id));
        $this->reviewee_id = htmlspecialchars(strip_tags($this->reviewee_id));
        $this->rating = htmlspecialchars(strip_tags($this->rating));
        $this->review_text = htmlspecialchars(strip_tags($this->review_text));
        $this->review_type = htmlspecialchars(strip_tags($this->review_type));
        
        // Bind values
        $stmt->bindParam(":project_id", $this->project_id);
        $stmt->bindParam(":contract_id", $this->contract_id);
        $stmt->bindParam(":reviewer_id", $this->reviewer_id);
        $stmt->bindParam(":reviewee_id", $this->reviewee_id);
        $stmt->bindParam(":rating", $this->rating);
        $stmt->bindParam(":review_text", $this->review_text);
        $stmt->bindParam(":review_type", $this->review_type);
        
        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            
            // Update user rating
            $this->updateUserRating($this->reviewee_id);
            
            return true;
        }
        
        return false;
    }

    /**
     * Update review
     * 
     * @return bool Operation result
     */
    public function update() {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                  rating = :rating,
                  review_text = :review_text,
                  updated_at = NOW()
                  WHERE id = :id AND reviewer_id = :reviewer_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->reviewer_id = htmlspecialchars(strip_tags($this->reviewer_id));
        $this->rating = htmlspecialchars(strip_tags($this->rating));
        $this->review_text = htmlspecialchars(strip_tags($this->review_text));
        
        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":reviewer_id", $this->reviewer_id);
        $stmt->bindParam(":rating", $this->rating);
        $stmt->bindParam(":review_text", $this->review_text);
        
        // Execute query
        if ($stmt->execute()) {
            // Update user rating
            $this->updateUserRating($this->reviewee_id);
            
            return true;
        }
        
        return false;
    }

    /**
     * Check if user has already reviewed a project
     * 
     * @param int $project_id Project ID
     * @param int $reviewer_id Reviewer ID
     * @param int $reviewee_id Reviewee ID
     * @return bool True if review exists, false otherwise
     */
    public function checkExistingReview($project_id, $reviewer_id, $reviewee_id) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE project_id = :project_id 
                  AND reviewer_id = :reviewer_id 
                  AND reviewee_id = :reviewee_id 
                  LIMIT 1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $project_id = htmlspecialchars(strip_tags($project_id));
        $reviewer_id = htmlspecialchars(strip_tags($reviewer_id));
        $reviewee_id = htmlspecialchars(strip_tags($reviewee_id));
        $stmt->bindParam(":project_id", $project_id);
        $stmt->bindParam(":reviewer_id", $reviewer_id);
        $stmt->bindParam(":reviewee_id", $reviewee_id);
        
        // Execute query
        $stmt->execute();
        
        return ($stmt->rowCount() > 0);
    }

    /**
     * Update user's average rating
     * 
     * @param int $user_id User ID
     * @return bool Operation result
     */
    private function updateUserRating($user_id) {
        // Query to calculate average rating
        $query = "SELECT AVG(rating) as avg_rating FROM " . $this->table_name . " 
                  WHERE reviewee_id = :user_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind value
        $stmt->bindParam(":user_id", $user_id);
        
        // Execute query
        $stmt->execute();
        $row = $stmt->fetch();
        
        $avg_rating = $row['avg_rating'] ?? 0;
        
        // Query to update user's rating
        $query = "UPDATE users SET rating = :rating WHERE id = :user_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":rating", $avg_rating);
        $stmt->bindParam(":user_id", $user_id);
        
        // Execute query
        return $stmt->execute();
    }

    /**
     * Get top-rated reviews
     * 
     * @param int $limit Limit results
     * @return array List of top-rated reviews
     */
    public function getTopRated($limit = 5) {
        $query = "SELECT r.*, p.title as project_title, 
                  u1.username as reviewer_name, u1.profile_image as reviewer_image, 
                  u2.username as reviewee_name, u2.profile_image as reviewee_image 
                  FROM " . $this->table_name . " r
                  JOIN projects p ON r.project_id = p.id
                  JOIN users u1 ON r.reviewer_id = u1.id
                  JOIN users u2 ON r.reviewee_id = u2.id
                  WHERE r.rating >= 4 
                  ORDER BY r.rating DESC, r.created_at DESC 
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
