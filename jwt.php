<?php
require_once __DIR__ . "/vendor/autoload.php";
use Firebase\JWT\JWT;

$secret_key = "lecktra_super_secret_key_2026_do_not_share_key_xyz_abc_123";

// ✅ PREVENT DOUBLE DECLARATION
if (!function_exists('createToken')) {

    function createToken($user)
    {
        global $secret_key;

        $payload = [
            "id" => $user["id"],
            "email" => $user["email"],
            "role" => $user["role"],
            "iat" => time(),
            "exp" => time() + 3600
        ];

        return JWT::encode($payload, $secret_key, "HS256");
    }
}