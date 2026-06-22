<?php
require_once __DIR__ . '/../header.php';
require_once('../../conn.php');
$authUser = require_once __DIR__ . '/../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$role         = $_GET['role']         ?? null;
$last_checked = $_GET['last_checked'] ?? null;

// Check role and last_checked are not empty
if (!$role || !$last_checked) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "role and last_checked are required"
    ]);
    exit;
}

// Validate role
$validRoles = ['admin', 'lecturer', 'student'];
if (!in_array($role, $validRoles)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid role. Must be one of: admin, lecturer, student"
    ]);
    exit;
}

// Validate last_checked is a valid datetime
if (!strtotime($last_checked)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "last_checked must be a valid datetime e.g. 2026-06-01 10:00:00"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, title, message, type, target_role, is_active, created_at
        FROM notifications
        WHERE is_active = 1
        AND (target_role = ? OR target_role = 'all')
        AND created_at > ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$role, $last_checked]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success"      => true,
        "role"         => $role,
        "last_checked" => $last_checked,
        "count"        => count($notifications),
        "data"         => $notifications
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}