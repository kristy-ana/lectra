<?php

require_once __DIR__ . '/../header.php';
require_once "../../conn.php";
require_once "../../jwt.php";

// =========================
// GET INPUT (JSON)
// =========================
$data = json_decode(file_get_contents("php://input"), true);

$email = $data["email"] ?? "";
$password = $data["password"] ?? "";

// =========================
// VALIDATION
// =========================
if ($email === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Email and password are required"
    ]);
    exit;
}

// =========================
// FETCH USER
// =========================
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// =========================
// USER CHECK
// =========================
if (!$user) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
    exit;
}

// =========================
// PASSWORD VERIFY
// =========================
$storedHash = $user["password_hash"];

if (!password_verify($password, $storedHash)) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email or password"
    ]);
    exit;
}

// =========================
// CREATE JWT TOKEN
// =========================
$token = createToken($user);

// =========================
// SUCCESS RESPONSE
// =========================
echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "token" => $token,
    "user" => [
        "id" => $user["id"],
        "name" => $user["name"],
        "email" => $user["email"],
        "role" => $user["role"]
    ]
]);

