<?php
/**
 * JWT Utility
 * 
 * Handles JWT token generation and validation
 */
class JWT {
    // Properties
    private $secret;
    private $expiry;
    private $algorithm = 'HS256';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->secret = JWT_SECRET;
        $this->expiry = JWT_EXPIRY;
    }
    
    /**
     * Generate a JWT token
     * 
     * @param array $payload Data to encode in token
     * @return string JWT token
     */
    public function generate($payload) {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];
        
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + $this->expiry; // Expiry time
        
        $header_encoded = $this->base64UrlEncode(json_encode($header));
        $payload_encoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $this->secret, true);
        $signature_encoded = $this->base64UrlEncode($signature);
        
        return "$header_encoded.$payload_encoded.$signature_encoded";
    }
    
    /**
     * Validate a JWT token
     * 
     * @param string $token JWT token to validate
     * @return object Decoded payload
     * @throws Exception if token is invalid
     */
    public function validate($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        // Verify signature
        $signature = $this->base64UrlDecode($signature_encoded);
        $expected_signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $this->secret, true);
        
        if (!hash_equals($signature, $expected_signature)) {
            throw new Exception('Invalid token signature');
        }
        
        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payload_encoded));
        
        // Check if token has expired
        if (isset($payload->exp) && $payload->exp < time()) {
            throw new Exception('Token has expired');
        }
        
        return $payload;
    }
    
    /**
     * Encode data to Base64URL
     * 
     * @param string $data Data to encode
     * @return string Base64URL encoded string
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Decode Base64URL data
     * 
     * @param string $data Base64URL encoded string
     * @return string Decoded data
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Get token from Authorization header
     * 
     * @return string|null Token if found, null otherwise
     */
    public function getTokenFromHeader() {
        $headers = getallheaders();
        $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($auth_header) || strpos($auth_header, 'Bearer ') !== 0) {
            return null;
        }
        
        return trim(substr($auth_header, 7));
    }
    
    /**
     * Refresh a JWT token
     * 
     * @param string $token Original token
     * @return string Refreshed token
     * @throws Exception if token is invalid
     */
    public function refresh($token) {
        $payload = $this->validate($token);
        
        // Remove expiry from payload before generating new token
        unset($payload->exp);
        unset($payload->iat);
        
        // Convert stdClass to array
        $payload_array = json_decode(json_encode($payload), true);
        
        // Generate new token
        return $this->generate($payload_array);
    }
}
?>
