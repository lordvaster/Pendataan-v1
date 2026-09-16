<?php
// Author: Zeday @join.co.id
/**
 * Add User API
 * Create new user with role (administrator only)
 */

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/db_connect.php';
require_once '../includes/usersession.php';

// Check if user is administrator
if ($role !== 'administrator') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!isset($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid CSRF token'
    ]);
    exit();
}

$username = sanitizeInput($input['username'] ?? '');
$password = $input['password'] ?? '';
$userRole = $input['role'] ?? 'view_only';

// Validation
if (empty($username) || empty($password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Username dan password harus diisi'
    ]);
    exit();
}

if (strlen($password) < 8) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password minimal 8 karakter'
    ]);
    exit();
}

if (!in_array($userRole, ['administrator', 'view_only'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Role tidak valid'
    ]);
    exit();
}

try {
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Username sudah digunakan'
        ]);
        $stmt->close();
        exit();
    }
    $stmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $username, $hashedPassword, $userRole);
    
    if ($stmt->execute()) {
        // Log activity
        logSecurityEvent('user_created', [
            'admin_id' => $userid,
            'admin_username' => $username,
            'new_user' => $username,
            'role' => $userRole
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'User berhasil ditambahkan'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menambahkan user: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
