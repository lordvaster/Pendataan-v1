<?php
// Author: Zeday @join.co.id
/**
 * Security Headers
 * Add security headers to all responses
 */

function setSecurityHeaders() {
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data: https:; font-src 'self' data:; frame-src https://www.google.com;");
    
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Prevent clickjacking
    header("X-Frame-Options: SAMEORIGIN");
    
    // XSS Protection
    header("X-XSS-Protection: 1; mode=block");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Remove server signature
    header_remove("X-Powered-By");
}

// Call this function at the beginning of each page
setSecurityHeaders();
?>
