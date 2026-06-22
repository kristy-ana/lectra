<?php
// Enable CORS and handle OPTIONS
require_once __DIR__ . '/../header.php';

// Include database connection
require_once __DIR__ . '/../../conn.php';

// Include auth middleware (returns $authUser)
$authUser = require_once __DIR__ . '/../middleware/auth.php';

// Get query parameters
$departmentId = $_GET['department_id'] ?? null;
$week = $_GET['week'] ?? null;

// Validate required paramete1rs
if ($departmentId === null || !is_numeric($departmentId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "department_id is required and must be a number"
    ]);
    exit;
}

if ($week === null || !is_numeric($week)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "week is required and must be a number"
    ]);
    exit;
}

$departmentId = (int)$departmentId;
$week = (int)$week;

// Optional: check if departmentid exists
  $stmta = $conn->prepare("SELECT id FROM departments WHERE id = ?");
    $stmta->execute([$departmentId]);
    if (!$stmta->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "DepartmentID not found"
        ]);
        exit;
    }

try {
      // Fetch timetable for the department and week
    $stmt = $conn->prepare("
        SELECT t.id, t.course_id, t.day, t.start_time, t.end_time, t.venue, t.week, t.created_at,
               c.code AS course_code, c.title AS course_title,
               d.name AS department_name,
               l.name AS lecturer_name
        FROM timetable t
        JOIN courses c ON t.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        LEFT JOIN users l ON c.lecturer_id = l.id
        WHERE c.department_id = ? AND t.week = ?
        ORDER BY t.day, t.start_time
    ");
    $stmt->execute([$departmentId, $week]);
    $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "data" => $timetable
    ]);
} 
catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}