<?php
/**
 * AI Matching Service
 * 
 * Handles recommendation of freelancers and teams for projects based on skills and ratings
 */
class AIMatchingService {
    // Database connection
    private $conn;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Get freelancer recommendations for a project
     * 
     * @param int $project_id Project ID
     * @param string $skills_required Skills required for the project
     * @param int $limit Maximum number of recommendations to return
     * @return array List of recommended freelancers
     */
    public function getFreelancerRecommendations($project_id, $skills_required, $limit = 5) {
        // Parse skills into an array
        $skills_array = explode(',', $skills_required);
        $skills_array = array_map('trim', $skills_array);
        
        // Query to find freelancers with matching skills, sorted by rating and skill match
        $query = "SELECT u.id, u.username, u.profile_image, u.skills, u.bio, 
                 u.hourly_rate, u.location, u.rating, u.availability,
                 (
                     SELECT COUNT(*) 
                     FROM contracts c 
                     WHERE c.freelancer_id = u.id AND c.status = 'completed'
                 ) as completed_projects,
                 (
                     SELECT SUM(LENGTH(u.skills) - LENGTH(REPLACE(u.skills, ',', ''))) 
                     FROM users 
                     WHERE id = u.id
                 ) + 1 as skill_count
                 FROM users u
                 WHERE u.user_type = 'freelancer'
                 AND u.is_verified = 1
                 AND u.active_status = 1";
        
        // Filter by skill match
        $skill_conditions = [];
        foreach ($skills_array as $skill) {
            if (trim($skill) != '') {
                $skill_conditions[] = "u.skills LIKE :skill_" . md5($skill);
            }
        }
        
        if (!empty($skill_conditions)) {
            $query .= " AND (" . implode(" OR ", $skill_conditions) . ")";
        }
        
        // Order by skill matching percentage, rating, and completed projects
        $query .= " ORDER BY (
                     SELECT COUNT(*) FROM (
                         SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(u.skills, ',', numbers.n), ',', -1)) as skill
                         FROM (
                             SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
                         ) numbers
                         WHERE numbers.n <= 1 + LENGTH(u.skills) - LENGTH(REPLACE(u.skills, ',', ''))
                     ) skills_list
                     WHERE ";
        
        $skill_match_conditions = [];
        foreach ($skills_array as $skill) {
            if (trim($skill) != '') {
                $skill_match_conditions[] = "TRIM(skills_list.skill) LIKE :match_" . md5($skill);
            }
        }
        
        if (!empty($skill_match_conditions)) {
            $query .= "(" . implode(" OR ", $skill_match_conditions) . ")";
        } else {
            $query .= "1=1"; // Fallback if no skills provided
        }
        
        $query .= ") / skill_count DESC, u.rating DESC, completed_projects DESC
                   LIMIT :limit";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind skill parameters
        foreach ($skills_array as $skill) {
            if (trim($skill) != '') {
                $param = '%' . trim($skill) . '%';
                $stmt->bindValue(":skill_" . md5($skill), $param);
                $stmt->bindValue(":match_" . md5($skill), '%' . trim($skill) . '%');
            }
        }
        
        // Bind limit
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        $freelancers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate match percentage for each freelancer
        foreach ($freelancers as &$freelancer) {
            // Get freelancer skills
            $freelancer_skills = explode(',', $freelancer['skills']);
            $freelancer_skills = array_map('trim', $freelancer_skills);
            
            // Count matching skills
            $matches = array_intersect($freelancer_skills, $skills_array);
            $match_count = count($matches);
            $required_count = count($skills_array);
            
            // Calculate percentage
            $percentage = ($required_count > 0) ? round(($match_count / $required_count) * 100) : 0;
            
            // Add match percentage and matching skills to result
            $freelancer['match_percentage'] = $percentage;
            $freelancer['matching_skills'] = implode(', ', $matches);
            
            // Get recent reviews
            $query = "SELECT r.rating, r.review_text, r.created_at, u.username as reviewer_name
                      FROM reviews r
                      JOIN users u ON r.reviewer_id = u.id
                      WHERE r.reviewee_id = :freelancer_id
                      ORDER BY r.created_at DESC
                      LIMIT 2";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':freelancer_id', $freelancer['id']);
            $stmt->execute();
            $freelancer['recent_reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Determine why this freelancer was recommended
            $reasons = [];
            
            if ($percentage >= 80) {
                $reasons[] = "High skill match for the required skills";
            } elseif ($percentage >= 50) {
                $reasons[] = "Moderate skill match for some required skills";
            }
            
            if ($freelancer['rating'] >= 4.5) {
                $reasons[] = "Excellent rating from past clients";
            } elseif ($freelancer['rating'] >= 4.0) {
                $reasons[] = "Very good rating from past clients";
            }
            
            if ($freelancer['completed_projects'] >= 10) {
                $reasons[] = "Experienced with " . $freelancer['completed_projects'] . " completed projects";
            } elseif ($freelancer['completed_projects'] >= 5) {
                $reasons[] = "Has successfully completed " . $freelancer['completed_projects'] . " projects";
            }
            
            $freelancer['recommendation_reasons'] = $reasons;
        }
        
        return $freelancers;
    }
    
    /**
     * Get team recommendations for a project
     * 
     * @param int $project_id Project ID
     * @param string $skills_required Skills required for the project
     * @param int $team_size Desired team size
     * @param int $limit Maximum number of team recommendations to return
     * @return array List of recommended teams
     */
    public function getTeamRecommendations($project_id, $skills_required, $team_size = 3, $limit = 3) {
        // Parse skills into an array
        $skills_array = explode(',', $skills_required);
        $skills_array = array_map('trim', $skills_array);
        
        // First, get a pool of skilled freelancers
        $freelancer_pool = $this->getFreelancerPool($skills_array, $team_size * 5);
        
        // Group freelancers by primary skill
        $skill_groups = [];
        foreach ($freelancer_pool as $freelancer) {
            $freelancer_skills = explode(',', $freelancer['skills']);
            $freelancer_skills = array_map('trim', $freelancer_skills);
            
            // Find matching skills
            $matching_skills = array_intersect($freelancer_skills, $skills_array);
            
            if (!empty($matching_skills)) {
                // Use the first matching skill as primary for grouping
                $primary_skill = reset($matching_skills);
                
                if (!isset($skill_groups[$primary_skill])) {
                    $skill_groups[$primary_skill] = [];
                }
                
                $skill_groups[$primary_skill][] = $freelancer;
            }
        }
        
        // Generate team recommendations
        $teams = [];
        $team_count = 0;
        
        // Attempt to create balanced teams
        while ($team_count < $limit && !empty($skill_groups)) {
            $team = [];
            $team_skills = [];
            
            // Select one freelancer from each skill group, prioritizing most important skills
            foreach ($skills_array as $required_skill) {
                if (count($team) >= $team_size) {
                    break; // Team is complete
                }
                
                if (isset($skill_groups[$required_skill]) && !empty($skill_groups[$required_skill])) {
                    // Sort this skill group by rating
                    usort($skill_groups[$required_skill], function($a, $b) {
                        return $b['rating'] <=> $a['rating'];
                    });
                    
                    // Add the highest-rated freelancer to the team
                    $team_member = array_shift($skill_groups[$required_skill]);
                    
                    // Remove this freelancer from all other skill groups
                    foreach ($skill_groups as $skill => $members) {
                        $skill_groups[$skill] = array_filter($members, function($member) use ($team_member) {
                            return $member['id'] != $team_member['id'];
                        });
                    }
                    
                    // Add to team
                    $team[] = $team_member;
                    
                    // Track team skills
                    $member_skills = explode(',', $team_member['skills']);
                    $member_skills = array_map('trim', $member_skills);
                    $team_skills = array_merge($team_skills, $member_skills);
                }
            }
            
            // If team isn't full, add more members with complementary skills
            while (count($team) < $team_size && !empty($skill_groups)) {
                // Find skills not covered by current team
                $missing_skills = array_diff($skills_array, $team_skills);
                
                if (empty($missing_skills)) {
                    // All skills covered, just get highest rated remaining freelancers
                    $all_remaining = [];
                    foreach ($skill_groups as $skill => $members) {
                        $all_remaining = array_merge($all_remaining, $members);
                    }
                    
                    if (empty($all_remaining)) {
                        break; // No more freelancers available
                    }
                    
                    // Sort by rating
                    usort($all_remaining, function($a, $b) {
                        return $b['rating'] <=> $a['rating'];
                    });
                    
                    $team_member = array_shift($all_remaining);
                    
                    // Remove this freelancer from all skill groups
                    foreach ($skill_groups as $skill => $members) {
                        $skill_groups[$skill] = array_filter($members, function($member) use ($team_member) {
                            return $member['id'] != $team_member['id'];
                        });
                    }
                    
                    // Add to team
                    $team[] = $team_member;
                    
                    // Track team skills
                    $member_skills = explode(',', $team_member['skills']);
                    $member_skills = array_map('trim', $member_skills);
                    $team_skills = array_merge($team_skills, $member_skills);
                } else {
                    // Try to find a freelancer with missing skills
                    $found = false;
                    
                    foreach ($missing_skills as $missing_skill) {
                        if (isset($skill_groups[$missing_skill]) && !empty($skill_groups[$missing_skill])) {
                            // Sort this skill group by rating
                            usort($skill_groups[$missing_skill], function($a, $b) {
                                return $b['rating'] <=> $a['rating'];
                            });
                            
                            // Add the highest-rated freelancer to the team
                            $team_member = array_shift($skill_groups[$missing_skill]);
                            
                            // Remove this freelancer from all other skill groups
                            foreach ($skill_groups as $skill => $members) {
                                $skill_groups[$skill] = array_filter($members, function($member) use ($team_member) {
                                    return $member['id'] != $team_member['id'];
                                });
                            }
                            
                            // Add to team
                            $team[] = $team_member;
                            
                            // Track team skills
                            $member_skills = explode(',', $team_member['skills']);
                            $member_skills = array_map('trim', $member_skills);
                            $team_skills = array_merge($team_skills, $member_skills);
                            
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        // No freelancers found with missing skills, get highest rated
                        $all_remaining = [];
                        foreach ($skill_groups as $skill => $members) {
                            $all_remaining = array_merge($all_remaining, $members);
                        }
                        
                        if (empty($all_remaining)) {
                            break; // No more freelancers available
                        }
                        
                        // Remove duplicates
                        $unique_remaining = [];
                        foreach ($all_remaining as $fr) {
                            $unique_remaining[$fr['id']] = $fr;
                        }
                        $all_remaining = array_values($unique_remaining);
                        
                        // Sort by rating
                        usort($all_remaining, function($a, $b) {
                            return $b['rating'] <=> $a['rating'];
                        });
                        
                        $team_member = array_shift($all_remaining);
                        
                        // Remove this freelancer from all skill groups
                        foreach ($skill_groups as $skill => $members) {
                            $skill_groups[$skill] = array_filter($members, function($member) use ($team_member) {
                                return $member['id'] != $team_member['id'];
                            });
                        }
                        
                        // Add to team
                        $team[] = $team_member;
                        
                        // Track team skills
                        $member_skills = explode(',', $team_member['skills']);
                        $member_skills = array_map('trim', $member_skills);
                        $team_skills = array_merge($team_skills, $member_skills);
                    }
                }
            }
            
            // Remove empty skill groups
            foreach ($skill_groups as $skill => $members) {
                if (empty($members)) {
                    unset($skill_groups[$skill]);
                }
            }
            
            // If we have a valid team, add it to results
            if (count($team) > 0) {
                // Calculate team metrics
                $team_skills_unique = array_unique($team_skills);
                $team_rating_avg = array_sum(array_column($team, 'rating')) / count($team);
                $team_match_percentage = $this->calculateTeamMatchPercentage($team_skills_unique, $skills_array);
                
                $teams[] = [
                    'team_id' => 'team_' . $team_count,
                    'members' => $team,
                    'match_percentage' => $team_match_percentage,
                    'average_rating' => round($team_rating_avg, 1),
                    'skill_coverage' => count(array_intersect($team_skills_unique, $skills_array)) . '/' . count($skills_array),
                    'team_skills' => array_unique($team_skills)
                ];
                
                $team_count++;
            }
        }
        
        return $teams;
    }
    
    /**
     * Get a pool of potential freelancers for team formation
     * 
     * @param array $skills_array Required skills
     * @param int $pool_size Size of the freelancer pool
     * @return array List of freelancers
     */
    private function getFreelancerPool($skills_array, $pool_size = 30) {
        // Query to find freelancers with matching skills
        $query = "SELECT u.id, u.username, u.profile_image, u.skills, u.bio, 
                 u.hourly_rate, u.location, u.rating, u.availability,
                 (
                     SELECT COUNT(*) 
                     FROM contracts c 
                     WHERE c.freelancer_id = u.id AND c.status = 'completed'
                 ) as completed_projects
                 FROM users u
                 WHERE u.user_type = 'freelancer'
                 AND u.is_verified = 1
                 AND u.active_status = 1";
        
        // Filter by skill match
        $skill_conditions = [];
        foreach ($skills_array as $skill) {
            if (trim($skill) != '') {
                $skill_conditions[] = "u.skills LIKE :skill_" . md5($skill);
            }
        }
        
        if (!empty($skill_conditions)) {
            $query .= " AND (" . implode(" OR ", $skill_conditions) . ")";
        }
        
        // Order by rating and completed projects
        $query .= " ORDER BY u.rating DESC, completed_projects DESC
                   LIMIT :limit";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind skill parameters
        foreach ($skills_array as $skill) {
            if (trim($skill) != '') {
                $param = '%' . trim($skill) . '%';
                $stmt->bindValue(":skill_" . md5($skill), $param);
            }
        }
        
        // Bind limit
        $stmt->bindParam(":limit", $pool_size, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate the match percentage between team skills and required skills
     * 
     * @param array $team_skills Team's combined skills
     * @param array $required_skills Required skills for the project
     * @return int Match percentage
     */
    private function calculateTeamMatchPercentage($team_skills, $required_skills) {
        $matching_skills = array_intersect($team_skills, $required_skills);
        $match_count = count($matching_skills);
        $required_count = count($required_skills);
        
        return ($required_count > 0) ? round(($match_count / $required_count) * 100) : 0;
    }
}
?>
