<?php
require_once __DIR__ . '/User.php';

class Freelancer {
    private $db;
    private $table = 'freelancers';
    private $userModel;
    
    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
    }
    
    /**
     * Create a new freelancer profile
     * @param int $userId User ID
     * @param array $data Freelancer data
     * @return bool True if successful, false otherwise
     */
    public function create($userId, $data) {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Check if user exists
            $user = $this->userModel->getById($userId);
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Check if freelancer profile already exists
            $existingProfile = $this->getByUserId($userId);
            if ($existingProfile) {
                throw new Exception("Freelancer profile already exists");
            }
            
            // Prepare skills as JSON
            $skills = isset($data['skills']) ? json_encode($data['skills']) : null;
            
            $query = "INSERT INTO {$this->table} (
                user_id, title, bio, skills, hourly_rate, experience_years, 
                education, certifications, languages, is_available, open_to_team, 
                portfolio_url, created_at
            ) VALUES (
                :user_id, :title, :bio, :skills, :hourly_rate, :experience_years,
                :education, :certifications, :languages, :is_available, :open_to_team,
                :portfolio_url, NOW()
            )";
            
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':bio', $data['bio']);
            $stmt->bindParam(':skills', $skills);
            $stmt->bindParam(':hourly_rate', $data['hourly_rate']);
            $stmt->bindParam(':experience_years', $data['experience_years']);
            $stmt->bindParam(':education', $data['education']);
            $stmt->bindParam(':certifications', $data['certifications']);
            $stmt->bindParam(':languages', $data['languages']);
            
            $isAvailable = isset($data['is_available']) ? $data['is_available'] : 1;
            $openToTeam = isset($data['open_to_team']) ? $data['open_to_team'] : 1;
            
            $stmt->bindParam(':is_available', $isAvailable, PDO::PARAM_INT);
            $stmt->bindParam(':open_to_team', $openToTeam, PDO::PARAM_INT);
            $stmt->bindParam(':portfolio_url', $data['portfolio_url']);
            
            // Execute query
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("Failed to create freelancer profile");
            }
            
            // Update user role if needed
            if ($user['role'] !== 'freelancer') {
                $this->userModel->update($userId, ['role' => 'freelancer']);
            }
            
            // Commit transaction
            $this->db->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollBack();
            error_log("Freelancer profile creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get freelancer profile by user ID
     * @param int $userId User ID
     * @return array|bool Freelancer data if found, false otherwise
     */
    public function getByUserId($userId) {
        try {
            $query = "SELECT f.*, u.email, u.first_name, u.last_name, u.phone, u.profile_image
                      FROM {$this->table} f
                      JOIN users u ON f.user_id = u.id
                      WHERE f.user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($freelancer) {
                // Decode JSON fields
                if (isset($freelancer['skills'])) {
                    $freelancer['skills'] = json_decode($freelancer['skills'], true);
                }
                
                // Get rating
                $freelancer['rating'] = $this->getFreelancerRating($userId);
                
                // Get completed projects count
                $freelancer['completed_projects'] = $this->getCompletedProjectsCount($userId);
                
                return $freelancer;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error fetching freelancer profile: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get freelancer profile by ID
     * @param int $id Freelancer ID
     * @return array|bool Freelancer data if found, false otherwise
     */
    public function getById($id) {
        try {
            $query = "SELECT f.*, u.email, u.first_name, u.last_name, u.phone, u.profile_image
                      FROM {$this->table} f
                      JOIN users u ON f.user_id = u.id
                      WHERE f.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($freelancer) {
                // Decode JSON fields
                if (isset($freelancer['skills'])) {
                    $freelancer['skills'] = json_decode($freelancer['skills'], true);
                }
                
                // Get rating
                $freelancer['rating'] = $this->getFreelancerRating($freelancer['user_id']);
                
                // Get completed projects count
                $freelancer['completed_projects'] = $this->getCompletedProjectsCount($freelancer['user_id']);
                
                return $freelancer;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error fetching freelancer profile by ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update freelancer profile
     * @param int $userId User ID
     * @param array $data Updated freelancer data
     * @return bool True if successful, false otherwise
     */
    public function update($userId, $data) {
        try {
            // Check if profile exists
            $profile = $this->getByUserId($userId);
            if (!$profile) {
                return false;
            }
            
            $updateFields = [];
            $params = [':user_id' => $userId];
            
            // Build update fields
            foreach ($data as $key => $value) {
                if ($key === 'skills' && is_array($value)) {
                    $updateFields[] = "skills = :skills";
                    $params[':skills'] = json_encode($value);
                } elseif ($key !== 'user_id' && $key !== 'id') {
                    $updateFields[] = "{$key} = :{$key}";
                    $params[":{$key}"] = $value;
                }
            }
            
            // Add updated_at timestamp
            $updateFields[] = "updated_at = NOW()";
            
            $query = "UPDATE {$this->table} SET " . implode(', ', $updateFields) . " WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            
            // Execute query
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Freelancer profile update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete freelancer profile
     * @param int $userId User ID
     * @return bool True if successful, false otherwise
     */
    public function delete($userId) {
        try {
            $query = "DELETE FROM {$this->table} WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Freelancer profile deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get freelancer rating
     * @param int $userId User ID
     * @return float Average rating
     */
    public function getFreelancerRating($userId) {
        try {
            $query = "SELECT AVG(rating) as avg_rating FROM reviews WHERE recipient_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return round($result['avg_rating'] ?? 0, 1);
        } catch (PDOException $e) {
            error_log("Error getting freelancer rating: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get completed projects count
     * @param int $userId User ID
     * @return int Number of completed projects
     */
    public function getCompletedProjectsCount($userId) {
        try {
            $query = "SELECT COUNT(*) as count FROM contracts c
                      JOIN projects p ON c.project_id = p.id
                      WHERE c.freelancer_id = :user_id AND p.status = 'completed'";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int) $result['count'];
        } catch (PDOException $e) {
            error_log("Error counting completed projects: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get all freelancers
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @param array $filters Optional filters
     * @return array List of freelancers
     */
    public function getAll($limit = 50, $offset = 0, $filters = []) {
        try {
            $query = "SELECT f.*, u.email, u.first_name, u.last_name, u.phone, u.profile_image
                      FROM {$this->table} f
                      JOIN users u ON f.user_id = u.id";
            
            $whereConditions = [];
            $params = [];
            
            // Apply filters
            if (!empty($filters)) {
                // Filter by availability
                if (isset($filters['is_available'])) {
                    $whereConditions[] = "f.is_available = :is_available";
                    $params[':is_available'] = $filters['is_available'];
                }
                
                // Filter by team openness
                if (isset($filters['open_to_team'])) {
                    $whereConditions[] = "f.open_to_team = :open_to_team";
                    $params[':open_to_team'] = $filters['open_to_team'];
                }
                
                // Filter by skills
                if (isset($filters['skills']) && is_array($filters['skills'])) {
                    $skillConditions = [];
                    foreach ($filters['skills'] as $index => $skill) {
                        $paramName = ":skill{$index}";
                        $skillConditions[] = "f.skills LIKE {$paramName}";
                        $params[$paramName] = "%\"{$skill}\"%";
                    }
                    if (!empty($skillConditions)) {
                        $whereConditions[] = "(" . implode(" OR ", $skillConditions) . ")";
                    }
                }
                
                // Filter by hourly rate range
                if (isset($filters['min_rate'])) {
                    $whereConditions[] = "f.hourly_rate >= :min_rate";
                    $params[':min_rate'] = $filters['min_rate'];
                }
                if (isset($filters['max_rate'])) {
                    $whereConditions[] = "f.hourly_rate <= :max_rate";
                    $params[':max_rate'] = $filters['max_rate'];
                }
                
                // Filter by experience years
                if (isset($filters['min_experience'])) {
                    $whereConditions[] = "f.experience_years >= :min_experience";
                    $params[':min_experience'] = $filters['min_experience'];
                }
            }
            
            // Add WHERE clause if conditions exist
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $query .= " ORDER BY f.updated_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $freelancers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process results
            foreach ($freelancers as &$freelancer) {
                // Decode JSON fields
                if (isset($freelancer['skills'])) {
                    $freelancer['skills'] = json_decode($freelancer['skills'], true);
                }
                
                // Get rating
                $freelancer['rating'] = $this->getFreelancerRating($freelancer['user_id']);
                
                // Get completed projects count
                $freelancer['completed_projects'] = $this->getCompletedProjectsCount($freelancer['user_id']);
            }
            
            return $freelancers;
        } catch (PDOException $e) {
            error_log("Error fetching freelancers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count freelancers
     * @param array $filters Optional filters
     * @return int Count of freelancers
     */
    public function countFreelancers($filters = []) {
        try {
            $query = "SELECT COUNT(*) as count FROM {$this->table} f";
            
            $whereConditions = [];
            $params = [];
            
            // Apply filters (same as getAll)
            if (!empty($filters)) {
                // Filter by availability
                if (isset($filters['is_available'])) {
                    $whereConditions[] = "f.is_available = :is_available";
                    $params[':is_available'] = $filters['is_available'];
                }
                
                // Filter by team openness
                if (isset($filters['open_to_team'])) {
                    $whereConditions[] = "f.open_to_team = :open_to_team";
                    $params[':open_to_team'] = $filters['open_to_team'];
                }
                
                // Filter by skills
                if (isset($filters['skills']) && is_array($filters['skills'])) {
                    $skillConditions = [];
                    foreach ($filters['skills'] as $index => $skill) {
                        $paramName = ":skill{$index}";
                        $skillConditions[] = "f.skills LIKE {$paramName}";
                        $params[$paramName] = "%\"{$skill}\"%";
                    }
                    if (!empty($skillConditions)) {
                        $whereConditions[] = "(" . implode(" OR ", $skillConditions) . ")";
                    }
                }
                
                // Filter by hourly rate range
                if (isset($filters['min_rate'])) {
                    $whereConditions[] = "f.hourly_rate >= :min_rate";
                    $params[':min_rate'] = $filters['min_rate'];
                }
                if (isset($filters['max_rate'])) {
                    $whereConditions[] = "f.hourly_rate <= :max_rate";
                    $params[':max_rate'] = $filters['max_rate'];
                }
                
                // Filter by experience years
                if (isset($filters['min_experience'])) {
                    $whereConditions[] = "f.experience_years >= :min_experience";
                    $params[':min_experience'] = $filters['min_experience'];
                }
            }
            
            // Add WHERE clause if conditions exist
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int) $result['count'];
        } catch (PDOException $e) {
            error_log("Error counting freelancers: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Find freelancers by skills
     * @param array $skills Skills to match
     * @param int $limit Limit results
     * @return array List of matching freelancers
     */
    public function findBySkills($skills, $limit = 50) {
        try {
            if (empty($skills)) {
                return [];
            }
            
            $query = "SELECT f.*, u.email, u.first_name, u.last_name, u.phone, u.profile_image
                      FROM {$this->table} f
                      JOIN users u ON f.user_id = u.id
                      WHERE f.is_available = 1";
            
            $skillConditions = [];
            $params = [];
            
            foreach ($skills as $index => $skill) {
                $paramName = ":skill{$index}";
                $skillConditions[] = "f.skills LIKE {$paramName}";
                $params[$paramName] = "%\"{$skill}\"%";
            }
            
            if (!empty($skillConditions)) {
                $query .= " AND (" . implode(" OR ", $skillConditions) . ")";
            }
            
            $query .= " ORDER BY f.rating DESC, f.experience_years DESC LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $freelancers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process results
            foreach ($freelancers as &$freelancer) {
                // Decode JSON fields
                if (isset($freelancer['skills'])) {
                    $freelancer['skills'] = json_decode($freelancer['skills'], true);
                }
                
                // Get rating
                $freelancer['rating'] = $this->getFreelancerRating($freelancer['user_id']);
                
                // Get completed projects count
                $freelancer['completed_projects'] = $this->getCompletedProjectsCount($freelancer['user_id']);
            }
            
            return $freelancers;
        } catch (PDOException $e) {
            error_log("Error finding freelancers by skills: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get freelancer's skills
     * @param int $freelancerId Freelancer user ID
     * @return array List of skills
     */
    public function getFreelancerSkills($freelancerId) {
        try {
            $query = "SELECT skills FROM {$this->table} WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $freelancerId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['skills'])) {
                return json_decode($result['skills'], true) ?? [];
            }
            
            return [];
        } catch (PDOException $e) {
            error_log("Error getting freelancer skills: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search freelancers
     * @param string $term Search term
     * @param array $filters Optional filters
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of matching freelancers
     */
    public function searchFreelancers($term, $filters = [], $limit = 50, $offset = 0) {
        try {
            $query = "SELECT f.*, u.email, u.first_name, u.last_name, u.phone, u.profile_image
                      FROM {$this->table} f
                      JOIN users u ON f.user_id = u.id
                      WHERE (u.first_name LIKE :term OR u.last_name LIKE :term OR f.title LIKE :term OR f.bio LIKE :term)";
            
            $params = [':term' => "%{$term}%"];
            
            // Apply filters (same logic as getAll)
            if (!empty($filters)) {
                // Filter by availability
                if (isset($filters['is_available'])) {
                    $query .= " AND f.is_available = :is_available";
                    $params[':is_available'] = $filters['is_available'];
                }
                
                // Other filters as needed...
            }
            
            $query .= " ORDER BY f.updated_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $freelancers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process results
            foreach ($freelancers as &$freelancer) {
                // Decode JSON fields
                if (isset($freelancer['skills'])) {
                    $freelancer['skills'] = json_decode($freelancer['skills'], true);
                }
                
                // Get rating
                $freelancer['rating'] = $this->getFreelancerRating($freelancer['user_id']);
                
                // Get completed projects count
                $freelancer['completed_projects'] = $this->getCompletedProjectsCount($freelancer['user_id']);
            }
            
            return $freelancers;
        } catch (PDOException $e) {
            error_log("Error searching freelancers: " . $e->getMessage());
            return [];
        }
    }
}
