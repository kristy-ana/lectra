<?php

require_once __DIR__ . "/../header.php";
require_once "../../conn.php";
require_once "../../jwt.php";

$data = json_decode(file_get_contents("php://input"), true);

$name = $data["name"] ?? null;
$email = $data["email"] ?? null;
$password = $data["password"] ?? null;
$role = $data["role"] ?? "student";
$department_id = $data["department_id"] ?? null;

$authUser = require_once __DIR__ . "/../middleware/auth.php";

if ($authUser->role !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Forbidden - Admin access required"
    ]);
    exit;
}

if (!$name || !$email || !$password) {
    echo json_encode([
        "status" => "error",
        "message" => "Name, email and password are required"
    ]);
    exit;
}

if (!in_array($role, ['admin', 'lecturer', 'student'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid role. Must be admin, lecturer, or student"
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode([
        "status" => "error",
        "message" => "Email already exists"
    ]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (name, email, password_hash, role, department_id)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $name,
    $email,
    $hashedPassword,
    $role,
    $department_id
]);

$userId = $conn->lastInsertId();

echo json_encode([
    "status" => "success",
    "message" => "User registered successfully",
    "user" => [
        "id" => $userId,
        "name" => $name,
        "email" => $email,
        "role" => $role,
        "department_id" => $department_id
    ]
]);