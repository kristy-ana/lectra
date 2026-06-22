<?php
// Enable CORS and handle OPTIONS
require_once __DIR__ . '/../header.php';

// Include database connection
require_once __DIR__ . '/../../conn.php';

// Include auth middleware (returns $authUser)
$authUser = require_once __DIR__ . '/../middleware/auth.php';

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);
$device_token = $data['device_token'] ?? null;

// Validate device token
if ($device_token === null || !is_string($device_token)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "device_token is required and must be a string"
    ]);
    exit;
}

// Optional: validate token length or format? We'll just store it.

try {
    // Update the user's device token
    $stmt = $conn->prepare("UPDATE users SET device_token = ? WHERE id = ?");
    $stmt->execute([$device_token, $authUser->id]);
    
    // Check if any row was affected
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);
        exit;
    }
    
    echo json_encode([
        "success" => true,
        "data" => []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}