<?php
// Author: Zeday @join.co.id
/**
 * Logout Process
 * Secure logout with logging
 */

require_once 'includes/config.php';
require_once 'includes/security.php';

startSecureSession();

// Log logout event
if (isset($_SESSION['userid'])) {
    logSecurityEvent('user_logout', [
        'user_id' => $_SESSION['userid'],
        'username' => $_SESSION['username'] ?? 'unknown'
    ]);
}

// Destroy session
session_unset();
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page
header("Location: " . APP_URL . "/loginpage.php?logout=1");
exit();
