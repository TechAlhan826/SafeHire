<?php
class FileHandler {
    private $uploadsPath;
    private $allowedExtensions;
    private $maxFileSize; // in bytes
    
    public function __construct() {
        $this->uploadsPath = UPLOADS_PATH;
        $this->allowedExtensions = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'svg'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'],
            'archive' => ['zip', 'rar'],
            'code' => ['js', 'php', 'html', 'css', 'json']
        ];
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
    }
    
    /**
     * Upload a file
     * @param array $file The $_FILES element
     * @param string $directory Subdirectory where to store the file
     * @param string $fileType Type of file (image, document, etc.)
     * @return array|bool Array with file info if successful, false otherwise
     */
    public function uploadFile($file, $directory, $fileType = null) {
        try {
            // Check if file was uploaded properly
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("File upload error: " . $this->getUploadErrorMessage($file['error']));
            }
            
            // Check file size
            if ($file['size'] > $this->maxFileSize) {
                throw new Exception("File too large. Maximum size is " . ($this->maxFileSize / 1024 / 1024) . "MB");
            }
            
            // Get file extension
            $fileName = basename($file['name']);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Check if extension is allowed
            if ($fileType && !$this->isExtensionAllowed($extension, $fileType)) {
                throw new Exception("File type not allowed. Allowed types for {$fileType}: " . 
                    implode(', ', $this->allowedExtensions[$fileType]));
            }
            
            // Create unique filename
            $newFileName = $this->generateUniqueFileName($fileName);
            
            // Create directory if it doesn't exist
            $uploadDirectory = $this->uploadsPath . '/' . $directory;
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }
            
            $filePath = $uploadDirectory . '/' . $newFileName;
            
            // Move uploaded file to destination
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception("Failed to move uploaded file");
            }
            
            // Return file information
            return [
                'name' => $newFileName,
                'original_name' => $fileName,
                'path' => $directory . '/' . $newFileName,
                'type' => $file['type'],
                'size' => $file['size'],
                'extension' => $extension,
                'url' => $this->getFileUrl($directory . '/' . $newFileName)
            ];
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a file
     * @param string $filePath Path to the file relative to uploads directory
     * @return bool True if successful, false otherwise
     */
    public function deleteFile($filePath) {
        try {
            $fullPath = $this->uploadsPath . '/' . $filePath;
            
            if (!file_exists($fullPath)) {
                throw new Exception("File not found: {$filePath}");
            }
            
            if (!unlink($fullPath)) {
                throw new Exception("Failed to delete file: {$filePath}");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("File deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a unique filename
     * @param string $originalFileName Original file name
     * @return string Unique filename
     */
    private function generateUniqueFileName($originalFileName) {
        $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalFileName, PATHINFO_FILENAME);
        
        // Sanitize base name
        $baseName = preg_replace('/[^a-z0-9_-]/i', '_', $baseName);
        $baseName = substr($baseName, 0, 50); // Limit length
        
        // Generate timestamp and random string
        $timestamp = time();
        $randomString = bin2hex(random_bytes(3));
        
        return $baseName . '_' . $timestamp . '_' . $randomString . '.' . $extension;
    }
    
    /**
     * Check if a file extension is allowed for a specific file type
     * @param string $extension File extension
     * @param string $fileType Type of file
     * @return bool True if allowed, false otherwise
     */
    private function isExtensionAllowed($extension, $fileType) {
        if (!isset($this->allowedExtensions[$fileType])) {
            return false;
        }
        
        return in_array($extension, $this->allowedExtensions[$fileType]);
    }
    
    /**
     * Get a user-friendly error message for upload errors
     * @param int $errorCode Error code from $_FILES['error']
     * @return string Error message
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            case UPLOAD_ERR_FORM_SIZE:
                return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
            case UPLOAD_ERR_PARTIAL:
                return "The uploaded file was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing a temporary folder";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "A PHP extension stopped the file upload";
            default:
                return "Unknown upload error";
        }
    }
    
    /**
     * Get public URL for a file
     * @param string $filePath Path to the file relative to uploads directory
     * @return string Public URL
     */
    private function getFileUrl($filePath) {
        return APP_URL . '/uploads/' . $filePath;
    }
    
    /**
     * Handle multiple file uploads
     * @param array $files The $_FILES array for multiple files
     * @param string $directory Subdirectory where to store the files
     * @param string $fileType Type of file (image, document, etc.)
     * @return array Array with information about uploaded files
     */
    public function uploadMultipleFiles($files, $directory, $fileType = null) {
        $uploadedFiles = [];
        $errors = [];
        
        // Restructure the $_FILES array if needed
        if (isset($files['name']) && is_array($files['name'])) {
            $fileCount = count($files['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    
                    $result = $this->uploadFile($file, $directory, $fileType);
                    
                    if ($result) {
                        $uploadedFiles[] = $result;
                    } else {
                        $errors[] = "Failed to upload file: " . $files['name'][$i];
                    }
                } else {
                    $errors[] = "Error for file " . $files['name'][$i] . ": " . 
                        $this->getUploadErrorMessage($files['error'][$i]);
                }
            }
        } else {
            // Single file upload
            $result = $this->uploadFile($files, $directory, $fileType);
            
            if ($result) {
                $uploadedFiles[] = $result;
            } else {
                $errors[] = "Failed to upload file";
            }
        }
        
        return [
            'success' => count($uploadedFiles) > 0,
            'files' => $uploadedFiles,
            'errors' => $errors
        ];
    }
}
