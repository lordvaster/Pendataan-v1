<?php
// Author: Zeday @join.co.id
/**
 * Update User API
 * Update user password and role (administrator only)
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

$userId = intval($input['id'] ?? 0);
$password = $input['password'] ?? '';
$userRole = $input['role'] ?? '';

// Validation
if ($userId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User ID tidak valid'
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
    // Get current user data
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User tidak ditemukan'
        ]);
        $stmt->close();
        exit();
    }
    
    $userData = $result->fetch_assoc();
    $stmt->close();
    
    // Update user
    if (!empty($password)) {
        // Update with new password
        if (strlen($password) < 8) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Password minimal 8 karakter'
            ]);
            exit();
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $hashedPassword, $userRole, $userId);
    } else {
        // Update role only
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $userRole, $userId);
    }
    
    if ($stmt->execute()) {
        // Log activity
        $logMessage = "Updated user: {$userData['username']} - Role: $userRole";
        if (!empty($password)) {
            $logMessage .= " (password changed)";
        }
        logSecurityEvent('user_updated', [
            'admin_id' => $userid,
            'updated_user' => $userData['username'],
            'new_role' => $userRole,
            'password_changed' => !empty($password)
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'User berhasil diupdate'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal mengupdate user: ' . $stmt->error
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
