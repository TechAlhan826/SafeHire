<?php
/**
 * Bid Controller
 * 
 * Handles bid submission, management, and acceptance
 */
class BidController {
    // Database connection and models
    private $conn;
    private $bid;
    private $project;
    private $user;
    private $contract;
    private $chat;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize models
        require_once __DIR__ . '/../models/Bid.php';
        require_once __DIR__ . '/../models/Project.php';
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/Contract.php';
        require_once __DIR__ . '/../models/Chat.php';
        
        $this->bid = new Bid($db);
        $this->project = new Project($db);
        $this->user = new User($db);
        $this->contract = new Contract($db);
        $this->chat = new Chat($db);
    }
    
    /**
     * Get bids for a project
     * 
     * @param int $project_id Project ID
     * @param string $status Bid status filter
     * @return array Response data
     */
    public function getProjectBids($project_id, $status = null) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($project_id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Get bids
        $bids = $this->bid->getByProjectId($project_id, $status);
        
        return [
            'status' => 'success',
            'data' => $bids,
            'code' => 200
        ];
    }
    
    /**
     * Get bids by freelancer
     * 
     * @param int $freelancer_id Freelancer ID
     * @param string $status Bid status filter
     * @return array Response data
     */
    public function getFreelancerBids($freelancer_id, $status = null) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user by ID
        if (!$this->user->getById($freelancer_id)) {
            return [
                'status' => 'error',
                'message' => 'User not found',
                'code' => 404
            ];
        }
        
        // Check if user is a freelancer
        if ($this->user->user_type !== 'freelancer') {
            return [
                'status' => 'error',
                'message' => 'User is not a freelancer',
                'code' => 400
            ];
        }
        
        // Get bids
        $bids = $this->bid->getByFreelancerId($freelancer_id, $status);
        
        return [
            'status' => 'success',
            'data' => $bids,
            'code' => 200
        ];
    }
    
    /**
     * Submit a bid
     * 
     * @param int $freelancer_id Freelancer ID
     * @return array Response data
     */
    public function submitBid($freelancer_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get user by ID
        if (!$this->user->getById($freelancer_id)) {
            return [
                'status' => 'error',
                'message' => 'User not found',
                'code' => 404
            ];
        }
        
        // Check if user is a freelancer
        if ($this->user->user_type !== 'freelancer') {
            return [
                'status' => 'error',
                'message' => 'Only freelancers can submit bids',
                'code' => 400
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        $required_fields = ['project_id', 'amount', 'proposal', 'delivery_time'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return [
                    'status' => 'error',
                    'message' => ucfirst($field) . ' is required',
                    'code' => 400
                ];
            }
        }
        
        // Get project by ID
        if (!$this->project->getById($data['project_id'])) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Check if project is open
        if ($this->project->status !== 'open') {
            return [
                'status' => 'error',
                'message' => 'Project is not open for bidding',
                'code' => 400
            ];
        }
        
        // Check if freelancer has already bid on this project
        if ($this->bid->checkExistingBid($data['project_id'], $freelancer_id)) {
            return [
                'status' => 'error',
                'message' => 'You have already bid on this project',
                'code' => 409
            ];
        }
        
        // Set bid properties
        $this->bid->project_id = $data['project_id'];
        $this->bid->freelancer_id = $freelancer_id;
        $this->bid->amount = $data['amount'];
        $this->bid->proposal = $data['proposal'];
        $this->bid->delivery_time = $data['delivery_time'];
        $this->bid->team_id = $data['team_id'] ?? null;
        $this->bid->is_team_bid = $data['is_team_bid'] ?? 0;
        
        // Create the bid
        if ($this->bid->create()) {
            // Notify project owner
            $this->chat->addSystemNotification(
                $this->project->client_id, 
                $data['project_id'], 
                "A new bid has been submitted for your project by " . $this->user->username
            );
            
            return [
                'status' => 'success',
                'message' => 'Bid submitted successfully',
                'data' => [
                    'id' => $this->bid->id,
                    'project_id' => $this->bid->project_id,
                    'amount' => $this->bid->amount,
                    'proposal' => $this->bid->proposal,
                    'delivery_time' => $this->bid->delivery_time
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to submit bid',
                'code' => 500
            ];
        }
    }
    
    /**
     * Update a bid
     * 
     * @param int $id Bid ID
     * @param int $freelancer_id Freelancer ID
     * @return array Response data
     */
    public function updateBid($id, $freelancer_id) {
        // Check if request method is PUT
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get bid by ID
        if (!$this->bid->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Bid not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->bid->freelancer_id != $freelancer_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to update this bid',
                'code' => 403
            ];
        }
        
        // Check if bid can be updated
        if ($this->bid->status !== 'pending') {
            return [
                'status' => 'error',
                'message' => 'Bid cannot be updated in its current status',
                'code' => 400
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Set updatable fields
        if (isset($data['amount'])) $this->bid->amount = $data['amount'];
        if (isset($data['proposal'])) $this->bid->proposal = $data['proposal'];
        if (isset($data['delivery_time'])) $this->bid->delivery_time = $data['delivery_time'];
        
        // Update the bid
        if ($this->bid->update()) {
            return [
                'status' => 'success',
                'message' => 'Bid updated successfully',
                'data' => [
                    'id' => $this->bid->id,
                    'amount' => $this->bid->amount,
                    'proposal' => $this->bid->proposal,
                    'delivery_time' => $this->bid->delivery_time
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to update bid',
                'code' => 500
            ];
        }
    }
    
    /**
     * Delete a bid
     * 
     * @param int $id Bid ID
     * @param int $freelancer_id Freelancer ID
     * @return array Response data
     */
    public function deleteBid($id, $freelancer_id) {
        // Check if request method is DELETE
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get bid by ID
        if (!$this->bid->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Bid not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->bid->freelancer_id != $freelancer_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to delete this bid',
                'code' => 403
            ];
        }
        
        // Check if bid can be deleted
        if ($this->bid->status !== 'pending') {
            return [
                'status' => 'error',
                'message' => 'Bid cannot be deleted in its current status',
                'code' => 400
            ];
        }
        
        // Delete the bid
        if ($this->bid->delete()) {
            return [
                'status' => 'success',
                'message' => 'Bid deleted successfully',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to delete bid',
                'code' => 500
            ];
        }
    }
    
    /**
     * Accept a bid and create a contract
     * 
     * @param int $id Bid ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function acceptBid($id, $client_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get bid by ID
        if (!$this->bid->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Bid not found',
                'code' => 404
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($this->bid->project_id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Verify ownership of project
        if ($this->project->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to accept bids for this project',
                'code' => 403
            ];
        }
        
        // Check if project is open
        if ($this->project->status !== 'open') {
            return [
                'status' => 'error',
                'message' => 'Project is not open for bidding',
                'code' => 400
            ];
        }
        
        // Check if bid has already been accepted
        if ($this->bid->status !== 'pending') {
            return [
                'status' => 'error',
                'message' => 'Bid has already been processed',
                'code' => 400
            ];
        }
        
        // Begin transaction
        $this->conn->beginTransaction();
        
        try {
            // Update bid status
            $this->bid->updateStatus('accepted');
            
            // Update project status
            $this->project->updateStatus('in_progress');
            
            // Create contract
            $this->contract->project_id = $this->bid->project_id;
            $this->contract->client_id = $client_id;
            $this->contract->freelancer_id = $this->bid->freelancer_id;
            $this->contract->bid_id = $this->bid->id;
            $this->contract->amount = $this->bid->amount;
            $this->contract->start_date = date('Y-m-d');
            $this->contract->team_id = $this->bid->team_id;
            
            if (!$this->contract->create()) {
                throw new Exception('Failed to create contract');
            }
            
            // Reject all other bids for this project
            $other_bids = $this->bid->getByProjectId($this->bid->project_id, 'pending');
            foreach ($other_bids as $other_bid) {
                if ($other_bid['id'] != $this->bid->id) {
                    $reject_bid = new Bid($this->conn);
                    $reject_bid->getById($other_bid['id']);
                    $reject_bid->updateStatus('rejected');
                }
            }
            
            // Notify freelancer
            $this->chat->addSystemNotification(
                $this->bid->freelancer_id, 
                $this->bid->project_id, 
                "Your bid has been accepted! A contract has been created."
            );
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'status' => 'success',
                'message' => 'Bid accepted and contract created successfully',
                'data' => [
                    'contract_id' => $this->contract->id,
                    'completion_code' => $this->contract->completion_code,
                    'milestone_codes' => json_decode($this->contract->milestone_codes, true)
                ],
                'code' => 200
            ];
        } catch (Exception $e) {
            // Roll back transaction on error
            $this->conn->rollBack();
            
            return [
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Reject a bid
     * 
     * @param int $id Bid ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function rejectBid($id, $client_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get bid by ID
        if (!$this->bid->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Bid not found',
                'code' => 404
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($this->bid->project_id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Verify ownership of project
        if ($this->project->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to reject bids for this project',
                'code' => 403
            ];
        }
        
        // Check if bid has already been processed
        if ($this->bid->status !== 'pending') {
            return [
                'status' => 'error',
                'message' => 'Bid has already been processed',
                'code' => 400
            ];
        }
        
        // Update bid status
        if ($this->bid->updateStatus('rejected')) {
            // Notify freelancer
            $this->chat->addSystemNotification(
                $this->bid->freelancer_id, 
                $this->bid->project_id, 
                "Your bid has been rejected."
            );
            
            return [
                'status' => 'success',
                'message' => 'Bid rejected successfully',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to reject bid',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get accepted bid for a project
     * 
     * @param int $project_id Project ID
     * @return array Response data
     */
    public function getAcceptedBid($project_id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($project_id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Get accepted bid
        if (!$this->bid->getAcceptedBid($project_id)) {
            return [
                'status' => 'error',
                'message' => 'No accepted bid found for this project',
                'code' => 404
            ];
        }
        
        // Get freelancer details
        $this->user->getById($this->bid->freelancer_id);
        
        return [
            'status' => 'success',
            'data' => [
                'id' => $this->bid->id,
                'project_id' => $this->bid->project_id,
                'freelancer_id' => $this->bid->freelancer_id,
                'freelancer_name' => $this->user->username,
                'amount' => $this->bid->amount,
                'proposal' => $this->bid->proposal,
                'delivery_time' => $this->bid->delivery_time,
                'status' => $this->bid->status,
                'created_at' => $this->bid->created_at,
                'is_team_bid' => $this->bid->is_team_bid,
                'team_id' => $this->bid->team_id
            ],
            'code' => 200
        ];
    }
}
?>
