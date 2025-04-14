<?php
/**
 * Database Manager Class
 * 
 * Handles database connections and operations for the SafeHire platform
 * Supports both MySQL (cPanel) and PostgreSQL (Replit) environments
 */
class DatabaseManager {
    private $host;
    private $database;
    private $username;
    private $password;
    private $port;
    private $conn;
    private $dbType; // 'mysql' or 'pgsql'
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->port = DB_PORT;
        
        // Determine database type based on environment
        $this->dbType = (getenv('PGDATABASE') || getenv('DATABASE_URL')) ? 'pgsql' : 'mysql';
    }
    
    /**
     * Connect to the database
     * @return PDO Database connection
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Use environment variables for PostgreSQL if available (for Replit)
            if (getenv("DATABASE_URL")) {
                // Parse the DATABASE_URL into its components
                $db_url = parse_url(getenv("DATABASE_URL"));
                $host = $db_url['host'] ?? getenv('PGHOST');
                $port = $db_url['port'] ?? getenv('PGPORT');
                $dbname = ltrim($db_url['path'] ?? ('/' . getenv('PGDATABASE')), '/');
                $user = $db_url['user'] ?? getenv('PGUSER');
                $pass = $db_url['pass'] ?? getenv('PGPASSWORD');
                
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
                $this->conn = new PDO($dsn, $user, $pass);
                
                // Debug info
                error_log("Connected using PostgreSQL DATABASE_URL: {$dsn}");
            } 
            // Use PostgreSQL with individual environment variables
            elseif (getenv('PGDATABASE')) {
                $dsn = "pgsql:host=" . getenv('PGHOST') . ";port=" . getenv('PGPORT') . ";dbname=" . getenv('PGDATABASE');
                $this->conn = new PDO($dsn, getenv('PGUSER'), getenv('PGPASSWORD'));
                
                // Debug info
                error_log("Connected using PostgreSQL env vars: {$dsn}");
            } 
            // Use MySQL (typical for cPanel environments)
            else {
                $dsn = "mysql:host={$this->host};dbname={$this->database};port={$this->port};charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
                
                // Debug info
                error_log("Connected using MySQL config: {$dsn}");
            }
            
            // Set PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $e) {
            // Log detailed error
            error_log("Database Connection Error: " . $e->getMessage());
            error_log("Connection Type: " . $this->dbType);
            error_log("DB Host: " . $this->host);
            error_log("DB Name: " . $this->database);
            
            // Return error message with limited information
            throw new Exception("Database connection error. Please check your database settings and try again.");
        }
        
        return $this->conn;
    }
    
    /**
     * Begin a transaction
     * @return bool
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     * Commit a transaction
     * @return bool
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback a transaction
     * @return bool
     */
    public function rollback() {
        return $this->conn->rollBack();
    }
    
    /**
     * Execute a query
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return PDOStatement
     */
    public function executeQuery($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            // Log error
            error_log("Query Error: " . $e->getMessage() . " - Query: " . $query);
            throw new Exception("Database query error. Please try again later.");
        }
    }
    
    /**
     * Get the last inserted ID
     * @return string The last inserted ID
     */
    public function getLastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    /**
     * Check if table exists
     * @param string $tableName Table name to check
     * @return bool True if table exists, false otherwise
     */
    public function tableExists($tableName) {
        try {
            if ($this->dbType === 'pgsql') {
                $result = $this->conn->query("SELECT to_regclass('public.{$tableName}')");
                $exists = $result->fetchColumn();
                return $exists !== null;
            } else {
                // MySQL
                $result = $this->conn->query("SHOW TABLES LIKE '{$tableName}'");
                return $result->rowCount() > 0;
            }
        } catch(PDOException $e) {
            error_log("Error checking if table exists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create tables if they don't exist
     * @return bool True if successful, false otherwise
     */
    public function createTablesIfNotExist() {
        try {
            // Get the SQL schema based on database type
            $schema_file = ($this->dbType === 'pgsql') 
                ? '/database_schema.sql' 
                : '/database_schema_mysql.sql';
            
            // Check if the specific file exists, otherwise use default
            if (!file_exists(BASE_PATH . $schema_file)) {
                $schema_file = '/database_schema.sql';
            }
            
            $sql = file_get_contents(BASE_PATH . $schema_file);
            
            // Execute the SQL
            $this->conn->exec($sql);
            return true;
        } catch(PDOException $e) {
            error_log("Error creating tables: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert a PostgreSQL query to MySQL syntax
     * @param string $query PostgreSQL query
     * @return string MySQL-compatible query
     */
    public function convertToMySQLSyntax($query) {
        // Only convert if we're using MySQL
        if ($this->dbType !== 'mysql') {
            return $query;
        }
        
        // Replace SERIAL with AUTO_INCREMENT
        $query = preg_replace('/SERIAL PRIMARY KEY/', 'INT AUTO_INCREMENT PRIMARY KEY', $query);
        
        // Replace TIMESTAMP DEFAULT CURRENT_TIMESTAMP with equivalent MySQL syntax
        $query = str_replace('TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 
                            'TIMESTAMP DEFAULT CURRENT_TIMESTAMP', $query);
        
        // Replace TEXT[] with TEXT (MySQL doesn't have array types)
        $query = preg_replace('/TEXT\[\]/', 'TEXT', $query);
        
        return $query;
    }
}
