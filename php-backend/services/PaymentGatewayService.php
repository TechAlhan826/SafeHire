<?php
/**
 * Payment Gateway Service
 * 
 * Handles integration with payment gateways (Razorpay and custom UPI)
 */
class PaymentGatewayService {
    // Properties
    private $razorpay_key_id;
    private $razorpay_key_secret;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->razorpay_key_id = RAZORPAY_KEY_ID;
        $this->razorpay_key_secret = RAZORPAY_KEY_SECRET;
    }
    
    /**
     * Initialize a payment
     * 
     * @param float $amount Amount to charge
     * @param string $description Payment description
     * @param string $payment_method Payment method (razorpay, upi)
     * @param array $metadata Additional payment metadata
     * @return array Payment details
     * @throws Exception if payment initialization fails
     */
    public function initializePayment($amount, $description, $payment_method, $metadata = []) {
        // Validate amount
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than zero");
        }
        
        // Choose payment gateway based on method
        switch ($payment_method) {
            case 'razorpay':
                return $this->initializeRazorpayPayment($amount, $description, $metadata);
                
            case 'upi':
                return $this->initializeUpiPayment($amount, $description, $metadata);
                
            default:
                throw new Exception("Unsupported payment method: $payment_method");
        }
    }
    
    /**
     * Initialize a Razorpay payment
     * 
     * @param float $amount Amount to charge
     * @param string $description Payment description
     * @param array $metadata Additional payment metadata
     * @return array Razorpay payment details
     * @throws Exception if payment initialization fails
     */
    private function initializeRazorpayPayment($amount, $description, $metadata = []) {
        if (empty($this->razorpay_key_id) || empty($this->razorpay_key_secret)) {
            throw new Exception("Razorpay API keys not configured");
        }
        
        try {
            // In a real implementation, this would use the Razorpay API to create an order
            // For this demo, we'll return mock data
            
            $receipt_id = 'rcpt_' . uniqid();
            $order_id = 'order_' . uniqid();
            
            // Convert amount to paise (Razorpay uses smallest currency unit)
            $amount_in_paise = $amount * 100;
            
            $payment_data = [
                'id' => $order_id,
                'entity' => 'order',
                'amount' => $amount_in_paise,
                'amount_paid' => 0,
                'amount_due' => $amount_in_paise,
                'currency' => 'INR',
                'receipt' => $receipt_id,
                'status' => 'created',
                'notes' => $metadata,
                'created_at' => time()
            ];
            
            return [
                'gateway' => 'razorpay',
                'order_id' => $order_id,
                'amount' => $amount,
                'currency' => 'INR',
                'description' => $description,
                'key_id' => $this->razorpay_key_id,
                'payment_data' => $payment_data,
                'metadata' => $metadata
            ];
        } catch (Exception $e) {
            throw new Exception("Razorpay payment initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize a UPI payment
     * 
     * @param float $amount Amount to charge
     * @param string $description Payment description
     * @param array $metadata Additional payment metadata
     * @return array UPI payment details
     * @throws Exception if payment initialization fails
     */
    private function initializeUpiPayment($amount, $description, $metadata = []) {
        try {
            // In a real implementation, this would use a UPI gateway API
            // For this demo, we'll generate a mock UPI payment link
            
            $transaction_id = 'upi_' . uniqid();
            $expiry_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Generate a mock UPI ID if provided in metadata, otherwise use a default
            $upi_id = isset($metadata['upi_id']) ? $metadata['upi_id'] : 'payment@safehire';
            
            // Generate mock UPI payment URL
            $upi_url = "upi://pay?pa=$upi_id&pn=SafeHire&am=$amount&tr=$transaction_id&tn=" . urlencode($description);
            
            return [
                'gateway' => 'upi',
                'transaction_id' => $transaction_id,
                'amount' => $amount,
                'currency' => 'INR',
                'description' => $description,
                'upi_id' => $upi_id,
                'upi_url' => $upi_url,
                'qr_code_url' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($upi_url),
                'expiry_time' => $expiry_time,
                'metadata' => $metadata
            ];
        } catch (Exception $e) {
            throw new Exception("UPI payment initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Verify a payment
     * 
     * @param array $payment_data Payment data from callback
     * @return bool True if payment is verified, false otherwise
     * @throws Exception if verification fails
     */
    public function verifyPayment($payment_data) {
        if (!isset($payment_data['gateway'])) {
            throw new Exception("Unknown payment gateway");
        }
        
        switch ($payment_data['gateway']) {
            case 'razorpay':
                return $this->verifyRazorpayPayment($payment_data);
                
            case 'upi':
                return $this->verifyUpiPayment($payment_data);
                
            default:
                throw new Exception("Unsupported payment gateway: " . $payment_data['gateway']);
        }
    }
    
    /**
     * Verify a Razorpay payment
     * 
     * @param array $payment_data Payment data from callback
     * @return bool True if payment is verified, false otherwise
     * @throws Exception if verification fails
     */
    private function verifyRazorpayPayment($payment_data) {
        // In a real implementation, this would verify the payment with Razorpay API
        // For this demo, we'll assume the verification is successful
        
        if (!isset($payment_data['razorpay_payment_id']) || !isset($payment_data['razorpay_order_id']) || !isset($payment_data['razorpay_signature'])) {
            // If we're in demo mode with missing parameters, still return true
            if (isset($payment_data['demo_mode']) && $payment_data['demo_mode']) {
                return true;
            }
            
            throw new Exception("Missing required Razorpay parameters");
        }
        
        // In a real implementation, we would validate the signature here
        // $expected_signature = hash_hmac('sha256', $payment_data['razorpay_order_id'] . '|' . $payment_data['razorpay_payment_id'], $this->razorpay_key_secret);
        // return hash_equals($expected_signature, $payment_data['razorpay_signature']);
        
        return true;
    }
    
    /**
     * Verify a UPI payment
     * 
     * @param array $payment_data Payment data from callback
     * @return bool True if payment is verified, false otherwise
     * @throws Exception if verification fails
     */
    private function verifyUpiPayment($payment_data) {
        // In a real implementation, this would verify the UPI payment with the gateway
        // For this demo, we'll assume the verification is successful
        
        if (!isset($payment_data['transaction_id']) || !isset($payment_data['status'])) {
            // If we're in demo mode with missing parameters, still return true
            if (isset($payment_data['demo_mode']) && $payment_data['demo_mode']) {
                return true;
            }
            
            throw new Exception("Missing required UPI parameters");
        }
        
        // Check the status
        return ($payment_data['status'] === 'SUCCESS');
    }
    
    /**
     * Refund a payment
     * 
     * @param string $payment_id Payment ID to refund
     * @param float $amount Amount to refund (optional, defaults to full amount)
     * @param string $reason Reason for refund
     * @return array Refund details
     * @throws Exception if refund fails
     */
    public function refundPayment($payment_id, $amount = null, $reason = '') {
        // Determine the payment gateway based on payment ID prefix
        $gateway = '';
        
        if (strpos($payment_id, 'rzp_') === 0) {
            $gateway = 'razorpay';
        } elseif (strpos($payment_id, 'upi_') === 0) {
            $gateway = 'upi';
        } else {
            throw new Exception("Unknown payment gateway for payment ID: $payment_id");
        }
        
        switch ($gateway) {
            case 'razorpay':
                return $this->refundRazorpayPayment($payment_id, $amount, $reason);
                
            case 'upi':
                return $this->refundUpiPayment($payment_id, $amount, $reason);
                
            default:
                throw new Exception("Unsupported payment gateway: $gateway");
        }
    }
    
    /**
     * Refund a Razorpay payment
     * 
     * @param string $payment_id Razorpay payment ID to refund
     * @param float $amount Amount to refund (optional, defaults to full amount)
     * @param string $reason Reason for refund
     * @return array Refund details
     * @throws Exception if refund fails
     */
    private function refundRazorpayPayment($payment_id, $amount = null, $reason = '') {
        // In a real implementation, this would use the Razorpay API to create a refund
        // For this demo, we'll return mock data
        
        try {
            $refund_id = 'rfnd_' . uniqid();
            
            return [
                'gateway' => 'razorpay',
                'id' => $refund_id,
                'payment_id' => $payment_id,
                'amount' => $amount,
                'status' => 'processed',
                'reason' => $reason,
                'created_at' => time()
            ];
        } catch (Exception $e) {
            throw new Exception("Razorpay refund failed: " . $e->getMessage());
        }
    }
    
    /**
     * Refund a UPI payment
     * 
     * @param string $payment_id UPI payment ID to refund
     * @param float $amount Amount to refund (optional, defaults to full amount)
     * @param string $reason Reason for refund
     * @return array Refund details
     * @throws Exception if refund fails
     */
    private function refundUpiPayment($payment_id, $amount = null, $reason = '') {
        // In a real implementation, this would use a UPI gateway API to create a refund
        // For this demo, we'll return mock data
        
        try {
            $refund_id = 'upi_rfnd_' . uniqid();
            
            return [
                'gateway' => 'upi',
                'id' => $refund_id,
                'payment_id' => $payment_id,
                'amount' => $amount,
                'status' => 'processed',
                'reason' => $reason,
                'created_at' => time()
            ];
        } catch (Exception $e) {
            throw new Exception("UPI refund failed: " . $e->getMessage());
        }
    }
}
?>
