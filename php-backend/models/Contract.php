<?php
/**
 * Contract Model
 * 
 * Handles all database operations related to contracts
 */
class Contract {
    // Database connection and table name
    private $conn;
    private $table_name = "contracts";

    // Object properties
    public $id;
    public $project_id;
    public $client_id;
    public $freelancer_id;
    public $bid_id;
    public $start_date;
    public $end_date;
    public $amount;
    public $status; // 'active', 'completed', 'cancelled'
    public $payment_status; // 'pending', 'partial', 'completed'
    public $created_at;
    public $updated_at;
    public $completion_code;
    public $milestone_codes;
    public $escrow_id;
    public $team_id; // For team contracts

    /**
     * Constructor with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all contracts
     * 
     * @param string $status Contract status filter
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of contracts
     */
    public function getAll($status = null, $limit = 10, $offset = 0) {
        $query = "SELECT c.*, p.title as project_title, 
                  u1.username as client_name, u2.username as freelancer_name 
                  FROM " . $this->table_name . " c
                  JOIN projects p ON c.project_id = p.id
                  JOIN users u1 ON c.client_id = u1.id
                  JOIN users u2 ON c.freelancer_id = u2.id";
        
        // Filter by status if provided
        if ($status) {
            $query .= " WHERE c.status = :status";
        }
        
        $query .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        if ($status) {
            $stmt->bindParam(":status", $status);
        }
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get contract by ID
     * 
     * @param int $id Contract ID
     * @return bool Success or failure
     */
    public function getById($id) {
        $query = "SELECT c.*, p.title as project_title, p.description as project_description, 
                  u1.username as client_name, u1.email as client_email, 
                  u2.username as freelancer_name, u2.email as freelancer_email 
                  FROM " . $this->table_name . " c
                  JOIN projects p ON c.project_id = p.id
                  JOIN users u1 ON c.client_id = u1.id
                  JOIN users u2 ON c.freelancer_id = u2.id
                  WHERE c.id = :id LIMIT 1";
        
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
            $this->client_id = $row['client_id'];
            $this->freelancer_id = $row['freelancer_id'];
            $this->bid_id = $row['bid_id'];
            $this->start_date = $row['start_date'];
            $this->end_date = $row['end_date'];
            $this->amount = $row['amount'];
            $this->status = $row['status'];
            $this->payment_status = $row['payment_status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->completion_code = $row['completion_code'];
            $this->milestone_codes = $row['milestone_codes'];
            $this->escrow_id = $row['escrow_id'];
            $this->team_id = $row['team_id'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Get contracts by client ID
     * 
     * @param int $client_id Client ID
     * @param string $status Contract status filter
     * @return array List of contracts
     */
    public function getByClientId($client_id, $status = null) {
        $query = "SELECT c.*, p.title as project_title, 
                  u.username as freelancer_name, u.profile_image as freelancer_image 
                  FROM " . $this->table_name . " c
                  JOIN projects p ON c.project_id = p.id
                  JOIN users u ON c.freelancer_id = u.id
                  WHERE c.client_id = :client_id";
        
        // Filter by status if provided
        if ($status) {
            $query .= " AND c.status = :status";
        }
        
        $query .= " ORDER BY c.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $client_id = htmlspecialchars(strip_tags($client_id));
        $stmt->bindParam(":client_id", $client_id);
        
        if ($status) {
            $status = htmlspecialchars(strip_tags($status));
            $stmt->bindParam(":status", $status);
        }
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get contracts by freelancer ID
     * 
     * @param int $freelancer_id Freelancer ID
     * @param string $status Contract status filter
     * @return array List of contracts
     */
    public function getByFreelancerId($freelancer_id, $status = null) {
        $query = "SELECT c.*, p.title as project_title, 
                  u.username as client_name, u.profile_image as client_image 
                  FROM " . $this->table_name . " c
                  JOIN projects p ON c.project_id = p.id
                  JOIN users u ON c.client_id = u.id
                  WHERE c.freelancer_id = :freelancer_id";
        
        // Filter by status if provided
        if ($status) {
            $query .= " AND c.status = :status";
        }
        
        $query .= " ORDER BY c.created_at DESC";
        
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
     * Create new contract
     * 
     * @return bool Operation result
     */
    public function create() {
        // Generate completion code
        $completion_code = $this->generateRandomCode();
        
        // Generate milestone codes if needed
        $milestone_codes = json_encode([
            'milestone1' => $this->generateRandomCode(),
            'milestone2' => $this->generateRandomCode(),
            'milestone3' => $this->generateRandomCode()
        ]);
        
        // Query
        $query = "INSERT INTO " . $this->table_name . " 
                  (project_id, client_id, freelancer_id, bid_id, start_date, end_date, 
                   amount, status, payment_status, completion_code, milestone_codes, 
                   escrow_id, team_id) 
                  VALUES 
                  (:project_id, :client_id, :freelancer_id, :bid_id, :start_date, :end_date, 
                   :amount, :status, :payment_status, :completion_code, :milestone_codes, 
                   :escrow_id, :team_id)";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->project_id = htmlspecialchars(strip_tags($this->project_id));
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->freelancer_id = htmlspecialchars(strip_tags($this->freelancer_id));
        $this->bid_id = htmlspecialchars(strip_tags($this->bid_id));
        $this->start_date = htmlspecialchars(strip_tags($this->start_date ?? date('Y-m-d')));
        $this->end_date = htmlspecialchars(strip_tags($this->end_date ?? ''));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'active'));
        $this->payment_status = htmlspecialchars(strip_tags($this->payment_status ?? 'pending'));
        $this->escrow_id = $this->escrow_id ?? null;
        $this->team_id = $this->team_id ?? null;
        
        // Bind values
        $stmt->bindParam(":project_id", $this->project_id);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":freelancer_id", $this->freelancer_id);
        $stmt->bindParam(":bid_id", $this->bid_id);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":end_date", $this->end_date);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":payment_status", $this->payment_status);
        $stmt->bindParam(":completion_code", $completion_code);
        $stmt->bindParam(":milestone_codes", $milestone_codes);
        $stmt->bindParam(":escrow_id", $this->escrow_id);
        $stmt->bindParam(":team_id", $this->team_id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->completion_code = $completion_code;
            $this->milestone_codes = $milestone_codes;
            return true;
        }
        
        return false;
    }

    /**
     * Update contract
     * 
     * @return bool Operation result
     */
    public function update() {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                  start_date = :start_date,
                  end_date = :end_date,
                  amount = :amount,
                  status = :status,
                  payment_status = :payment_status,
                  updated_at = NOW()
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->start_date = htmlspecialchars(strip_tags($this->start_date));
        $this->end_date = htmlspecialchars(strip_tags($this->end_date));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->payment_status = htmlspecialchars(strip_tags($this->payment_status));
        
        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":end_date", $this->end_date);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":payment_status", $this->payment_status);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Update contract status
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
     * Update payment status
     * 
     * @param string $payment_status New payment status
     * @return bool Operation result
     */
    public function updatePaymentStatus($payment_status) {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET payment_status = :payment_status, updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $payment_status = htmlspecialchars(strip_tags($payment_status));
        
        // Bind values
        $stmt->bindParam(":payment_status", $payment_status);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->payment_status = $payment_status;
            return true;
        }
        
        return false;
    }

    /**
     * Check if completion code is valid
     * 
     * @param string $code Completion code to check
     * @return bool True if code is valid, false otherwise
     */
    public function validateCompletionCode($code) {
        return ($this->completion_code === $code);
    }

    /**
     * Check if milestone code is valid
     * 
     * @param string $milestone Milestone name
     * @param string $code Milestone code to check
     * @return bool True if code is valid, false otherwise
     */
    public function validateMilestoneCode($milestone, $code) {
        $milestone_codes = json_decode($this->milestone_codes, true);
        
        if (isset($milestone_codes[$milestone])) {
            return ($milestone_codes[$milestone] === $code);
        }
        
        return false;
    }

    /**
     * Generate random code for milestones and completion
     * 
     * @param int $length Code length
     * @return string Random code
     */
    private function generateRandomCode($length = 8) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $code;
    }

    /**
     * Get active contract for a project
     * 
     * @param int $project_id Project ID
     * @return bool Success or failure
     */
    public function getActiveContractByProject($project_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE project_id = :project_id AND status = 'active' 
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
            $this->client_id = $row['client_id'];
            $this->freelancer_id = $row['freelancer_id'];
            $this->bid_id = $row['bid_id'];
            $this->start_date = $row['start_date'];
            $this->end_date = $row['end_date'];
            $this->amount = $row['amount'];
            $this->status = $row['status'];
            $this->payment_status = $row['payment_status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->completion_code = $row['completion_code'];
            $this->milestone_codes = $row['milestone_codes'];
            $this->escrow_id = $row['escrow_id'];
            $this->team_id = $row['team_id'];
            
            return true;
        }
        
        return false;
    }
}
?>
