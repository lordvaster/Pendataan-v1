<?php
// Author: Zeday @join.co.id
/**
 * Database Connection
 * Secure database connection using environment variables
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Create connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        // Log error (don't expose to user)
        error_log("Database connection failed: " . $conn->connect_error);
        
        // Show generic error to user
        if (APP_DEBUG) {
            die("Database connection failed: " . $conn->connect_error);
        } else {
            die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
        }
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}
?>
