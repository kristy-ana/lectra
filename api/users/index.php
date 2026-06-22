<?php
require_once __DIR__ . "/../header.php";
require_once __DIR__ . "/../../jwt.php";

// =========================
// AUTH + ADMIN CHECK
// =========================
$authUser = require __DIR__ . "/../middleware/auth.php";

if ($authUser->role !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status"  => "error",
        "message" => "Forbidden - Admin access required"
    ]);
    exit;
}

// =========================
// DATABASE + ROUTING
// =========================
try {

    $method = $_SERVER['REQUEST_METHOD'];
    $id     = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

    $conn = new PDO("mysql:host=localhost;dbname=lectra", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($method) {

        // ─────────────────────────────────────
        // GET → all users or single user
        // ─────────────────────────────────────
        case 'GET':
            if ($id) {
                $stmt = $conn->prepare("SELECT id, name, email, role, department_id FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($user ?: ['error' => 'User not found']);
            } else {
                $stmt = $conn->prepare("SELECT id, name, email, role, department_id FROM users");
                $stmt->execute();
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        // ─────────────────────────────────────
        // POST → create user
        // ─────────────────────────────────────
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $name          = trim($data['name'] ?? '');
            $email         = trim($data['email'] ?? '');
            $password      = $data['password'] ?? '';
            $role          = $data['role'] ?? 'user';
            $department_id = $data['department_id'] ?? null;

            if (empty($name) || empty($email) || empty($password)) {
                echo json_encode(['error' => 'name, email and password are required']);
                break;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['error' => 'Invalid email format']);
                break;
            }

            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                echo json_encode(['error' => 'Email already exists']);
                break;
            }

            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, department_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department_id]);
            echo json_encode(['message' => 'User created successfully']);
            break;

        // ─────────────────────────────────────
        // PUT → update user
        // ─────────────────────────────────────
        case 'PUT':
            if (!$id) { echo json_encode(['error' => 'ID required']); break; }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) { echo json_encode(['error' => 'User not found']); break; }

            $name          = trim($data['name']  ?? $user['name']);
            $email         = trim($data['email'] ?? $user['email']);
            $role          = $data['role'] ?? $user['role'];
            $department_id = $data['department_id'] ?? $user['department_id'];

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['error' => 'Invalid email format']); break;
            }

            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, department_id = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $department_id, $id]);
            echo json_encode(['message' => 'User updated successfully']);
            break;

        // ─────────────────────────────────────
        // DELETE → delete user
        // ─────────────────────────────────────
        case 'DELETE':
            if (!$id) { echo json_encode(['error' => 'ID required']); break; }

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'User deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>