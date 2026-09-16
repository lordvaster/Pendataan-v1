<?php
// Author: Zeday @join.co.id
header("Content-Type: application/json");

require_once "../includes/config.php";
require_once "../includes/security.php";
require_once "../includes/db_connect.php";
require_once "../includes/usersession.php";

// Only administrators may create new user accounts
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Akses ditolak."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method tidak diizinkan."]);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Token keamanan tidak valid."]);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? 'view_only';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Username dan password harus diisi."]);
    exit;
}

if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(["status" => "error", "message" => "Username hanya boleh huruf, angka, underscore (3–50 karakter)."]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(["status" => "error", "message" => "Password minimal 8 karakter."]);
    exit;
}

if (!in_array($role, ['administrator', 'view_only'], true)) {
    $role = 'view_only';
}

$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username sudah digunakan."]);
    exit;
}
$check->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $hashedPassword, $role);

if ($stmt->execute()) {
    logSecurityEvent('user_created', ['created_by' => $username, 'new_user' => $username, 'role' => $role]);
    echo json_encode(["status" => "success", "message" => "User berhasil dibuat."]);
} else {
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan ke database."]);
}

$stmt->close();
$conn->close();
