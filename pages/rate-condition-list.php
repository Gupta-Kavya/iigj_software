<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'rate_condition_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!rate_condition_table_ready($conn)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Rate condition master is not ready.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'rules' => rate_condition_list($conn, true),
]);
?>
