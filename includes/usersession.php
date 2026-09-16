<?php
// Author: Zeday @join.co.id
/**
 * User Session Management
 * Secure session handling with timeout and validation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

// Start secure session
startSecureSession();

// Check if user is logged in
if (!isset($_SESSION["login_status"]) || $_SESSION["login_status"] !== true) {
    // Log unauthorized access attempt
    logSecurityEvent('unauthorized_access_attempt', [
        'requested_page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
    
    // Redirect to login page
    header("Location: " . APP_URL . "/loginpage.php");
    exit;
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
    // Session expired
    logSecurityEvent('session_expired', [
        'user_id' => $_SESSION["userid"] ?? 'unknown',
        'username' => $_SESSION["username"] ?? 'unknown'
    ]);
    
    session_unset();
    session_destroy();
    
    header("Location: " . APP_URL . "/loginpage.php?expired=1");
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Validate session IP (optional, can be disabled if users have dynamic IPs)
if (!isset($_SESSION['ip_address'])) {
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
} elseif ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
    // IP address changed - possible session hijacking
    logSecurityEvent('session_ip_mismatch', [
        'user_id' => $_SESSION["userid"] ?? 'unknown',
        'original_ip' => $_SESSION['ip_address'],
        'current_ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Uncomment below to enforce IP validation
    // session_unset();
    // session_destroy();
    // header("Location: " . APP_URL . "/loginpage.php?security=1");
    // exit;
}

// Set user variables
$username = escapeHtml($_SESSION["username"] ?? '');
$userid = $_SESSION["userid"] ?? 0;
$role = $_SESSION["role"] ?? 'view_only';
?>
