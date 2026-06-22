<?php

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../../jwt.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Content-Type: application/json");

// =========================
// GET AUTH HEADER
// =========================
$headers = getallheaders();

$authHeader =
    $headers["Authorization"] ??
    $headers["authorization"] ??
    "";

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    echo json_encode([
        "status" => "error",
        "message" => "Token missing"
    ]);
    exit;
}

$token = trim($matches[1]);

// =========================
// VERIFY TOKEN
// =========================
try {
    // ✅ IMPORTANT FIX: use $secret_key directly from jwt.php
    $decoded = JWT::decode($token, new Key($secret_key, "HS256"));

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid token",
        "debug" => $e->getMessage()
    ]);
    exit;
}

// =========================
// RETURN USER DATA
// =========================
return $decoded;