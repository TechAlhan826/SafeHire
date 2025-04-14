<?php
/**
 * Chat Controller
 * 
 * Handles chat messages and communication between users
 */
class ChatController {
    // Database connection and models
    private $conn;
    private $chat;
    private $user;
    private $project;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize models
        require_once __DIR__ . '/../models/Chat.php';
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/Project.php';
        
        $this->chat = new Chat($db);
        $this->user = new User($db);
        $this->project = new Project($db);
    }
    
    /**
     * Get chat history between two users for a project
     * 
     * @param int $user_id Current user ID
     * @param int $other_user_id Other user ID
     * @param int $project_id Project ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Response data
     */
    public function getChatHistory($user_id, $other_user_id, $project_id, $page = 1, $limit = 50) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Get chat history
        $messages = $this->chat->getChatHistory($user_id, $other_user_id, $project_id, $limit, $offset);
        
        // Mark messages as read
        $this->chat->markAsRead($user_id, $other_user_id, $project_id);
        
        return [
            'status' => 'success',
            'data' => $messages,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($messages) // Note: would use a separate count query in production
            ],
            'code' => 200
        ];
    }
    
    /**
     * Send a new message
     * 
     * @param int $sender_id Sender ID
     * @return array Response data
     */
    public function sendMessage($sender_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        $required_fields = ['receiver_id', 'project_id', 'message'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return [
                    'status' => 'error',
                    'message' => ucfirst($field) . ' is required',
                    'code' => 400
                ];
            }
        }
        
        // Check if sender exists
        if (!$this->user->getById($sender_id)) {
            return [
                'status' => 'error',
                'message' => 'Sender not found',
                'code' => 404
            ];
        }
        
        // Check if receiver exists
        if (!$this->user->getById($data['receiver_id'])) {
            return [
                'status' => 'error',
                'message' => 'Receiver not found',
                'code' => 404
            ];
        }
        
        // Check if project exists
        if (!$this->project->getById($data['project_id'])) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Set chat properties
        $this->chat->sender_id = $sender_id;
        $this->chat->receiver_id = $data['receiver_id'];
        $this->chat->project_id = $data['project_id'];
        $this->chat->message = $data['message'];
        $this->chat->message_type = $data['message_type'] ?? 'text';
        $this->chat->is_encrypted = $data['is_encrypted'] ?? 0;
        $this->chat->encryption_key = $data['encryption_key'] ?? null;
        
        // Handle file upload (simplified)
        if (isset($_FILES['attachment'])) {
            $target_dir = __DIR__ . "/../uploads/chat/";
            $file_name = uniqid() . "_" . basename($_FILES["attachment"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                $this->chat->attachment = "/uploads/chat/" . $file_name;
                $this->chat->message_type = 'file';
            }
        }
        
        // Send the message
        if ($this->chat->create()) {
            return [
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $this->chat->id,
                    'sender_id' => $this->chat->sender_id,
                    'receiver_id' => $this->chat->receiver_id,
                    'project_id' => $this->chat->project_id,
                    'message' => $this->chat->message,
                    'message_type' => $this->chat->message_type,
                    'attachment' => $this->chat->attachment,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to send message',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get unread messages count
     * 
     * @param int $user_id User ID
     * @return array Response data
     */
    public function getUnreadCount($user_id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get unread count
        $count = $this->chat->getUnreadCount($user_id);
        
        return [
            'status' => 'success',
            'data' => [
                'unread_count' => $count
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get user's conversations
     * 
     * @param int $user_id User ID
     * @return array Response data
     */
    public function getConversations($user_id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get conversations
        $conversations = $this->chat->getConversations($user_id);
        
        return [
            'status' => 'success',
            'data' => $conversations,
            'code' => 200
        ];
    }
    
    /**
     * Mark messages as read
     * 
     * @param int $user_id User ID
     * @param int $sender_id Sender ID
     * @param int $project_id Project ID
     * @return array Response data
     */
    public function markAsRead($user_id, $sender_id, $project_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Mark messages as read
        if ($this->chat->markAsRead($user_id, $sender_id, $project_id)) {
            return [
                'status' => 'success',
                'message' => 'Messages marked as read',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to mark messages as read',
                'code' => 500
            ];
        }
    }
    
    /**
     * Send a system notification
     * 
     * @param int $receiver_id Receiver ID
     * @param int $project_id Project ID
     * @return array Response data
     */
    public function sendSystemNotification($receiver_id, $project_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        if (empty($data['message'])) {
            return [
                'status' => 'error',
                'message' => 'Message is required',
                'code' => 400
            ];
        }
        
        // Check if receiver exists
        if (!$this->user->getById($receiver_id)) {
            return [
                'status' => 'error',
                'message' => 'Receiver not found',
                'code' => 404
            ];
        }
        
        // Check if project exists
        if (!$this->project->getById($project_id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Send notification
        if ($this->chat->addSystemNotification($receiver_id, $project_id, $data['message'])) {
            return [
                'status' => 'success',
                'message' => 'Notification sent successfully',
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to send notification',
                'code' => 500
            ];
        }
    }
    
    /**
     * Get latest messages for a project
     * 
     * @param int $project_id Project ID
     * @param int $limit Number of messages
     * @return array Response data
     */
    public function getLatestProjectMessages($project_id, $limit = 10) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Check if project exists
        if (!$this->project->getById($project_id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Get latest messages
        $messages = $this->chat->getLatestProjectMessages($project_id, $limit);
        
        return [
            'status' => 'success',
            'data' => $messages,
            'code' => 200
        ];
    }
}
?>
