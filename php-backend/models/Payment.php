<?php
/**
 * Payment Model
 * 
 * Handles all database operations related to payments
 */
class Payment {
    // Database connection and table name
    private $conn;
    private $table_name = "payments";

    // Object properties
    public $id;
    public $contract_id;
    public $amount;
    public $payment_type; // 'advance', 'milestone', 'final'
    public $payment_method; // 'razorpay', 'upi', etc.
    public $transaction_id;
    public $status; // 'pending', 'completed', 'failed', 'refunded'
    public $created_at;
    public $updated_at;
    public $payer_id; // Client ID
    public $payee_id; // Freelancer ID
    public $milestone_id; // For milestone payments
    public $payment_details; // JSON with additional details
    public $redeemed_code; // Code used for redeeming payment

    /**
     * Constructor with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all payments
     * 
     * @param string $status Payment status filter
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of payments
     */
    public function getAll($status = null, $limit = 10, $offset = 0) {
        $query = "SELECT p.*, c.project_id, 
                  u1.username as payer_name, u2.username as payee_name 
                  FROM " . $this->table_name . " p
                  JOIN contracts c ON p.contract_id = c.id
                  JOIN users u1 ON p.payer_id = u1.id
                  JOIN users u2 ON p.payee_id = u2.id";
        
        // Filter by status if provided
        if ($status) {
            $query .= " WHERE p.status = :status";
        }
        
        $query .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
        
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
     * Get payment by ID
     * 
     * @param int $id Payment ID
     * @return bool Success or failure
     */
    public function getById($id) {
        $query = "SELECT p.*, c.project_id, pr.title as project_title, 
                  u1.username as payer_name, u1.email as payer_email, 
                  u2.username as payee_name, u2.email as payee_email 
                  FROM " . $this->table_name . " p
                  JOIN contracts c ON p.contract_id = c.id
                  JOIN projects pr ON c.project_id = pr.id
                  JOIN users u1 ON p.payer_id = u1.id
                  JOIN users u2 ON p.payee_id = u2.id
                  WHERE p.id = :id LIMIT 1";
        
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
            $this->contract_id = $row['contract_id'];
            $this->amount = $row['amount'];
            $this->payment_type = $row['payment_type'];
            $this->payment_method = $row['payment_method'];
            $this->transaction_id = $row['transaction_id'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->payer_id = $row['payer_id'];
            $this->payee_id = $row['payee_id'];
            $this->milestone_id = $row['milestone_id'];
            $this->payment_details = $row['payment_details'];
            $this->redeemed_code = $row['redeemed_code'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Get payments by contract ID
     * 
     * @param int $contract_id Contract ID
     * @return array List of payments
     */
    public function getByContractId($contract_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE contract_id = :contract_id 
                  ORDER BY created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $contract_id = htmlspecialchars(strip_tags($contract_id));
        $stmt->bindParam(":contract_id", $contract_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get payments by user ID (either payer or payee)
     * 
     * @param int $user_id User ID
     * @param string $role Role ('payer' or 'payee')
     * @return array List of payments
     */
    public function getByUserId($user_id, $role = 'payer') {
        $column = ($role === 'payee') ? 'payee_id' : 'payer_id';
        
        $query = "SELECT p.*, c.project_id, pr.title as project_title, 
                  u.username as other_user_name 
                  FROM " . $this->table_name . " p
                  JOIN contracts c ON p.contract_id = c.id
                  JOIN projects pr ON c.project_id = pr.id
                  JOIN users u ON p." . ($role === 'payee' ? 'payer_id' : 'payee_id') . " = u.id
                  WHERE p." . $column . " = :user_id 
                  ORDER BY p.created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $user_id = htmlspecialchars(strip_tags($user_id));
        $stmt->bindParam(":user_id", $user_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Create new payment
     * 
     * @return bool Operation result
     */
    public function create() {
        // Query
        $query = "INSERT INTO " . $this->table_name . " 
                  (contract_id, amount, payment_type, payment_method, transaction_id, 
                   status, payer_id, payee_id, milestone_id, payment_details, redeemed_code) 
                  VALUES 
                  (:contract_id, :amount, :payment_type, :payment_method, :transaction_id, 
                   :status, :payer_id, :payee_id, :milestone_id, :payment_details, :redeemed_code)";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->contract_id = htmlspecialchars(strip_tags($this->contract_id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->payment_type = htmlspecialchars(strip_tags($this->payment_type));
        $this->payment_method = htmlspecialchars(strip_tags($this->payment_method));
        $this->transaction_id = htmlspecialchars(strip_tags($this->transaction_id ?? ''));
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'pending'));
        $this->payer_id = htmlspecialchars(strip_tags($this->payer_id));
        $this->payee_id = htmlspecialchars(strip_tags($this->payee_id));
        $this->milestone_id = $this->milestone_id ?? null;
        $this->payment_details = $this->payment_details ?? null;
        $this->redeemed_code = $this->redeemed_code ?? null;
        
        // Bind values
        $stmt->bindParam(":contract_id", $this->contract_id);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":payment_type", $this->payment_type);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":transaction_id", $this->transaction_id);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":payer_id", $this->payer_id);
        $stmt->bindParam(":payee_id", $this->payee_id);
        $stmt->bindParam(":milestone_id", $this->milestone_id);
        $stmt->bindParam(":payment_details", $this->payment_details);
        $stmt->bindParam(":redeemed_code", $this->redeemed_code);
        
        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    /**
     * Update payment status
     * 
     * @param string $status New status
     * @param string $transaction_id Transaction ID (optional)
     * @return bool Operation result
     */
    public function updateStatus($status, $transaction_id = null) {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, updated_at = NOW()";
        
        // Add transaction ID to update if provided
        if ($transaction_id) {
            $query .= ", transaction_id = :transaction_id";
        }
        
        $query .= " WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $status = htmlspecialchars(strip_tags($status));
        
        // Bind values
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id);
        
        if ($transaction_id) {
            $transaction_id = htmlspecialchars(strip_tags($transaction_id));
            $stmt->bindParam(":transaction_id", $transaction_id);
        }
        
        // Execute query
        if ($stmt->execute()) {
            $this->status = $status;
            if ($transaction_id) {
                $this->transaction_id = $transaction_id;
            }
            return true;
        }
        
        return false;
    }

    /**
     * Update payment details
     * 
     * @param array $details Payment details
     * @return bool Operation result
     */
    public function updateDetails($details) {
        // Convert details array to JSON
        $details_json = json_encode($details);
        
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET payment_details = :payment_details, updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":payment_details", $details_json);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->payment_details = $details_json;
            return true;
        }
        
        return false;
    }

    /**
     * Record payment with redeemed code
     * 
     * @param string $code Redeemed code
     * @return bool Operation result
     */
    public function redeemPayment($code) {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET redeemed_code = :redeemed_code, status = 'completed', updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $code = htmlspecialchars(strip_tags($code));
        
        // Bind values
        $stmt->bindParam(":redeemed_code", $code);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->redeemed_code = $code;
            $this->status = 'completed';
            return true;
        }
        
        return false;
    }

    /**
     * Calculate total payments for a contract
     * 
     * @param int $contract_id Contract ID
     * @param string $status Payment status filter (optional)
     * @return float Total amount
     */
    public function calculateTotalForContract($contract_id, $status = 'completed') {
        $query = "SELECT SUM(amount) as total FROM " . $this->table_name . " 
                  WHERE contract_id = :contract_id";
        
        // Filter by status if provided
        if ($status) {
            $query .= " AND status = :status";
        }
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $contract_id = htmlspecialchars(strip_tags($contract_id));
        $stmt->bindParam(":contract_id", $contract_id);
        
        if ($status) {
            $status = htmlspecialchars(strip_tags($status));
            $stmt->bindParam(":status", $status);
        }
        
        // Execute query
        $stmt->execute();
        $row = $stmt->fetch();
        
        return $row['total'] ?? 0;
    }
}
?>
