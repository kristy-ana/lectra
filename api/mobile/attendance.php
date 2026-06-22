<?php
require_once __DIR__ . '/../header.php';
require_once('../../conn.php');
$authUser = require_once __DIR__ . '/../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Check that the user is a student
if ($authUser->role !== 'student') {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Only students can record attendance"
    ]);
    exit;
}

$data        = json_decode(file_get_contents("php://input"), true);
$timetableId = $data['timetable_id'] ?? null;

if (!$timetableId || !is_numeric($timetableId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "timetable_id is required"
    ]);
    exit;
}

try {
    // Check if timetable id exists
    $check = $conn->prepare("SELECT id FROM timetable WHERE id = ?");
    $check->execute([$timetableId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Timetable not found"
        ]);
        exit;
    }

    // Check if student already recorded attendance for this class
    $dup = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND timetable_id = ?");
    $dup->execute([$authUser->id, $timetableId]);
    if ($dup->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Attendance already recorded for this class"
        ]);
        exit;
    }

    // Insert attendance
    $stmt = $conn->prepare("INSERT INTO attendance (student_id, timetable_id, confirmed_at) VALUES (?, ?, NOW())");
    $stmt->execute([$authUser->id, $timetableId]);

    echo json_encode([
        "success" => true,
        "message" => "Attendance recorded successfully",
        "data"    => [
            "student_id"   => $authUser->id,
            "timetable_id" => (int)$timetableId,
            "confirmed_at" => date("Y-m-d H:i:s")
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}