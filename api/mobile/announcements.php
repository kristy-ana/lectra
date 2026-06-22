<?php
require_once __DIR__ . "/../header.php";

// =========================
// GET ACTIVE ANNOUNCEMENTS
// No auth required — public endpoint for mobile app
// =========================
try {

    $conn = new PDO("mysql:host=localhost;dbname=lectra", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Fetch only emergency/active announcements, newest first
    $stmt = $conn->prepare("
        SELECT id, title, body, is_emergency, created_at 
        FROM announcements 
        WHERE is_emergency = 1 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($announcements)) {
        echo json_encode([
            "status" => "success",
            "count"  => 0,
            "data"   => []
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "count"  => count($announcements),
        "data"   => $announcements
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>