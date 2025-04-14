<?php
/**
 * Project Model
 * 
 * Handles all database operations related to projects
 */
class Project {
    // Database connection and table name
    private $conn;
    private $table_name = "projects";

    // Object properties
    public $id;
    public $title;
    public $description;
    public $client_id;
    public $budget;
    public $duration;
    public $skills_required;
    public $status; // 'open', 'in_progress', 'completed', 'cancelled'
    public $attachment;
    public $created_at;
    public $updated_at;
    public $start_date;
    public $end_date;
    public $team_size;
    public $visibility; // 'public', 'private', 'invite_only'

    /**
     * Constructor with database connection
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all projects
     * 
     * @param string $status Project status filter
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of projects
     */
    public function getAll($status = null, $limit = 10, $offset = 0) {
        $query = "SELECT p.*, u.username as client_name 
                  FROM " . $this->table_name . " p
                  JOIN users u ON p.client_id = u.id";
        
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
     * Get project by ID
     * 
     * @param int $id Project ID
     * @return bool Success or failure
     */
    public function getById($id) {
        $query = "SELECT p.*, u.username as client_name, u.email as client_email 
                  FROM " . $this->table_name . " p
                  JOIN users u ON p.client_id = u.id
                  WHERE p.id = :id 
                  LIMIT 1";
        
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
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->client_id = $row['client_id'];
            $this->budget = $row['budget'];
            $this->duration = $row['duration'];
            $this->skills_required = $row['skills_required'];
            $this->status = $row['status'];
            $this->attachment = $row['attachment'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->start_date = $row['start_date'];
            $this->end_date = $row['end_date'];
            $this->team_size = $row['team_size'];
            $this->visibility = $row['visibility'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Get projects by client ID
     * 
     * @param int $client_id Client ID
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of projects
     */
    public function getByClientId($client_id, $limit = 10, $offset = 0) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE client_id = :client_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $client_id = htmlspecialchars(strip_tags($client_id));
        $stmt->bindParam(":client_id", $client_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Create new project
     * 
     * @return bool Operation result
     */
    public function create() {
        // Query
        $query = "INSERT INTO " . $this->table_name . " 
                  (title, description, client_id, budget, duration, skills_required, 
                   status, attachment, team_size, visibility) 
                  VALUES 
                  (:title, :description, :client_id, :budget, :duration, :skills_required, 
                   :status, :attachment, :team_size, :visibility)";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->budget = htmlspecialchars(strip_tags($this->budget));
        $this->duration = htmlspecialchars(strip_tags($this->duration));
        $this->skills_required = $this->skills_required;
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'open'));
        $this->attachment = $this->attachment ?? null;
        $this->team_size = htmlspecialchars(strip_tags($this->team_size ?? 1));
        $this->visibility = htmlspecialchars(strip_tags($this->visibility ?? 'public'));
        
        // Bind values
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":budget", $this->budget);
        $stmt->bindParam(":duration", $this->duration);
        $stmt->bindParam(":skills_required", $this->skills_required);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":attachment", $this->attachment);
        $stmt->bindParam(":team_size", $this->team_size);
        $stmt->bindParam(":visibility", $this->visibility);
        
        // Execute query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    /**
     * Update project
     * 
     * @return bool Operation result
     */
    public function update() {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                  title = :title,
                  description = :description,
                  budget = :budget,
                  duration = :duration,
                  skills_required = :skills_required,
                  status = :status,
                  attachment = :attachment,
                  team_size = :team_size,
                  visibility = :visibility,
                  updated_at = NOW()
                  WHERE id = :id AND client_id = :client_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->budget = htmlspecialchars(strip_tags($this->budget));
        $this->duration = htmlspecialchars(strip_tags($this->duration));
        $this->skills_required = $this->skills_required;
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->attachment = $this->attachment;
        $this->team_size = htmlspecialchars(strip_tags($this->team_size));
        $this->visibility = htmlspecialchars(strip_tags($this->visibility));
        
        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":budget", $this->budget);
        $stmt->bindParam(":duration", $this->duration);
        $stmt->bindParam(":skills_required", $this->skills_required);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":attachment", $this->attachment);
        $stmt->bindParam(":team_size", $this->team_size);
        $stmt->bindParam(":visibility", $this->visibility);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Update project status
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
     * Delete project
     * 
     * @return bool Operation result
     */
    public function delete() {
        // Query
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND client_id = :client_id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":client_id", $this->client_id);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    /**
     * Search projects by skills
     * 
     * @param string $skills Skills to search for
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of matching projects
     */
    public function searchBySkills($skills, $limit = 10, $offset = 0) {
        $query = "SELECT p.*, u.username as client_name 
                  FROM " . $this->table_name . " p
                  JOIN users u ON p.client_id = u.id
                  WHERE p.status = 'open' 
                  AND FIND_IN_SET(:skill, p.skills_required) > 0 
                  ORDER BY p.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":skill", $skills);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Set project dates
     * 
     * @param string $start_date Project start date
     * @param string $end_date Project end date
     * @return bool Operation result
     */
    public function setDates($start_date, $end_date) {
        // Query
        $query = "UPDATE " . $this->table_name . " 
                  SET start_date = :start_date, end_date = :end_date, updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if ($stmt->execute()) {
            $this->start_date = $start_date;
            $this->end_date = $end_date;
            return true;
        }
        
        return false;
    }

    /**
     * Get recent projects
     * 
     * @param int $limit Limit results
     * @return array List of recent projects
     */
    public function getRecent($limit = 5) {
        $query = "SELECT p.*, u.username as client_name 
                  FROM " . $this->table_name . " p
                  JOIN users u ON p.client_id = u.id
                  WHERE p.status = 'open' 
                  ORDER BY p.created_at DESC 
                  LIMIT :limit";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind value
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>
