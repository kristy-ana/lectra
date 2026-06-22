<?php

require_once __DIR__ . "/../header.php";
require_once "../../conn.php";
require_once "../../jwt.php";

$method = $_SERVER['REQUEST_METHOD'];
$id     = null;

// Get ID from query parameter (e.g., /api/timetable/index.php?id=123)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
}

/* =========================
   LOAD MIDDLEWARE (AUTH CHECK)
   ========================= */
try {
    $authUser = require_once __DIR__ . "/../middleware/auth.php";
    //==================
    //ADMIN CHECK //
    //=================//
    if ($authUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Forbidden - Admin access required"
        ]);
        exit;
    }
   

    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized - Invalid or missing token"
    ]);
    exit;
}

try {
    switch ($method) {

        case 'GET':
            if ($id) {

                $stmt = $conn->prepare("
                    SELECT t.id,
                           t.course_id,
                           t.day,
                           t.start_time,
                           t.end_time,
                           t.venue,
                           t.week,
                           t.created_at,
                           c.code AS course_code,
                           c.title AS course_title,
                           d.name AS department_name,
                           l.name AS lecturer_name,
                           l.email AS lecturer_email
                    FROM timetable t
                    JOIN courses c ON t.course_id = c.id
                    JOIN departments d ON c.department_id = d.id
                    LEFT JOIN users l ON c.lecturer_id = l.id
                    WHERE t.id = ?
                ");

                $stmt->execute([$id]);
                $timetable = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$timetable) {
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Timetable entry not found"
                    ]);
                    break;
                }

                echo json_encode([
                    "status" => "success",
                    "timetable" => $timetable
                ]);

            } else {

                // Supported filters: department_id, day, week
                $departmentId = $_GET['department_id'] ?? null;
                $day          = $_GET['day'] ?? null;
                $week         = $_GET['week'] ?? null;

                $sql = "
                    SELECT t.id,
                           t.course_id,
                           t.day,
                           t.start_time,
                           t.end_time,
                           t.venue,
                           t.week,
                           t.created_at,
                           c.code AS course_code,
                           c.title AS course_title,
                           d.name AS department_name,
                           l.name AS lecturer_name,
                           l.email AS lecturer_email
                    FROM timetable t
                    JOIN courses c ON t.course_id = c.id
                    JOIN departments d ON c.department_id = d.id
                    LEFT JOIN users l ON c.lecturer_id = l.id
                    WHERE 1=1
                ";

                $params = [];

                if ($departmentId !== null) {
                    $sql .= " AND c.department_id = ?";
                    $params[] = $departmentId;
                }

                if ($day !== null) {
                    $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    if (!in_array($day, $validDays)) {
                        echo json_encode([
                            "status" => "error",
                            "message" => "Invalid day. Must be one of: Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday"
                        ]);
                        break;
                    }
                    $sql .= " AND t.day = ?";
                    $params[] = $day;
                }

                if ($week !== null) {
                    $sql .= " AND t.week = ?";
                    $params[] = (int)$week;
                }

                $sql .= " ORDER BY t.week, t.day, t.start_time";

                $stmt = $conn->prepare($sql);
                $stmt->execute($params ?: []);
                $timetables = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    "status" => "success",
                    "data" => $timetables,
                    "count" => count($timetables),
                    "filters_applied" => [
                        "department_id" => $departmentId,
                        "day" => $day,
                        "week" => $week
                    ]
                ]);
            }

            break;
        
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            
            $course_id  = trim($data['course_id'] ?? null);
            $day        = trim($data['day'] ?? '');
            $start_time = $data['start_time'] ?? '';
            $end_time   = $data['end_time'] ?? '';
            $venue      = trim($data['venue'] ?? '');
            $week       = $data['week'] ?? null;

            // Basic presence check
            if (!$course_id || $day === '' || $start_time === '' || $end_time === '' || $venue === '' || !$week) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "All fields are required"
                ]);
                break;
            }

            // Day validation
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            if (!in_array($day, $validDays)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Valid day is required (Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday)"
                ]);
                break;
            }

            // Time format validation (HH:MM:SS)
            if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $start_time)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Valid start time is required (HH:MM:SS format)"
                ]);
                break;
            }

            if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $end_time)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Valid end time is required (HH:MM:SS format)"
                ]);
                break;
            }

            // Week validation
            $week = (int)$week;
            if ($week < 1) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Week must be a positive integer"
                ]);
                break;
            }

            // Check if course exists
            $courseCheck = $conn->prepare("SELECT id FROM courses WHERE id = ?");
            $courseCheck->execute([$course_id]);
            if (!$courseCheck->fetch()) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Invalid Course ID"
                ]);
                break;
            }

            // Conflict check (same venue, day, week - overlapping times not allowed)
            $conflictCheck = $conn->prepare("
                SELECT id FROM timetable 
                WHERE venue = ? AND day = ? AND week = ? 
                AND start_time < ? AND end_time > ?
            ");
            $conflictCheck->execute([$venue, $day, $week, $end_time, $start_time]);

            if ($conflictCheck->fetch()) {
                http_response_code(409);
                echo json_encode([
                    "status" => "error",
                    "message" => "Time conflict detected - another timetable entry already exists for this venue at the overlapping time"
                ]);
                break;
            }

            // Insert timetable
            $stmt = $conn->prepare("INSERT INTO timetable (course_id, day, start_time, end_time, venue, week) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $day, $start_time, $end_time, $venue, $week]);

            $timetableId = $conn->lastInsertId();

            $stmt = $conn->prepare("
                SELECT t.id, t.course_id, t.day, t.start_time, t.end_time, t.venue, t.week, t.created_at,
                       c.code AS course_code,
                       c.title AS course_title,
                       d.name AS department_name,
                       l.name AS lecturer_name,
                       l.email AS lecturer_email
                FROM timetable t
                JOIN courses c ON t.course_id = c.id
                JOIN departments d ON c.department_id = d.id
                LEFT JOIN users l ON c.lecturer_id = l.id
                WHERE t.id = ?
            ");
            $stmt->execute([$timetableId]);
            $newTimetable = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success",
                "message" => "Timetable created successfully",
                "timetable" => $newTimetable
            ]);
            break;

        case 'PUT':
         
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "ID required"
                ]);
                break;
            }

            $stmt = $conn->prepare("SELECT * FROM timetable WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    "status" => "error",
                    "message" => "Timetable record not found"
                ]);
                break;
            }
            
            $course_id  = $data['course_id'] ?? $existing['course_id'];
            $day        = trim($data['day'] ?? $existing['day']);
            $start_time = trim($data['start_time'] ?? $existing['start_time']);
            $end_time   = trim($data['end_time'] ?? $existing['end_time']);
            $venue      = trim($data['venue'] ?? $existing['venue']);
            $week       = isset($data['week']) ? (int)$data['week'] : (int)$existing['week'];

            // Basic presence check
            if (!$course_id || $day === '' || $start_time === '' || $end_time === '' || $venue === '' || !$week) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "All fields are required"
                ]);
                break;
            }

            // Day validation
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            if (!in_array($day, $validDays)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Valid day is required (Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday)"
                ]);
                break;
            }

            // Time format validation (HH:MM:SS)
            if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $start_time)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Valid start time is required (HH:MM:SS format)"
                ]);
                break;
            }

            if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $end_time)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Valid end time is required (HH:MM:SS format)"
                ]);
                break;
            }

            // Week validation
            if ($week < 1) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Week must be a positive integer"
                ]);
                break;
            }

            // Check if course ID exists (only if it changed)
            if ($course_id != $existing['course_id']) {
                $courseCheck = $conn->prepare("SELECT id FROM courses WHERE id = ?");
                $courseCheck->execute([$course_id]);
                if (!$courseCheck->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Invalid course_id."
                    ]);
                    break;
                }
            }

            // Conflict check (excluding current entry)
            $conflictCheck = $conn->prepare("
                SELECT id FROM timetable 
                WHERE venue = ? AND day = ? AND week = ? 
                AND start_time < ? AND end_time > ? AND id != ?
            ");
            $conflictCheck->execute([$venue, $day, $week, $end_time, $start_time, $id]);

            if ($conflictCheck->fetch()) {
                http_response_code(409);
                echo json_encode([
                    "status" => "error",
                    "message" => "Time conflict detected - another timetable entry already exists for this venue at the overlapping time"
                ]);
                break;
            }

            $stmt = $conn->prepare("
                UPDATE timetable 
                SET course_id = ?, day = ?, start_time = ?, end_time = ?, venue = ?, week = ? 
                WHERE id = ?
            ");
            $stmt->execute([$course_id, $day, $start_time, $end_time, $venue, $week, $id]);
            
            $stmt = $conn->prepare("
                SELECT t.id, t.course_id, t.day, t.start_time, t.end_time, t.venue, t.week, t.created_at,
                        c.code AS course_code, 
                        c.title AS course_title,
                        d.name AS department_name,
                        l.name AS lecturer_name,
                        l.email AS lecturer_email
                FROM timetable t
                JOIN courses c ON t.course_id = c.id
                JOIN departments d ON c.department_id = d.id
                LEFT JOIN users l ON c.lecturer_id = l.id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $updatedTimetable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "status" => "success",
                "message" => "Timetable updated successfully",
                "schedule" => $updatedTimetable
            ]);
            break;

        case 'DELETE':
            if ($id === null) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "ID required"
                ]);
                break;
            }

            // Check if timetable exists
            $stmt = $conn->prepare("SELECT id FROM timetable WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "status" => "error",
                    "message" => "Timetable not found"
                ]);
                break;
            }

            // Delete timetable
            $del = $conn->prepare("DELETE FROM timetable WHERE id = ?");
            $del->execute([$id]);
            
            echo json_encode([
                "status" => "success",
                "message" => "Timetable deleted successfully"
            ]);
            break;

        default:
            echo json_encode([
                "status" => "error",
                "message" => "Method not allowed. Use GET, POST, PUT, or DELETE."
            ]);
            http_response_code(405);
            break;

    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}