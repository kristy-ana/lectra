<?php

require_once __DIR__ . '/../header.php';
require_once "../../conn.php";
require_once "../../jwt.php";

// Handle PUT request
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed. Use PUT."
    ]);
    exit;
}

// =========================
// LOAD MIDDLEWARE (AUTH CHECK)
// =========================
$user = require_once __DIR__ . "/../middleware/auth.php";

// =========================
// INPUT
// =========================
$data = json_decode(file_get_contents("php://input"), true);

$name = $data["name"] ?? null;
$email = $data["email"] ?? null;
$role = $data["role"] ?? null;
$department_id = $data["department_id"] ?? null;

// Validate that at least one field is provided for update
if ($name === null && $email === null && $role === null && $department_id === null) {
    echo json_encode([
        "status" => "error",
        "message" => "At least one field (name, email, role, department_id) must be provided for update"
    ]);
    exit;
}

// Validate role if provided
if ($role !== null && !in_array($role, ['admin', 'lecturer', 'student'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid role. Must be admin, lecturer, or student"
    ]);
    exit;
}

// Check if email is being updated and if it already exists (for another user)
if ($email !== null) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user->id]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Email already exists"
        ]);
        exit;
    }
}

// Build update query dynamically
$updateFields = [];
$params = [];

if ($name !== null) {
    $updateFields[] = "name = ?";
    $params[] = $name;
}

if ($email !== null) {
    $updateFields[] = "email = ?";
    $params[] = $email;
}

if ($role !== null) {
    $updateFields[] = "role = ?";
    $params[] = $role;
}

if ($department_id !== null) {
    $updateFields[] = "department_id = ?";
    $params[] = $department_id;
}

if (empty($updateFields)) {
    echo json_encode([
        "status" => "error",
        "message" => "No valid fields to update"
    ]);
    exit;
}

// Add user ID to params for WHERE clause
$params[] = $user->id;

// Execute update
$query = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
$stmt = $conn->prepare($query);

try {
    $stmt->execute($params);
    
    // Fetch updated user data
    $stmt = $conn->prepare("SELECT id, name, email, role, department_id FROM users WHERE id = ?");
    $stmt->execute([$user->id]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "message"=> "Profile updated successfully",
        "user" => $updatedUser
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update profile: " . $e->getMessage()
    ]);
}