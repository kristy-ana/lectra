<?php
include 'conn.php';

require_once __DIR__ . '/../header.php';
require_once "../../conn.php";
require_once "../../jwt.php";

// =========================
// LOAD MIDDLEWARE (AUTH CHECK)
// =========================
$user = require_once __DIR__ . "/../middleware/auth.php";

// =========================
// INPUT
// =========================
$data = json_decode(file_get_contents("php://input"), true);

$old_password = $data["old_password"] ?? "";
$new_password = $data["new_password"] ?? "";

if ($old_password === "" || $new_password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Old and new password required"
    ]);
    exit;
}

// =========================
// GET USER FROM DB
// =========================
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user->id]);

$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dbUser) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
    exit;
}

// =========================
// VERIFY OLD PASSWORD
// =========================
if (!password_verify($old_password, $dbUser["password_hash"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Old password is incorrect"
    ]);
    exit;
}

// =========================
// UPDATE PASSWORD
// =========================
$newHash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->execute([$newHash, $user->id]);

echo json_encode([
    "status" => "success",
    "message" => "Password updated successfully"
]);