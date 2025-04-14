<?php
class RedeemCodeGenerator {
    private $db;
    private $codeLength;
    private $prefix;
    
    public function __construct($db, $codeLength = 8, $prefix = 'SH') {
        $this->db = $db;
        $this->codeLength = $codeLength;
        $this->prefix = $prefix;
    }
    
    /**
     * Generate a unique redeem code for a project milestone
     * @param int $projectId Project ID
     * @param int $milestoneId Milestone ID
     * @return string Generated redeem code
     */
    public function generateMilestoneCode($projectId, $milestoneId) {
        // Create a base code with project and milestone IDs encoded
        $baseCode = $this->encodeIds($projectId, $milestoneId);
        
        // Add randomness
        $randomComponent = $this->generateRandomString(4);
        
        // Combine with prefix
        $code = $this->prefix . '-' . $baseCode . '-' . $randomComponent;
        
        // Check if code exists and regenerate if needed
        while ($this->codeExists($code)) {
            $randomComponent = $this->generateRandomString(4);
            $code = $this->prefix . '-' . $baseCode . '-' . $randomComponent;
        }
        
        return $code;
    }
    
    /**
     * Encode project and milestone IDs into a short string
     * @param int $projectId Project ID
     * @param int $milestoneId Milestone ID
     * @return string Encoded string
     */
    private function encodeIds($projectId, $milestoneId) {
        // Combine IDs into a single number
        $combined = ($projectId * 1000) + $milestoneId;
        
        // Convert to base 36 (0-9, a-z) for a shorter representation
        return base_convert($combined, 10, 36);
    }
    
    /**
     * Generate a random alphanumeric string
     * @param int $length Length of string to generate
     * @return string Random string
     */
    private function generateRandomString($length) {
        $characters = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Removed similar-looking characters I, O
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Check if a code already exists in the database
     * @param string $code Code to check
     * @return bool True if code exists, false otherwise
     */
    private function codeExists($code) {
        $query = "SELECT COUNT(*) as count FROM milestones WHERE redeem_code = :code";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] > 0);
    }
    
    /**
     * Decode a redeem code to get project and milestone IDs
     * @param string $code Code to decode
     * @return array|bool Array with project_id and milestone_id if valid, false otherwise
     */
    public function decodeCode($code) {
        // Check format
        if (!preg_match('/^' . $this->prefix . '-([A-Z0-9]+)-([A-Z0-9]+)$/', $code, $matches)) {
            return false;
        }
        
        $encodedIds = $matches[1];
        
        // Convert from base 36 back to decimal
        $combined = intval(base_convert($encodedIds, 36, 10));
        
        // Extract project and milestone IDs
        $projectId = floor($combined / 1000);
        $milestoneId = $combined % 1000;
        
        return [
            'project_id' => $projectId,
            'milestone_id' => $milestoneId
        ];
    }
    
    /**
     * Validate a redeem code against a specific milestone
     * @param string $code Code to validate
     * @param int $projectId Expected project ID
     * @param int $milestoneId Expected milestone ID
     * @return bool True if valid, false otherwise
     */
    public function validateCode($code, $projectId, $milestoneId) {
        $decoded = $this->decodeCode($code);
        
        if (!$decoded) {
            return false;
        }
        
        return ($decoded['project_id'] == $projectId && $decoded['milestone_id'] == $milestoneId);
    }
}
