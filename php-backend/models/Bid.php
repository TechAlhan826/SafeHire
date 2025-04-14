<?php
/**
 * Bid Model
 * 
 * Handles all database operations related to bids
 */
class Bid {
    // Database connection and table name
    private $conn;
    private $table_name = "bids";

    // Object properties
    public $id;
    public $project_id;
    public $freelancer_id;
    public $amount;
    public $proposal;
    public $delivery_time;
    public $status; // 'pending', 'accepted', 'rejected'
    public $created_at;
    public $updated_at;
    public $team_id; // For team bids
    public $is_team_bid; // Boolean to indicate if it's a team bid

    /**
     * Constructor with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get bids for a project
     * 
     * @param int $project_id Project ID
     * @param string $status Bid status filter
     * @return array List of bids
     */
    public function getByProjectId($project_id, $status = null) {
        $query = "SELECT b.*, u.username as freelancer_name, u.profile_image, u.rating 
                  FROM " . $this->table_name . " b
                  JOIN users u ON b.freelancer_id = u.id
                  WHERE b.project_id = :project_id";
        
        // Filter by status if provided
        if ($status) {
            $query .= " AND b.status = :status";
        }
        
        $query .= " ORDER BY b.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $project_id = htmlspecialchars(strip_tags($project_id));
        $stmt->bindParam(":project_id", $project_id);
        
        if ($status) {
            $status = htmlspecialchars(strip_tags($status));
            $stmt->bindParam(":status", $status);
        }
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get bids by freelancer
     * 
     * @param int $freelancer_id Freelancer ID
     * @param string $status Bid status filter
     * @return array List of bids
     */
    public function getByFreelancerId($freelancer_id, $status = null) {
        $query = "SELECT b.*, p.title as project_title, p.budget as project_budget, 
                  u.username as client_name 
                  FROM " . $this->table_name . " b
                  JOIN projects p ON b.project_id = p.id
                  JOIN users u ON p.client_id = u.id
                  WHERE b.freelancer_id = :freelancer_id";
        
        // Filter by status if provided
        if ($status) {
            $query .= " AND b.status = :status";
        }
        
        $query .= " ORDER BY b.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $freelancer_id = htmlspecialchars(strip_tags($freelancer_id));
        $stmt->bindParam(":freelancer_id", $freelancer_id);
        
        if ($status) {
            $status = htmlspecialchars(strip_tags($status));
            $stmt->bindParam(":status", $status);
        }
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get bid by ID
     * 
     * @param int $id Bid ID
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
            $this->project_id = $row['project_id'];
            $this->freelancer_id = $row['freelancer_id'];
            $this->amount = $row['amount'];
            $this->proposal = $row['proposal'];
            $this->delivery_time = $row['delivery_time'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->team_id = $row['team_id'];
            $this->is_team_bid = $row['is_team_bid'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Create new bid
     * 
     * @return bool Operation result
     */
    public function create() {
        // Query
        $query = "INSERT INTO " . $this->table_name . " 
                  (project_id, freelancer_id, amount, proposal, delivery_time, status, team_id, is_team_bid) 
                  VALUES 
                  (:project_id, :freelancer_id, :amount, :proposal, :delivery_time, :status, :team_id, :is_team_bid)";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->project_id = htmlspecialchars(strip_tags($this->project_id));
        $this->freelancer_id = htmlspecialchars(strip_tags($this->freelancer_id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->proposal = htmlspecialchars(strip_tags($this->proposal));
        $this->delivery_time = htmlspecialchars(strip_tags($this->delivery_time));
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'pending'));
        $this->team_id = $this->team_id ?? null;
        $this->is_team_bid = $this->is_team_bid ?? 0;
        
        // Bind values
        $stmt->bindParam(":project_id", $this->project_id);
        $stmt->bindParam(":freelancer_id", $this->freelancer_id);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":proposal", $this->proposal);
        $stmt->bindParam(":delivery_time", $this->delivery_time);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":team_id", $this->team_id);
        $stmt->bindParam(":is_team_bid", $this->is_team_bid);
        
        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    /**
     * Update bid
     * 
     * @return bool Operation result
     */
    public function update() {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                  amount = :amount,
                  proposal = :proposal,
                  delivery_time = :delivery_time,
                  updated_at = NOW()
                  WHERE id = :id AND freelancer_id = :freelancer_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->freelancer_id = htmlspecialchars(strip_tags($this->freelancer_id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->proposal = htmlspecialchars(strip_tags($this->proposal));
        $this->delivery_time = htmlspecialchars(strip_tags($this->delivery_time));
        
        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":freelancer_id", $this->freelancer_id);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":proposal", $this->proposal);
        $stmt->bindParam(":delivery_time", $this->delivery_time);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Update bid status
     * 
     * @param string $status New status
     * @return bool Operation result
     */
    public function updateStatus($status) {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $status = htmlspecialchars(strip_tags($status));
        
        // Bind values
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->status = $status;
            return true;
        }
        
        return false;
    }

    /**
     * Check if user has already bid on a project
     * 
     * @param int $project_id Project ID
     * @param int $freelancer_id Freelancer ID
     * @return bool True if user has already bid, false otherwise
     */
    public function checkExistingBid($project_id, $freelancer_id) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE project_id = :project_id AND freelancer_id = :freelancer_id 
                  LIMIT 1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $project_id = htmlspecialchars(strip_tags($project_id));
        $freelancer_id = htmlspecialchars(strip_tags($freelancer_id));
        $stmt->bindParam(":project_id", $project_id);
        $stmt->bindParam(":freelancer_id", $freelancer_id);
        
        // Execute query
        $stmt->execute();
        
        return ($stmt->rowCount() > 0);
    }

    /**
     * Delete bid
     * 
     * @return bool Operation result
     */
    public function delete() {
        // Query
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id = :id AND freelancer_id = :freelancer_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->freelancer_id = htmlspecialchars(strip_tags($this->freelancer_id));
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":freelancer_id", $this->freelancer_id);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Get accepted bid for a project
     * 
     * @param int $project_id Project ID
     * @return bool Success or failure
     */
    public function getAcceptedBid($project_id) {
        $query = "SELECT b.*, u.username as freelancer_name 
                  FROM " . $this->table_name . " b
                  JOIN users u ON b.freelancer_id = u.id
                  WHERE b.project_id = :project_id AND b.status = 'accepted' 
                  LIMIT 1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $project_id = htmlspecialchars(strip_tags($project_id));
        $stmt->bindParam(":project_id", $project_id);
        
        // Execute query
        $stmt->execute();
        
        // Check if any row returned
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            
            // Set properties
            $this->id = $row['id'];
            $this->project_id = $row['project_id'];
            $this->freelancer_id = $row['freelancer_id'];
            $this->amount = $row['amount'];
            $this->proposal = $row['proposal'];
            $this->delivery_time = $row['delivery_time'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->team_id = $row['team_id'];
            $this->is_team_bid = $row['is_team_bid'];
            
            return true;
        }
        
        return false;
    }
}
?>
