<?php
/**
 * Chat Model
 * 
 * Handles all database operations related to chat messages
 */
class Chat {
    // Database connection and table name
    private $conn;
    private $table_name = "chats";

    // Object properties
    public $id;
    public $sender_id;
    public $receiver_id;
    public $project_id;
    public $message;
    public $is_read;
    public $attachment;
    public $created_at;
    public $message_type; // 'text', 'file', 'notification'
    public $is_encrypted;
    public $encryption_key;

    /**
     * Constructor with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get chat history between two users for a project
     * 
     * @param int $user1_id First user ID
     * @param int $user2_id Second user ID
     * @param int $project_id Project ID
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of chat messages
     */
    public function getChatHistory($user1_id, $user2_id, $project_id, $limit = 50, $offset = 0) {
        $query = "SELECT c.*, u.username as sender_name, u.profile_image as sender_image 
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.sender_id = u.id
                  WHERE c.project_id = :project_id 
                  AND ((c.sender_id = :user1_id AND c.receiver_id = :user2_id) 
                  OR (c.sender_id = :user2_id AND c.receiver_id = :user1_id)) 
                  ORDER BY c.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $user1_id = htmlspecialchars(strip_tags($user1_id));
        $user2_id = htmlspecialchars(strip_tags($user2_id));
        $project_id = htmlspecialchars(strip_tags($project_id));
        $stmt->bindParam(":user1_id", $user1_id);
        $stmt->bindParam(":user2_id", $user2_id);
        $stmt->bindParam(":project_id", $project_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get unread messages count for a user
     * 
     * @param int $user_id User ID
     * @return int Number of unread messages
     */
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as unread_count FROM " . $this->table_name . " 
                  WHERE receiver_id = :user_id AND is_read = 0";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $user_id = htmlspecialchars(strip_tags($user_id));
        $stmt->bindParam(":user_id", $user_id);
        
        // Execute query
        $stmt->execute();
        $row = $stmt->fetch();
        
        return $row['unread_count'] ?? 0;
    }

    /**
     * Get chat conversations for a user
     * 
     * @param int $user_id User ID
     * @return array List of conversations
     */
    public function getConversations($user_id) {
        $query = "SELECT 
                  c.project_id, 
                  p.title as project_title,
                  CASE 
                      WHEN c.sender_id = :user_id THEN c.receiver_id 
                      ELSE c.sender_id 
                  END as other_user_id,
                  u.username as other_user_name,
                  u.profile_image as other_user_image,
                  MAX(c.created_at) as last_message_time,
                  c.message as last_message,
                  SUM(CASE WHEN c.receiver_id = :user_id AND c.is_read = 0 THEN 1 ELSE 0 END) as unread_count
                  FROM " . $this->table_name . " c
                  JOIN users u ON (CASE WHEN c.sender_id = :user_id THEN c.receiver_id ELSE c.sender_id END) = u.id
                  JOIN projects p ON c.project_id = p.id
                  WHERE c.sender_id = :user_id OR c.receiver_id = :user_id
                  GROUP BY c.project_id, other_user_id
                  ORDER BY last_message_time DESC";
        
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
     * Create new chat message
     * 
     * @return bool Operation result
     */
    public function create() {
        // Query
        $query = "INSERT INTO " . $this->table_name . " 
                  (sender_id, receiver_id, project_id, message, is_read, 
                   attachment, message_type, is_encrypted, encryption_key) 
                  VALUES 
                  (:sender_id, :receiver_id, :project_id, :message, :is_read, 
                   :attachment, :message_type, :is_encrypted, :encryption_key)";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->sender_id = htmlspecialchars(strip_tags($this->sender_id));
        $this->receiver_id = htmlspecialchars(strip_tags($this->receiver_id));
        $this->project_id = htmlspecialchars(strip_tags($this->project_id));
        $this->message = $this->message; // Don't strip tags for message as it might contain formatting
        $this->is_read = $this->is_read ?? 0;
        $this->attachment = $this->attachment ?? null;
        $this->message_type = htmlspecialchars(strip_tags($this->message_type ?? 'text'));
        $this->is_encrypted = $this->is_encrypted ?? 0;
        $this->encryption_key = $this->encryption_key ?? null;
        
        // Bind values
        $stmt->bindParam(":sender_id", $this->sender_id);
        $stmt->bindParam(":receiver_id", $this->receiver_id);
        $stmt->bindParam(":project_id", $this->project_id);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":is_read", $this->is_read);
        $stmt->bindParam(":attachment", $this->attachment);
        $stmt->bindParam(":message_type", $this->message_type);
        $stmt->bindParam(":is_encrypted", $this->is_encrypted);
        $stmt->bindParam(":encryption_key", $this->encryption_key);
        
        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    /**
     * Mark messages as read
     * 
     * @param int $receiver_id Receiver ID
     * @param int $sender_id Sender ID
     * @param int $project_id Project ID
     * @return bool Operation result
     */
    public function markAsRead($receiver_id, $sender_id, $project_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_read = 1 
                  WHERE receiver_id = :receiver_id 
                  AND sender_id = :sender_id 
                  AND project_id = :project_id 
                  AND is_read = 0";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $receiver_id = htmlspecialchars(strip_tags($receiver_id));
        $sender_id = htmlspecialchars(strip_tags($sender_id));
        $project_id = htmlspecialchars(strip_tags($project_id));
        $stmt->bindParam(":receiver_id", $receiver_id);
        $stmt->bindParam(":sender_id", $sender_id);
        $stmt->bindParam(":project_id", $project_id);
        
        // Execute query
        return $stmt->execute();
    }

    /**
     * Get latest messages for a project
     * 
     * @param int $project_id Project ID
     * @param int $limit Limit results
     * @return array List of latest messages
     */
    public function getLatestProjectMessages($project_id, $limit = 10) {
        $query = "SELECT c.*, u.username as sender_name, u.profile_image as sender_image 
                  FROM " . $this->table_name . " c
                  JOIN users u ON c.sender_id = u.id
                  WHERE c.project_id = :project_id 
                  ORDER BY c.created_at DESC 
                  LIMIT :limit";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $project_id = htmlspecialchars(strip_tags($project_id));
        $stmt->bindParam(":project_id", $project_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Add system notification
     * 
     * @param int $receiver_id Receiver ID
     * @param int $project_id Project ID
     * @param string $message Notification message
     * @return bool Operation result
     */
    public function addSystemNotification($receiver_id, $project_id, $message) {
        $this->sender_id = 0; // System message
        $this->receiver_id = $receiver_id;
        $this->project_id = $project_id;
        $this->message = $message;
        $this->message_type = 'notification';
        
        return $this->create();
    }
}
?>
