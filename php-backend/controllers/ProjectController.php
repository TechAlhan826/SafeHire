<?php
/**
 * Project Controller
 * 
 * Handles project creation, management, and search operations
 */
class ProjectController {
    // Database connection and models
    private $conn;
    private $project;
    private $bid;
    private $contract;
    private $user;
    private $ai_matching;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize models
        require_once __DIR__ . '/../models/Project.php';
        require_once __DIR__ . '/../models/Bid.php';
        require_once __DIR__ . '/../models/Contract.php';
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../services/AIMatchingService.php';
        
        $this->project = new Project($db);
        $this->bid = new Bid($db);
        $this->contract = new Contract($db);
        $this->user = new User($db);
        $this->ai_matching = new AIMatchingService($db);
    }
    
    /**
     * Get all projects with optional filtering
     * 
     * @param string $status Project status filter
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Response data
     */
    public function getProjects($status = null, $page = 1, $limit = 10) {
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
        
        // Get projects
        $projects = $this->project->getAll($status, $limit, $offset);
        
        return [
            'status' => 'success',
            'data' => $projects,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($projects) // Note: would use a separate count query in production
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get project by ID
     * 
     * @param int $id Project ID
     * @return array Response data
     */
    public function getProjectById($id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Get bids for this project
        $bids = $this->bid->getByProjectId($id);
        
        // Check if there's an active contract
        $has_contract = $this->contract->getActiveContractByProject($id);
        $contract_data = null;
        
        if ($has_contract) {
            $contract_data = [
                'id' => $this->contract->id,
                'start_date' => $this->contract->start_date,
                'end_date' => $this->contract->end_date,
                'amount' => $this->contract->amount,
                'status' => $this->contract->status,
                'payment_status' => $this->contract->payment_status,
                'freelancer_id' => $this->contract->freelancer_id
            ];
        }
        
        return [
            'status' => 'success',
            'data' => [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'description' => $this->project->description,
                'client_id' => $this->project->client_id,
                'client_name' => isset($this->project->client_name) ? $this->project->client_name : null,
                'budget' => $this->project->budget,
                'duration' => $this->project->duration,
                'skills_required' => $this->project->skills_required,
                'status' => $this->project->status,
                'attachment' => $this->project->attachment,
                'created_at' => $this->project->created_at,
                'updated_at' => $this->project->updated_at,
                'start_date' => $this->project->start_date,
                'end_date' => $this->project->end_date,
                'team_size' => $this->project->team_size,
                'visibility' => $this->project->visibility,
                'bids' => $bids,
                'contract' => $contract_data
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get projects by client ID
     * 
     * @param int $client_id Client ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Response data
     */
    public function getProjectsByClientId($client_id, $page = 1, $limit = 10) {
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
        
        // Get projects
        $projects = $this->project->getByClientId($client_id, $limit, $offset);
        
        return [
            'status' => 'success',
            'data' => $projects,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($projects) // Note: would use a separate count query in production
            ],
            'code' => 200
        ];
    }
    
    /**
     * Create new project
     * 
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function createProject($client_id) {
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
        $required_fields = ['title', 'description', 'budget', 'duration', 'skills_required'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return [
                    'status' => 'error',
                    'message' => ucfirst($field) . ' is required',
                    'code' => 400
                ];
            }
        }
        
        // Set project properties
        $this->project->title = $data['title'];
        $this->project->description = $data['description'];
        $this->project->client_id = $client_id;
        $this->project->budget = $data['budget'];
        $this->project->duration = $data['duration'];
        $this->project->skills_required = $data['skills_required'];
        $this->project->status = $data['status'] ?? 'open';
        $this->project->team_size = $data['team_size'] ?? 1;
        $this->project->visibility = $data['visibility'] ?? 'public';
        
        // Handle file upload (simplified)
        if (isset($_FILES['attachment'])) {
            $target_dir = __DIR__ . "/../uploads/projects/";
            $file_name = uniqid() . "_" . basename($_FILES["attachment"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                $this->project->attachment = "/uploads/projects/" . $file_name;
            }
        }
        
        // Create the project
        if ($this->project->create()) {
            // Get AI matches if team size > 1
            $ai_matches = [];
            if ($this->project->team_size > 1) {
                $ai_matches = $this->ai_matching->getTeamRecommendations(
                    $this->project->id,
                    $this->project->skills_required,
                    $this->project->team_size
                );
            } else {
                // Get individual matches
                $ai_matches = $this->ai_matching->getFreelancerRecommendations(
                    $this->project->id,
                    $this->project->skills_required
                );
            }
            
            return [
                'status' => 'success',
                'message' => 'Project created successfully',
                'data' => [
                    'id' => $this->project->id,
                    'title' => $this->project->title,
                    'description' => $this->project->description,
                    'budget' => $this->project->budget,
                    'skills_required' => $this->project->skills_required,
                    'ai_matches' => $ai_matches
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to create project',
                'code' => 500
            ];
        }
    }
    
    /**
     * Update project
     * 
     * @param int $id Project ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function updateProject($id, $client_id) {
        // Check if request method is PUT
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->project->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to update this project',
                'code' => 403
            ];
        }
        
        // Check if project has an active contract
        if ($this->contract->getActiveContractByProject($id)) {
            return [
                'status' => 'error',
                'message' => 'Project has an active contract and cannot be updated',
                'code' => 400
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Set updatable fields
        if (isset($data['title'])) $this->project->title = $data['title'];
        if (isset($data['description'])) $this->project->description = $data['description'];
        if (isset($data['budget'])) $this->project->budget = $data['budget'];
        if (isset($data['duration'])) $this->project->duration = $data['duration'];
        if (isset($data['skills_required'])) $this->project->skills_required = $data['skills_required'];
        if (isset($data['status'])) $this->project->status = $data['status'];
        if (isset($data['team_size'])) $this->project->team_size = $data['team_size'];
        if (isset($data['visibility'])) $this->project->visibility = $data['visibility'];
        
        // Handle file upload (simplified)
        if (isset($_FILES['attachment'])) {
            $target_dir = __DIR__ . "/../uploads/projects/";
            $file_name = uniqid() . "_" . basename($_FILES["attachment"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                $this->project->attachment = "/uploads/projects/" . $file_name;
            }
        }
        
        // Update the project
        if ($this->project->update()) {
            return [
                'status' => 'success',
                'message' => 'Project updated successfully',
                'data' => [
                    'id' => $this->project->id,
                    'title' => $this->project->title,
                    'description' => $this->project->description,
                    'budget' => $this->project->budget,
                    'skills_required' => $this->project->skills_required,
                    'status' => $this->project->status
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to update project',
                'code' => 500
            ];
        }
    }
    
    /**
     * Delete project
     * 
     * @param int $id Project ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function deleteProject($id, $client_id) {
        // Check if request method is DELETE
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->project->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to delete this project',
                'code' => 403
            ];
        }
        
        // Check if project has bids or contracts
        $bids = $this->bid->getByProjectId($id);
        $has_contract = $this->contract->getActiveContractByProject($id);
        
        if (count($bids) > 0 || $has_contract) {
            return [
                'status' => 'error',
                'message' => 'Project has bids or an active contract and cannot be deleted',
                'code' => 400
            ];
        }
        
        // Delete the project
        if ($this->project->delete()) {
            return [
                'status' => 'success',
                'message' => 'Project deleted successfully',
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to delete project',
                'code' => 500
            ];
        }
    }
    
    /**
     * Search projects by skills
     * 
     * @return array Response data
     */
    public function searchProjects() {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get search parameters
        $skills = isset($_GET['skills']) ? $_GET['skills'] : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // Search projects
        if (!empty($skills)) {
            $projects = $this->project->searchBySkills($skills, $limit, $offset);
        } else {
            // Get all open projects if no skills provided
            $projects = $this->project->getAll('open', $limit, $offset);
        }
        
        return [
            'status' => 'success',
            'data' => $projects,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($projects) // Note: would use a separate count query in production
            ],
            'code' => 200
        ];
    }
    
    /**
     * Get AI recommended matches for a project
     * 
     * @param int $id Project ID
     * @return array Response data
     */
    public function getAIMatches($id) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Get matches based on team size
        if ($this->project->team_size > 1) {
            $matches = $this->ai_matching->getTeamRecommendations(
                $id,
                $this->project->skills_required,
                $this->project->team_size
            );
        } else {
            $matches = $this->ai_matching->getFreelancerRecommendations(
                $id,
                $this->project->skills_required
            );
        }
        
        return [
            'status' => 'success',
            'data' => $matches,
            'code' => 200
        ];
    }
    
    /**
     * Get recent projects
     * 
     * @param int $limit Number of projects to return
     * @return array Response data
     */
    public function getRecentProjects($limit = 5) {
        // Check if request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get recent projects
        $projects = $this->project->getRecent($limit);
        
        return [
            'status' => 'success',
            'data' => $projects,
            'code' => 200
        ];
    }
    
    /**
     * Set project dates
     * 
     * @param int $id Project ID
     * @param int $client_id Client ID
     * @return array Response data
     */
    public function setProjectDates($id, $client_id) {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'status' => 'error',
                'message' => 'Method not allowed',
                'code' => 405
            ];
        }
        
        // Get project by ID
        if (!$this->project->getById($id)) {
            return [
                'status' => 'error',
                'message' => 'Project not found',
                'code' => 404
            ];
        }
        
        // Verify ownership
        if ($this->project->client_id != $client_id) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to update this project',
                'code' => 403
            ];
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        if (empty($data['start_date']) || empty($data['end_date'])) {
            return [
                'status' => 'error',
                'message' => 'Start date and end date are required',
                'code' => 400
            ];
        }
        
        // Set project dates
        if ($this->project->setDates($data['start_date'], $data['end_date'])) {
            return [
                'status' => 'success',
                'message' => 'Project dates updated successfully',
                'data' => [
                    'id' => $this->project->id,
                    'start_date' => $this->project->start_date,
                    'end_date' => $this->project->end_date
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Unable to update project dates',
                'code' => 500
            ];
        }
    }
}
?>
