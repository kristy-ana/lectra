<?php

require_once __DIR__ . "/../header.php";
require_once "../../conn.php";
require_once "../../jwt.php";

$method = $_SERVER['REQUEST_METHOD'];
$id     = null;

// Get ID from query parameter (e.g., /api/courses/index.php?id=123)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
}

/* =========================
   LOAD MIDDLEWARE (AUTH CHECK)
   ========================= */
try {
    $authUser = require_once __DIR__ . "/../middleware/auth.php";
    
    // =========================
    // ADMIN CHECK
    // =========================
    if ($authUser->role !== 'admin') {
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
                 // GET /api/courses/:id - Get specific course with lecturer and department details
                 /* 
                    This query gets course details plus related lecturer and department information:
                    - c.*: basic course fields (id, code, title, lecturer_id, department_id, created_at)
                    - l.name AS lecturer_name: lecturer's name from users table
                    - l.email AS lecturer_email: lecturer's email from users table  
                    - d.name AS department_name: department name from departments table
                    LEFT JOIN is used so courses without lecturers still appear (lecturer fields will be NULL)
                 */
                 $stmt = $conn->prepare("
                     SELECT c.id, c.code, c.title, c.lecturer_id, c.department_id, c.created_at,
                            l.name AS lecturer_name, l.email AS lecturer_email,
                            d.name AS department_name
                     FROM courses c
                     LEFT JOIN users l ON c.lecturer_id = l.id
                     LEFT JOIN departments d ON c.department_id = d.id
                     WHERE c.id = ?
                 ");
                $stmt->execute([$id]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$course) {
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Course not found"
                    ]);
                    break;
                }
                
                echo json_encode([
                    "status" => "success",
                    "course" => $course
                ]);
            } else {
             // GET /api/courses - List all courses with lecturer and department details
             /* 
                Same query as above but for all courses, ordered by course code
             */
             $stmt = $conn->prepare("
                 SELECT c.id, c.code, c.title, c.lecturer_id, c.department_id, c.created_at,
                        l.name AS lecturer_name, l.email AS lecturer_email,
                        d.name AS department_name
                 FROM courses c
                 LEFT JOIN users l ON c.lecturer_id = l.id
                 LEFT JOIN departments d ON c.department_id = d.id
                 ORDER BY c.code
             ");
                $stmt->execute();
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    "status" => "success",
                    "data" => $courses,
                    "count" => count($courses)
                ]);
            }
            break;

        case 'POST':
            // POST /api/courses - Create new course
            // Handle JSON data
            $data = json_decode(file_get_contents("php://input"), true);
            
            $code = trim($data['code'] ?? '');
            $title = trim($data['title'] ?? '');
            $lecturer_id = $data['lecturer_id'] ?? null;
            $department_id = $data['department_id'] ?? null;

            // Validation
            if ($code === '' || $title === '' || $department_id === null) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Course code, title, and department ID are required"
                ]);
                break;
            }

            // Validate department exists
            $deptCheck = $conn->prepare("SELECT id FROM departments WHERE id = ?");
            $deptCheck->execute([$department_id]);
            if (!$deptCheck->fetch()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Invalid department ID"
                ]);
                break;
            }

            // Validate lecturer if provided (must be a lecturer role)
            if ($lecturer_id !== null) {
                $lecturerCheck = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND role = 'lecturer'");
                $lecturerCheck->execute([$lecturer_id]);
                if (!$lecturerCheck->fetch()) {
                    echo json_encode([
                        "status" => "error",
                        "message" => "Invalid lecturer ID - must be a user with lecturer role"
                    ]);
                    break;
                }
            }

            // Check if course code already exists
            $codeCheck = $conn->prepare("SELECT id FROM courses WHERE code = ?");
            $codeCheck->execute([$code]);
            if ($codeCheck->fetch()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Course code already exists"
                ]);
                break;
            }

            // Create course
            $stmt = $conn->prepare("INSERT INTO courses (code, title, lecturer_id, department_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $title, $lecturer_id, $department_id]);
            
            $courseId = $conn->lastInsertId();
            
            // Get created course with details
            /* 
               Fetch the newly created course with lecturer and department details
               using the same JOIN pattern as above
            */
            $stmt = $conn->prepare("
                SELECT c.id, c.code, c.title, c.lecturer_id, c.department_id, c.created_at,
                       l.name AS lecturer_name, l.email AS lecturer_email,
                       d.name AS department_name
                FROM courses c
                LEFT JOIN users l ON c.lecturer_id = l.id
                LEFT JOIN departments d ON c.department_id = d.id
                WHERE c.id = ?
            ");
            $stmt->execute([$courseId]);
            $newCourse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "status" => "success",
                "message" => "Course created successfully",
                "course" => $newCourse
            ]);
            break;

        case 'PUT':
            // PUT /api/courses/:id - Update course (Simplified for beginners)
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$id) {
                echo json_encode([
                    "status" => "error",
                    "message" => "ID required"
                ]);
                break;
            }

            // Find existing course FIRST
            $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Course not found"
                ]);
                break;
            }

            // Read fields — fall back to existing data when a field is left blank
            $code = trim($data['code'] ?? $course['code']);
            $title = trim($data['title'] ?? $course['title']);
            $lecturer_id = $data['lecturer_id'] ?? $course['lecturer_id'];
            $department_id = $data['department_id'] ?? $course['department_id'];

            // Validation
            if ($code === '' || $title === '' || $department_id === null) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Course code, title, and department ID are required"
                ]);
                break;
            }

            // Validate department exists
            $deptCheck = $conn->prepare("SELECT id FROM departments WHERE id = ?");
            $deptCheck->execute([$department_id]);
            if (!$deptCheck->fetch()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Invalid department ID"
                ]);
                break;
            }

            // Validate lecturer if provided (must be a lecturer role)
            if ($lecturer_id !== null) {
                $lecturerCheck = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND role = 'lecturer'");
                $lecturerCheck->execute([$lecturer_id]);
                if (!$lecturerCheck->fetch()) {
                    echo json_encode([
                        "status" => "error",
                        "message" => "Invalid lecturer ID - must be a user with lecturer role"
                    ]);
                    break;
                }
            }

            // Check if code is being updated and if it already exists (for another course)
            if ($code !== $course['code']) {
                $codeCheck = $conn->prepare("SELECT id FROM courses WHERE code = ? AND id != ?");
                $codeCheck->execute([$code, $id]);
                if ($codeCheck->fetch()) {
                    echo json_encode([
                        "status" => "error",
                        "message" => "Course code already exists"
                    ]);
                    break;
                }
            }

            // Update course
            $stmt = $conn->prepare("UPDATE courses SET code = ?, title = ?, lecturer_id = ?, department_id = ? WHERE id = ?");
            $stmt->execute([$code, $title, $lecturer_id, $department_id, $id]);
            
            // Get updated course with details
            /* 
               Fetch the updated course with lecturer and department details
               using the same JOIN pattern as above
            */
            $stmt = $conn->prepare("
                SELECT c.id, c.code, c.title, c.lecturer_id, c.department_id, c.created_at,
                       l.name AS lecturer_name, l.email AS lecturer_email,
                       d.name AS department_name
                FROM courses c
                LEFT JOIN users l ON c.lecturer_id = l.id
                LEFT JOIN departments d ON c.department_id = d.id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $updatedCourse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "status" => "success",
                "message" => "Course updated successfully",
                "course" => $updatedCourse
            ]);
            break;

        case 'DELETE':
            // DELETE /api/courses/:id - Delete course
            if ($id === null) {
                echo json_encode([
                    "status" => "error",
                    "message" => "ID required"
                ]);
                break;
            }

            // Check if course exists
            $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Course not found"
                ]);
                break;
            }

            // Check if course has timetable entries (prevent deletion if in use)
            $timetableCheck = $conn->prepare("SELECT COUNT(*) FROM timetable WHERE course_id = ?");
            $timetableCheck->execute([$id]);
            if ($timetableCheck->fetchColumn() > 0) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Cannot delete course - timetable entries exist for this course"
                ]);
                break;
            }

            // Delete course
            $del = $conn->prepare("DELETE FROM courses WHERE id = ?");
            $del->execute([$id]);
            
            echo json_encode([
                "status" => "success",
                "message" => "Course deleted successfully"
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