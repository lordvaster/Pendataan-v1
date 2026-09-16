<?php
// Author: Zeday @join.co.id
/**
 * Get Users API
 * Retrieve all users (administrator only)
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

try {
    $stmt = $conn->prepare("SELECT id, username, role, created_at FROM users ORDER BY id ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $users
    ]);
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve users: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
