<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Freelancer.php';
require_once __DIR__ . '/../models/Project.php';

class AITeamMatcher {
    private $db;
    private $freelancerModel;
    private $projectModel;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->freelancerModel = new Freelancer($this->db);
        $this->projectModel = new Project($this->db);
    }
    
    /**
     * Find the best team for a project
     * @param int $projectId The project ID
     * @param int $teamSize Desired team size
     * @param array $requiredSkills Optional array of required skills
     * @return array List of recommended freelancers
     */
    public function findBestTeam($projectId, $teamSize = 0, $requiredSkills = []) {
        try {
            // Get project details
            $project = $this->projectModel->getProjectById($projectId);
            
            if (!$project) {
                throw new Exception("Project not found");
            }
            
            // If team size is not specified, determine based on project scope
            if ($teamSize <= 0) {
                $teamSize = $this->determineTeamSize($project);
            }
            
            // Get required skills from the project if not provided
            if (empty($requiredSkills) && !empty($project['required_skills'])) {
                $requiredSkills = json_decode($project['required_skills'], true);
            }
            
            // Find freelancers with matching skills
            $freelancers = $this->findFreelancersWithMatchingSkills($requiredSkills);
            
            // Score and rank freelancers
            $rankedFreelancers = $this->rankFreelancers($freelancers, $project, $requiredSkills);
            
            // Optimize team composition
            $optimalTeam = $this->optimizeTeam($rankedFreelancers, $teamSize, $requiredSkills);
            
            return $optimalTeam;
        } catch (Exception $e) {
            error_log("Error in AI Team Matcher: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Determine an optimal team size based on project details
     * @param array $project Project details
     * @return int Recommended team size
     */
    private function determineTeamSize($project) {
        // Initialize with a default size
        $teamSize = 1;
        
        // Consider budget - higher budget might allow larger team
        $budget = floatval($project['budget']);
        if ($budget > 5000) {
            $teamSize++;
        }
        if ($budget > 10000) {
            $teamSize++;
        }
        
        // Consider project duration - longer projects might need more people
        $duration = intval($project['duration_days']);
        if ($duration > 30) {
            $teamSize++;
        }
        if ($duration > 90) {
            $teamSize++;
        }
        
        // Consider complexity based on required skills
        $skills = json_decode($project['required_skills'], true) ?? [];
        $skillCount = count($skills);
        if ($skillCount > 3) {
            $teamSize++;
        }
        if ($skillCount > 6) {
            $teamSize++;
        }
        
        return min($teamSize, 5); // Cap at 5 team members
    }
    
    /**
     * Find freelancers with matching skills
     * @param array $requiredSkills List of required skills
     * @return array Matching freelancers
     */
    private function findFreelancersWithMatchingSkills($requiredSkills) {
        return $this->freelancerModel->findBySkills($requiredSkills);
    }
    
    /**
     * Rank freelancers based on various factors
     * @param array $freelancers List of potential freelancers
     * @param array $project Project details
     * @param array $requiredSkills Required skills
     * @return array Ranked freelancers with scores
     */
    private function rankFreelancers($freelancers, $project, $requiredSkills) {
        $rankedFreelancers = [];
        
        foreach ($freelancers as $freelancer) {
            $score = 0;
            $freelancerSkills = json_decode($freelancer['skills'], true) ?? [];
            
            // Score based on skill match
            foreach ($requiredSkills as $skill) {
                if (in_array($skill, $freelancerSkills)) {
                    $score += 10;
                }
            }
            
            // Score based on rating
            $score += ($freelancer['rating'] ?? 0) * 5;
            
            // Score based on past project success
            $completedProjects = $this->freelancerModel->getCompletedProjectsCount($freelancer['id']);
            $score += min($completedProjects * 2, 20); // Cap at 20 points
            
            // Score based on availability
            if ($freelancer['is_available']) {
                $score += 15;
            }
            
            // Add to ranked list
            $rankedFreelancers[] = [
                'freelancer' => $freelancer,
                'score' => $score,
                'matching_skills' => array_intersect($freelancerSkills, $requiredSkills)
            ];
        }
        
        // Sort by score (descending)
        usort($rankedFreelancers, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $rankedFreelancers;
    }
    
    /**
     * Optimize team composition to ensure all skills are covered
     * @param array $rankedFreelancers Ranked list of freelancers
     * @param int $teamSize Desired team size
     * @param array $requiredSkills Required skills
     * @return array Optimal team
     */
    private function optimizeTeam($rankedFreelancers, $teamSize, $requiredSkills) {
        $team = [];
        $coveredSkills = [];
        
        // First pass: Add highest-scoring freelancers until team size is reached
        // or all skills are covered
        foreach ($rankedFreelancers as $ranked) {
            if (count($team) >= $teamSize) {
                break;
            }
            
            $freelancer = $ranked['freelancer'];
            $matchingSkills = $ranked['matching_skills'];
            
            $team[] = $freelancer;
            $coveredSkills = array_merge($coveredSkills, $matchingSkills);
            $coveredSkills = array_unique($coveredSkills);
            
            // If all skills are covered and team is at least half-full, we can stop
            if (count(array_intersect($requiredSkills, $coveredSkills)) == count($requiredSkills) &&
                count($team) >= ceil($teamSize / 2)) {
                break;
            }
        }
        
        // Second pass: If we still need to cover skills, replace lowest scoring team members
        // with freelancers who have uncovered skills
        $uncoveredSkills = array_diff($requiredSkills, $coveredSkills);
        
        if (!empty($uncoveredSkills) && count($team) >= $teamSize) {
            // Sort team by score (ascending)
            usort($team, function($a, $b) {
                return ($a['score'] ?? 0) - ($b['score'] ?? 0);
            });
            
            foreach ($uncoveredSkills as $skill) {
                // Find freelancer with this skill who is not already in the team
                foreach ($rankedFreelancers as $ranked) {
                    $freelancer = $ranked['freelancer'];
                    if (in_array($skill, $ranked['matching_skills']) && 
                        !in_array($freelancer['id'], array_column($team, 'id'))) {
                        
                        // Replace lowest scoring team member
                        array_shift($team);
                        $team[] = $freelancer;
                        break;
                    }
                }
            }
        }
        
        return $team;
    }
    
    /**
     * Get existing teams that might be suitable for a project
     * @param int $projectId The project ID
     * @return array List of potential teams
     */
    public function findExistingTeams($projectId) {
        try {
            // Get project details
            $project = $this->projectModel->getProjectById($projectId);
            
            if (!$project) {
                throw new Exception("Project not found");
            }
            
            $requiredSkills = json_decode($project['required_skills'], true) ?? [];
            
            // Get teams from database (assuming there's a Team model and method)
            $teamModel = new Team($this->db);
            $teams = $teamModel->getAllTeams();
            
            // Score teams based on skill match and other factors
            $scoredTeams = [];
            
            foreach ($teams as $team) {
                $teamMembers = $teamModel->getTeamMembers($team['id']);
                
                // Collect all skills of team members
                $teamSkills = [];
                foreach ($teamMembers as $member) {
                    $memberSkills = $this->freelancerModel->getFreelancerSkills($member['freelancer_id']);
                    $teamSkills = array_merge($teamSkills, $memberSkills);
                }
                $teamSkills = array_unique($teamSkills);
                
                // Calculate skill match percentage
                $matchingSkills = array_intersect($requiredSkills, $teamSkills);
                $skillMatchPercentage = count($requiredSkills) > 0 
                    ? (count($matchingSkills) / count($requiredSkills)) * 100 
                    : 0;
                
                // Calculate average rating
                $totalRating = 0;
                foreach ($teamMembers as $member) {
                    $freelancer = $this->freelancerModel->getFreelancerById($member['freelancer_id']);
                    $totalRating += $freelancer['rating'] ?? 0;
                }
                $avgRating = count($teamMembers) > 0 ? $totalRating / count($teamMembers) : 0;
                
                // Calculate final score
                $score = ($skillMatchPercentage * 0.7) + ($avgRating * 6);
                
                $scoredTeams[] = [
                    'team' => $team,
                    'members' => $teamMembers,
                    'skill_match' => $skillMatchPercentage,
                    'avg_rating' => $avgRating,
                    'score' => $score
                ];
            }
            
            // Sort by score (descending)
            usort($scoredTeams, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            // Return top 5 teams
            return array_slice($scoredTeams, 0, 5);
        } catch (Exception $e) {
            error_log("Error finding existing teams: " . $e->getMessage());
            throw $e;
        }
    }
}
