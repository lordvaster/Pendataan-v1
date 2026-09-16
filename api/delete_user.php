<?php
// Author: Zeday @join.co.id
/**
 * Delete User API
 * Delete user (administrator only)
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

// Validation
if ($userId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User ID tidak valid'
    ]);
    exit();
}

// Prevent deleting own account
if ($userId === $userid) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Tidak dapat menghapus akun sendiri'
    ]);
    exit();
}

try {
    // Get user data before deletion
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
    
    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        // Log activity
        logSecurityEvent('user_deleted', [
            'admin_id' => $userid,
            'deleted_user' => $userData['username'],
            'deleted_user_id' => $userId
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'User berhasil dihapus'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus user: ' . $stmt->error
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
