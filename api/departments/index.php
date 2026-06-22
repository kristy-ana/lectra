<?php

require_once __DIR__ . "/../header.php";
require_once "../../conn.php";
require_once "../../jwt.php";

$method = $_SERVER['REQUEST_METHOD'];
$id     = null;

// Get ID from query parameter (e.g., /api/departments/index.php?id=123)
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
                // GET /api/departments/:id - Get specific department
                $stmt = $conn->prepare("SELECT id, name, created_at FROM departments WHERE id = ?");
                $stmt->execute([$id]);
                $department = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$department) {
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Department not found"
                    ]);
                    break;
                }
                
                echo json_encode([
                    "status" => "success",
                    "department" => $department
                ]);
            } else {
                // GET /api/departments - List all departments
                $stmt = $conn->prepare("SELECT id, name, created_at FROM departments ORDER BY name");
                $stmt->execute();
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    "status" => "success",
                    "data" => $departments,
                    "count" => count($departments)
                ]);
            }
            break;

        case 'POST':
            // POST /api/departments - Create new department
            // Handle JSON data
            $data = json_decode(file_get_contents("php://input"), true);
            
            $name = trim($data['name'] ?? '');

            // Validation
            if ($name === '') {
                echo json_encode([
                    "status" => "error",
                    "message" => "Department name is required"
                ]);
                break;
            }

            // Check if department already exists
            $chk = $conn->prepare("SELECT id FROM departments WHERE name = ?");
            $chk->execute([$name]);
            if ($chk->fetch()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Department already exists"
                ]);
                break;
            }

            // Create department
            $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->execute([$name]);
            
            $departmentId = $conn->lastInsertId();
            
            // Get created department
            $stmt = $conn->prepare("SELECT id, name, created_at FROM departments WHERE id = ?");
            $stmt->execute([$departmentId]);
            $newDepartment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "status" => "success",
                "message" => "Department created successfully",
                "department" => $newDepartment
            ]);
            break;

        case 'PUT':
            // PUT /api/departments/:id - Update department (Simplified for beginners)
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$id) {
                echo json_encode([
                    "status" => "error",
                    "message" => "ID required"
                ]);
                break;
            }

            // Find existing department FIRST
            $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$department) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Department not found"
                ]);
                break;
            }

            // Read fields — fall back to existing data when a field is left blank
            $name = trim($data['name'] ?? $department['name']);

            // Validation
            if ($name === '') {
                echo json_encode([
                    "status" => "error",
                    "message" => "Department name is required"
                ]);
                break;
            }

            // Check if name is being updated and if it already exists (for another department)
            if ($name !== $department['name']) {
                $chk = $conn->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
                $chk->execute([$name, $id]);
                if ($chk->fetch()) {
                    echo json_encode([
                        "status" => "error",
                        "message" => "Department already exists"
                    ]);
                    break;
                }
            }

            // Update department
            $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);

            echo json_encode([
                "status" => "success",
                "message" => "Department updated successfully"
            ]);
            break;

        case 'DELETE':
            // DELETE /api/departments/:id - Delete department
            if ($id === null) {
                echo json_encode([
                    "status" => "error",
                    "message" => "ID required"
                ]);
                break;
            }

            // Check if department exists
            $stmt = $conn->prepare("SELECT id FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Department not found"
                ]);
                break;
            }

            // Check if department has users (prevent deletion if in use)
            $userCheck = $conn->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
            $userCheck->execute([$id]);
            if ($userCheck->fetchColumn() > 0) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Cannot delete department - users are assigned to it"
                ]);
                break;
            }

            // Check if department has courses (prevent deletion if in use)
            $courseCheck = $conn->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
            $courseCheck->execute([$id]);
            if ($courseCheck->fetchColumn() > 0) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Cannot delete department - courses are assigned to it"
                ]);
                break;
            }

            // Delete department
            $del = $conn->prepare("DELETE FROM departments WHERE id = ?");
            $del->execute([$id]);
            
            echo json_encode([
                "status" => "success",
                "message" => "Department deleted successfully"
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