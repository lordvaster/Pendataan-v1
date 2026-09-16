<?php
// Author: Zeday @join.co.id
/**
 * Login Process - Direct Database Version
 * Secure login with rate limiting, session regeneration, and logging
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/db_connect.php';

// Start secure session
startSecureSession();

header("Content-Type: application/json");

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

try {
    // Get input JSON from frontend
    $input = json_decode(file_get_contents("php://input"), true);
    
    $username         = sanitizeInput($input["username"] ?? "");
    $password         = $input["password"] ?? ""; // Don't sanitize password
    $recaptchaToken   = $input["g-recaptcha-response"] ?? '';

    // Verify reCAPTCHA (enforcement controlled by RECAPTCHA_ENFORCE in .env)
    if (!verifyRecaptcha($recaptchaToken)) {
        logSecurityEvent('login_recaptcha_failed', ['ip' => $_SERVER['REMOTE_ADDR']]);
        if (RECAPTCHA_ENFORCE) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Verifikasi reCAPTCHA gagal. Silakan coba lagi."]);
            exit;
        }
    }

    // Initial validation
    if (empty($username) || empty($password)) {
        logSecurityEvent('login_attempt_empty_fields', ['username' => $username]);
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username & Password wajib diisi"]);
        exit;
    }

    // Check rate limit for login attempts
    $rateLimitKey = 'login_' . $_SERVER['REMOTE_ADDR'];
    if (!checkRateLimit($rateLimitKey, RATE_LIMIT_LOGIN, 900)) { // 15 minutes window
        logSecurityEvent('login_rate_limit_exceeded', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'username' => $username
        ]);
        http_response_code(429);
        echo json_encode([
            "status" => "error",
            "message" => "Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit."
        ]);
        exit;
    }
    
    // Direct database authentication (no cURL needed)
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logSecurityEvent('login_failed', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'reason' => 'username_not_found'
        ]);
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Username atau password salah"
        ]);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        logSecurityEvent('login_failed', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'reason' => 'wrong_password'
        ]);
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Username atau password salah"
        ]);
        exit;
    }
    
    // If login successful, regenerate session ID (prevent session fixation)
    session_regenerate_id(true);
    
    // Create SESSION
    $_SESSION["login_status"] = true;
    $_SESSION["userid"] = $user["id"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["role"] = $user["role"] ?? 'view_only';
    $_SESSION["created"] = time();
    $_SESSION["last_activity"] = time();
    $_SESSION["ip_address"] = $_SERVER['REMOTE_ADDR'];
    
    // Generate CSRF token for future requests
    generateCSRFToken();
    
    // Log successful login
    logSecurityEvent('login_success', [
        'user_id' => $user["id"],
        'username' => $user["username"],
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Send response back to frontend
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Login berhasil",
        "data" => [
            "username" => $user["username"],
            "csrf_token" => $_SESSION[CSRF_TOKEN_NAME] ?? ''
        ]
    ]);
    
} catch (Exception $e) {
    // Log detailed error
    error_log("Login process exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Log security event
    logSecurityEvent('login_exception', [
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Gagal terhubung ke server. Silakan coba lagi."
    ]);
} finally {
    // Ensure database connection is closed
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
