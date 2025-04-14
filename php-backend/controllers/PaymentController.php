<?php
/**
 * Payment Controller
 * 
 * Handles payment processing, escrow, and milestone payments
 */
class PaymentController {
    // Database connection and models
    private $conn;
    private $payment;
    private $contract;
    private $project;
    private $user;
    private $payment_gateway;
    private $chat;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize models
        require_once __DIR__ . '/../models/Payment.php';
        require_once __DIR__ . '/../models/Contract.php';
        require_once __DIR__ . '/../models/Project.php';
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/Chat.php';
        require_once __DIR__ . '/../services/PaymentGatewayService.php';
        
        $this->payment = new Payment($db);
        $this->contract = new Contract($db);
        $this->project = new Project($db);
        $this->user = new User($db);
        $this->chat = new Chat($db);
        $this->payment_gateway = new PaymentGatewayService();
    }
    
    /**
     * Get payments by user ID
     * 
     * @param int $user_id User ID
     * @param string $role Role ('payer' or 'payee')
     * @return array Response data
     */
    public function getUserPayments($user_id, $role = 'payer') {
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
        
        // Get payments
        $payments = $this->payment->getByUserId($user_id, $role);
        
        return [
            'status' => 'success',
            'data' => $payments,
            'code' => 200
        ];
    }
    
    /**
     * Get payments by contract ID
     * 
     * @param int $contract_id Contract ID
     * @return array Response data
     */
    public function getContractPayments($contract_id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get contract by ID
        if (!$this->contract->getById($contract_id)) {
            return [
                'status' => 'error',
                'message' => 'Contract not found',
                'code' => 404
            ];
        }
        
        // Get payments
        $payments = $this->payment->getByContractId($contract_id);
        
        return [
            'status' => 'success',
            'data' => $payments,
            'code' => 200
        ];
    }
    
    /**
     * Create initial escrow payment (25% advance)
     * 
     * @param int $contract_id Contract ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function createAdvancePayment($contract_id, $client_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get contract by ID
        if (!$this->contract->getById($contract_id)) {
            return [
                'status' => 'error',
                'message' => 'Contract not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->contract->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to make payments for this contract',
                'code' => 403
            ];
        }
        
        // Check if contract is active
        if ($this->contract->status !== 'active') {
            return [
                'status' => 'error',
                'message' => 'Contract is not active',
                'code' => 400
            ];
        }
        
        // Check if advance payment already exists
        $existing_payments = $this->payment->getByContractId($contract_id);
        foreach ($existing_payments as $existing_payment) {
            if ($existing_payment['payment_type'] === 'advance') {
                return [
                    'status' => 'error',
                    'message' => 'Advance payment already exists for this contract',
                    'code' => 409
                ];
            }
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate payment method
        if (empty($data['payment_method'])) {
            return [
                'status' => 'error',
                'message' => 'Payment method is required',
                'code' => 400
            ];
        }
        
        // Calculate advance amount (25% of contract amount)
        $advance_amount = $this->contract->amount * 0.25;
        
        // Initialize payment gateway
        try {
            $payment_info = $this->payment_gateway->initializePayment(
                $advance_amount,
                'Advance payment for contract #' . $contract_id,
                $data['payment_method'],
                $data
            );
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Payment initialization failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
        
        // Create payment record
        $this->payment->contract_id = $contract_id;
        $this->payment->amount = $advance_amount;
        $this->payment->payment_type = 'advance';
        $this->payment->payment_method = $data['payment_method'];
        $this->payment->status = 'pending';
        $this->payment->payer_id = $client_id;
        $this->payment->payee_id = $this->contract->freelancer_id;
        $this->payment->payment_details = json_encode($payment_info);
        
        if ($this->payment->create()) {
            return [
                'status' => 'success',
                'message' => 'Advance payment initialized',
                'data' => [
                    'payment_id' => $this->payment->id,
                    'amount' => $advance_amount,
                    'payment_info' => $payment_info
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to create payment record',
                'code' => 500
            ];
        }
    }
    
    /**
     * Process payment webhook/callback
     * 
     * @return array Response data
     */
    public function processPaymentCallback() {
        // Get callback data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Verify the payment with the gateway
        try {
            $verified = $this->payment_gateway->verifyPayment($data);
            
            if (!$verified) {
                return [
                    'status' => 'error',
                    'message' => 'Payment verification failed',
                    'code' => 400
                ];
            }
            
            // Get payment by transaction ID
            // In a real implementation, you'd lookup the payment by transaction ID
            // For this demo, we'll assume the payment_id is passed in the callback
            if (empty($data['payment_id'])) {
                return [
                    'status' => 'error',
                    'message' => 'Payment ID not found in callback data',
                    'code' => 400
                ];
            }
            
            if (!$this->payment->getById($data['payment_id'])) {
                return [
                    'status' => 'error',
                    'message' => 'Payment not found',
                    'code' => 404
                ];
            }
            
            // Update payment status
            $this->payment->updateStatus('completed', $data['transaction_id'] ?? null);
            
            // Update payment details
            $this->payment->updateDetails($data);
            
            // Update contract payment status if it's an advance payment
            if ($this->payment->payment_type === 'advance') {
                $this->contract->getById($this->payment->contract_id);
                $this->contract->updatePaymentStatus('partial');
            }
            
            // Check if all payments are complete
            $total_paid = $this->payment->calculateTotalForContract($this->payment->contract_id, 'completed');
            if ($total_paid >= $this->contract->amount) {
                $this->contract->updatePaymentStatus('completed');
            }
            
            // Notify freelancer
            $this->chat->addSystemNotification(
                $this->payment->payee_id, 
                $this->contract->project_id, 
                "A payment of $" . $this->payment->amount . " has been completed for your contract."
            );
            
            return [
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'code' => 200
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Payment processing failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Create a milestone payment
     * 
     * @param int $contract_id Contract ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function createMilestonePayment($contract_id, $client_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get contract by ID
        if (!$this->contract->getById($contract_id)) {
            return [
                'status' => 'error',
                'message' => 'Contract not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->contract->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to make payments for this contract',
                'code' => 403
            ];
        }
        
        // Check if contract is active
        if ($this->contract->status !== 'active') {
            return [
                'status' => 'error',
                'message' => 'Contract is not active',
                'code' => 400
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        if (empty($data['amount']) || empty($data['milestone_id']) || empty($data['payment_method'])) {
            return [
                'status' => 'error',
                'message' => 'Amount, milestone ID, and payment method are required',
                'code' => 400
            ];
        }
        
        // Check if amount is valid
        if ($data['amount'] <= 0 || !is_numeric($data['amount'])) {
            return [
                'status' => 'error',
                'message' => 'Invalid amount',
                'code' => 400
            ];
        }
        
        // Initialize payment gateway
        try {
            $payment_info = $this->payment_gateway->initializePayment(
                $data['amount'],
                'Milestone payment for contract #' . $contract_id,
                $data['payment_method'],
                $data
            );
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Payment initialization failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
        
        // Create payment record
        $this->payment->contract_id = $contract_id;
        $this->payment->amount = $data['amount'];
        $this->payment->payment_type = 'milestone';
        $this->payment->payment_method = $data['payment_method'];
        $this->payment->status = 'pending';
        $this->payment->payer_id = $client_id;
        $this->payment->payee_id = $this->contract->freelancer_id;
        $this->payment->milestone_id = $data['milestone_id'];
        $this->payment->payment_details = json_encode($payment_info);
        
        if ($this->payment->create()) {
            return [
                'status' => 'success',
                'message' => 'Milestone payment initialized',
                'data' => [
                    'payment_id' => $this->payment->id,
                    'amount' => $data['amount'],
                    'payment_info' => $payment_info
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to create payment record',
                'code' => 500
            ];
        }
    }
    
    /**
     * Redeem payment using milestone or completion code
     * 
     * @param int $payment_id Payment ID
     * @param int $freelancer_id Freelancer ID
     * @return array Response data
     */
    public function redeemPayment($payment_id, $freelancer_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get payment by ID
        if (!$this->payment->getById($payment_id)) {
            return [
                'status' => 'error',
                'message' => 'Payment not found',
                'code' => 404
            ];
        }
        
        // Verify recipient
        if ($this->payment->payee_id != $freelancer_id) {
            return [
                'status' => 'error',
                'message' => 'You are not the intended recipient of this payment',
                'code' => 403
            ];
        }
        
        // Check if payment is already redeemed
        if ($this->payment->status === 'completed') {
            return [
                'status' => 'error',
                'message' => 'Payment has already been redeemed',
                'code' => 400
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate code
        if (empty($data['code'])) {
            return [
                'status' => 'error',
                'message' => 'Redemption code is required',
                'code' => 400
            ];
        }
        
        // Get contract
        if (!$this->contract->getById($this->payment->contract_id)) {
            return [
                'status' => 'error',
                'message' => 'Contract not found',
                'code' => 404
            ];
        }
        
        // Verify code
        $code_valid = false;
        
        if ($this->payment->payment_type === 'milestone' && $this->payment->milestone_id) {
            // Verify milestone code
            $milestone_name = 'milestone' . $this->payment->milestone_id;
            $code_valid = $this->contract->validateMilestoneCode($milestone_name, $data['code']);
        } elseif ($this->payment->payment_type === 'final') {
            // Verify completion code
            $code_valid = $this->contract->validateCompletionCode($data['code']);
        }
        
        if (!$code_valid) {
            return [
                'status' => 'error',
                'message' => 'Invalid redemption code',
                'code' => 400
            ];
        }
        
        // Redeem payment
        if ($this->payment->redeemPayment($data['code'])) {
            // Notify client
            $this->chat->addSystemNotification(
                $this->payment->payer_id, 
                $this->contract->project_id, 
                "A payment of $" . $this->payment->amount . " has been redeemed by the freelancer."
            );
            
            return [
                'status' => 'success',
                'message' => 'Payment redeemed successfully',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to redeem payment',
                'code' => 500
            ];
        }
    }
    
    /**
     * Create final payment
     * 
     * @param int $contract_id Contract ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function createFinalPayment($contract_id, $client_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get contract by ID
        if (!$this->contract->getById($contract_id)) {
            return [
                'status' => 'error',
                'message' => 'Contract not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->contract->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to make payments for this contract',
                'code' => 403
            ];
        }
        
        // Check if contract is active
        if ($this->contract->status !== 'active') {
            return [
                'status' => 'error',
                'message' => 'Contract is not active',
                'code' => 400
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate payment method
        if (empty($data['payment_method'])) {
            return [
                'status' => 'error',
                'message' => 'Payment method is required',
                'code' => 400
            ];
        }
        
        // Calculate remaining amount
        $total_paid = $this->payment->calculateTotalForContract($contract_id, 'completed');
        $remaining_amount = $this->contract->amount - $total_paid;
        
        if ($remaining_amount <= 0) {
            return [
                'status' => 'error',
                'message' => 'Contract is already paid in full',
                'code' => 400
            ];
        }
        
        // Initialize payment gateway
        try {
            $payment_info = $this->payment_gateway->initializePayment(
                $remaining_amount,
                'Final payment for contract #' . $contract_id,
                $data['payment_method'],
                $data
            );
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Payment initialization failed: ' . $e->getMessage(),
                'code' => 500
            ];
        }
        
        // Create payment record
        $this->payment->contract_id = $contract_id;
        $this->payment->amount = $remaining_amount;
        $this->payment->payment_type = 'final';
        $this->payment->payment_method = $data['payment_method'];
        $this->payment->status = 'pending';
        $this->payment->payer_id = $client_id;
        $this->payment->payee_id = $this->contract->freelancer_id;
        $this->payment->payment_details = json_encode($payment_info);
        
        if ($this->payment->create()) {
            return [
                'status' => 'success',
                'message' => 'Final payment initialized',
                'data' => [
                    'payment_id' => $this->payment->id,
                    'amount' => $remaining_amount,
                    'payment_info' => $payment_info
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to create payment record',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get payment details
     * 
     * @param int $id Payment ID
     * @return array Response data
     */
    public function getPaymentById($id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get payment by ID
        if (!$this->payment->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Payment not found',
                'code' => 404
            ];
        }
        
        return [
            'status' => 'success',
            'data' => [
                'id' => $this->payment->id,
                'contract_id' => $this->payment->contract_id,
                'amount' => $this->payment->amount,
                'payment_type' => $this->payment->payment_type,
                'payment_method' => $this->payment->payment_method,
                'transaction_id' => $this->payment->transaction_id,
                'status' => $this->payment->status,
                'created_at' => $this->payment->created_at,
                'updated_at' => $this->payment->updated_at,
                'payer_id' => $this->payment->payer_id,
                'payee_id' => $this->payment->payee_id,
                'milestone_id' => $this->payment->milestone_id,
                'payment_details' => json_decode($this->payment->payment_details, true),
                'redeemed_code' => $this->payment->redeemed_code
            ],
            'code' => 200
        ];
    }
}
?>
