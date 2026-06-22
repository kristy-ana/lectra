<?php
require_once __DIR__ . "/../header.php";
require_once __DIR__ . "/../../jwt.php";

// =========================
// AUTH CHECK
// GET is public — all others require admin
// =========================
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if ($method !== 'GET') {
    $authUser = require __DIR__ . "/../middleware/auth.php";

    if ($authUser->role !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "status"  => "error",
            "message" => "Forbidden - Admin access required"
        ]);
        exit;
    }
}

// =========================
// DATABASE + ROUTING
// =========================
try {

    $conn = new PDO("mysql:host=localhost;dbname=lectra", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($method) {

        // ─────────────────────────────────────
        // GET → everyone can read announcements
        // ─────────────────────────────────────
        case 'GET':
            if ($id) {
                $stmt = $conn->prepare("SELECT id, title, body, is_emergency, created_at FROM announcements WHERE id = ?");
                $stmt->execute([$id]);
                $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($announcement ?: ['error' => 'Announcement not found']);
            } else {
                $stmt = $conn->prepare("SELECT id, title, body, is_emergency, created_at FROM announcements ORDER BY created_at DESC");
                $stmt->execute();
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        // ─────────────────────────────────────
        // POST → create announcement (admin only)
        // ─────────────────────────────────────
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $title        = trim($data['title'] ?? '');
            $body         = trim($data['body'] ?? '');
            $is_emergency = isset($data['is_emergency']) && $data['is_emergency'] == '1' ? 1 : 0;

            if (empty($title) || empty($body)) {
                echo json_encode(['error' => 'title and body are required']);
                break;
            }

            $stmt = $conn->prepare("INSERT INTO announcements (title, body, is_emergency) VALUES (?, ?, ?)");
            $stmt->execute([$title, $body, $is_emergency]);
            echo json_encode(['message' => 'Announcement created successfully']);
            break;

        // ─────────────────────────────────────
        // PUT → update announcement (admin only)
        // ─────────────────────────────────────
        case 'PUT':
            if (!$id) { echo json_encode(['error' => 'ID required']); break; }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$announcement) { echo json_encode(['error' => 'Announcement not found']); break; }

            $title        = trim($data['title'] ?? $announcement['title']);
            $body         = trim($data['body']  ?? $announcement['body']);
            $is_emergency = isset($data['is_emergency']) ? ($data['is_emergency'] == '1' ? 1 : 0) : $announcement['is_emergency'];

            if (empty($title) || empty($body)) {
                echo json_encode(['error' => 'title and body are required']); break;
            }

            $stmt = $conn->prepare("UPDATE announcements SET title = ?, body = ?, is_emergency = ? WHERE id = ?");
            $stmt->execute([$title, $body, $is_emergency, $id]);
            echo json_encode(['message' => 'Announcement updated successfully']);
            break;

        // ─────────────────────────────────────
        // DELETE → remove announcement (admin only)
        // ─────────────────────────────────────
        case 'DELETE':
            if (!$id) { echo json_encode(['error' => 'ID required']); break; }

            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'Announcement deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>