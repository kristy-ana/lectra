<?php
require_once __DIR__ . "/../header.php";
require_once __DIR__ . "/../../conn.php";
require_once __DIR__ . "/../../jwt.php";

$method = $_SERVER['REQUEST_METHOD'];
$id     = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
}

// ─── AUTH CHECK ───────────────────────────────────────────────────────────────
try {
    $authUser = require_once __DIR__ . "/../middleware/auth.php";

    if ($authUser->role !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "status"  => "error",
            "message" => "Forbidden - Admin access required"
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status"  => "error",
        "message" => "Unauthorized - Invalid or missing token"
    ]);
    exit;
}

// ─── ROUTES ───────────────────────────────────────────────────────────────────
try {
    switch ($method) {

        // ── GET ───────────────────────────────────────────────────────────────
        case 'GET':
            if ($id) {
                $stmt = $conn->prepare("SELECT id, name, created_at FROM faculties WHERE id = ?");
                $stmt->execute([$id]);
                $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$faculty) {
                    http_response_code(404);
                    echo json_encode([
                        "status"  => "error",
                        "message" => "Faculty not found"
                    ]);
                    break;
                }

                echo json_encode(["status" => "success", "data" => $faculty]);

            } else {
                $stmt = $conn->prepare("SELECT id, name, created_at FROM faculties ORDER BY name ASC");
                $stmt->execute();
                $faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    "status" => "success",
                    "count"  => count($faculties),
                    "data"   => $faculties
                ]);
            }
            break;

        // ── POST ──────────────────────────────────────────────────────────────
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $name = trim($data['name'] ?? '');

            if ($name === '') {
                http_response_code(400);
                echo json_encode([
                    "status"  => "error",
                    "message" => "Faculty name is required"
                ]);
                break;
            }

            // Duplicate check
            $chk = $conn->prepare("SELECT id FROM faculties WHERE name = ?");
            $chk->execute([$name]);
            if ($chk->fetch()) {
                http_response_code(409);
                echo json_encode([
                    "status"  => "error",
                    "message" => "Faculty already exists"
                ]);
                break;
            }

            $stmt = $conn->prepare("INSERT INTO faculties (name) VALUES (?)");
            $stmt->execute([$name]);
            $newId = $conn->lastInsertId();

            $stmt = $conn->prepare("SELECT id, name, created_at FROM faculties WHERE id = ?");
            $stmt->execute([$newId]);

            echo json_encode([
                "status"  => "success",
                "message" => "Faculty created successfully",
                "data"    => $stmt->fetch(PDO::FETCH_ASSOC)
            ]);
            break;

        // ── PUT ───────────────────────────────────────────────────────────────
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    "status"  => "error",
                    "message" => "id is required in query string"
                ]);
                break;
            }

            $stmt = $conn->prepare("SELECT * FROM faculties WHERE id = ?");
            $stmt->execute([$id]);
            $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$faculty) {
                http_response_code(404);
                echo json_encode([
                    "status"  => "error",
                    "message" => "Faculty not found"
                ]);
                break;
            }

            $data = json_decode(file_get_contents("php://input"), true);
            $name = trim($data['name'] ?? $faculty['name']);

            if ($name === '') {
                http_response_code(400);
                echo json_encode([
                    "status"  => "error",
                    "message" => "Faculty name is required"
                ]);
                break;
            }

            if ($name !== $faculty['name']) {
                $chk = $conn->prepare("SELECT id FROM faculties WHERE name = ? AND id != ?");
                $chk->execute([$name, $id]);
                if ($chk->fetch()) {
                    http_response_code(409);
                    echo json_encode([
                        "status"  => "error",
                        "message" => "Another faculty with that name already exists"
                    ]);
                    break;
                }
            }

            $stmt = $conn->prepare("UPDATE faculties SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);

            echo json_encode([
                "status"  => "success",
                "message" => "Faculty updated successfully"
            ]);
            break;

        // ── DELETE ────────────────────────────────────────────────────────────
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    "status"  => "error",
                    "message" => "id is required in query string"
                ]);
                break;
            }

            $stmt = $conn->prepare("SELECT id FROM faculties WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    "status"  => "error",
                    "message" => "Faculty not found"
                ]);
                break;
            }

            // Prevent delete if departments are linked
            $deptCheck = $conn->prepare("SELECT COUNT(*) FROM departments WHERE faculty_id = ?");
            $deptCheck->execute([$id]);
            if ($deptCheck->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode([
                    "status"  => "error",
                    "message" => "Cannot delete — faculty has departments linked to it"
                ]);
                break;
            }

            $stmt = $conn->prepare("DELETE FROM faculties WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                "status"  => "success",
                "message" => "Faculty deleted successfully"
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                "status"  => "error",
                "message" => "Method not allowed. Use GET, POST, PUT or DELETE."
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}