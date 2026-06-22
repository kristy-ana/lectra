    <?php
    // store user data across pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// CONNECT TO DATABASE
    $conn = new PDO("mysql:host=localhost;dbname=lectra", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ?>