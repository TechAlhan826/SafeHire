<?php
/**
 * Helper Functions
 * 
 * Utility functions for general use throughout the application
 */
class Helpers {
    /**
     * Generate a random string
     * 
     * @param int $length Length of the string
     * @param string $type Type of string (alpha, numeric, alphanumeric, mixed)
     * @return string Random string
     */
    public static function generateRandomString($length = 10, $type = 'alphanumeric') {
        $characters = '';
        
        switch ($type) {
            case 'alpha':
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                break;
                
            case 'numeric':
                $characters = '0123456789';
                break;
                
            case 'mixed':
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+';
                break;
                
            case 'alphanumeric':
            default:
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                break;
        }
        
        $string = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[mt_rand(0, $max)];
        }
        
        return $string;
    }
    
    /**
     * Format currency
     * 
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted currency
     */
    public static function formatCurrency($amount, $currency = 'USD') {
        $symbol = self::getCurrencySymbol($currency);
        
        return $symbol . number_format($amount, 2);
    }
    
    /**
     * Get currency symbol
     * 
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    public static function getCurrencySymbol($currency) {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'JPY' => '¥',
            'CNY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'SGD' => 'S$',
            'NZD' => 'NZ$'
        ];
        
        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }
    
    /**
     * Format date
     * 
     * @param string $date Date to format
     * @param string $format Format string
     * @return string Formatted date
     */
    public static function formatDate($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return '';
        }
        
        $datetime = new DateTime($date);
        return $datetime->format($format);
    }
    
    /**
     * Format time ago
     * 
     * @param string $datetime Date/time to format
     * @return string Formatted time ago string
     */
    public static function timeAgo($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } else {
            return self::formatDate($datetime, 'M j, Y');
        }
    }
    
    /**
     * Sanitize a string for output
     * 
     * @param string $string String to sanitize
     * @return string Sanitized string
     */
    public static function sanitizeOutput($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Truncate a string to a specific length
     * 
     * @param string $string String to truncate
     * @param int $length Maximum length
     * @param string $append String to append if truncated
     * @return string Truncated string
     */
    public static function truncate($string, $length = 100, $append = '...') {
        if (strlen($string) <= $length) {
            return $string;
        }
        
        $string = substr($string, 0, $length);
        $pos = strrpos($string, ' ');
        
        if ($pos !== false) {
            $string = substr($string, 0, $pos);
        }
        
        return $string . $append;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    public static function getClientIP() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return $ip;
    }
    
    /**
     * Calculate percentage
     * 
     * @param float $value Value
     * @param float $total Total
     * @param int $precision Decimal precision
     * @return float Percentage
     */
    public static function calculatePercentage($value, $total, $precision = 2) {
        if ($total == 0) {
            return 0;
        }
        
        return round(($value / $total) * 100, $precision);
    }
    
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Convert array to CSV string
     * 
     * @param array $array Array to convert
     * @return string CSV string
     */
    public static function arrayToCsv($array) {
        if (empty($array)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Add header row
        fputcsv($output, array_keys(reset($array)));
        
        // Add data rows
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Check if a string contains another string
     * 
     * @param string $haystack String to search in
     * @param string $needle String to search for
     * @param bool $case_sensitive Whether the search should be case sensitive
     * @return bool True if needle is found, false otherwise
     */
    public static function stringContains($haystack, $needle, $case_sensitive = false) {
        if ($case_sensitive) {
            return strpos($haystack, $needle) !== false;
        } else {
            return stripos($haystack, $needle) !== false;
        }
    }
    
    /**
     * Convert bytes to human-readable format
     * 
     * @param int $bytes Bytes to convert
     * @param int $precision Decimal precision
     * @return string Human-readable size
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?>
