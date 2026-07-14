<?php
require_once __DIR__ . '/error_bootstrap.php';
require_once __DIR__ . '/auth.php';
if (!defined('AUTH_ALLOW_PUBLIC') || AUTH_ALLOW_PUBLIC !== true) {
    auth_require_login();
}

// Copy this file to db_connect.php and update credentials on each server.
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sm_iigj_software";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log('IIGJ database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('Database connection failed. Please check hosting database credentials.');
}

if (function_exists('auth_enforce_allowed_ip')) {
    auth_enforce_allowed_ip($conn);
}
?>
