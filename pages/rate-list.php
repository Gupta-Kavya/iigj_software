<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'rate_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!rate_master_table_ready($conn)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Rate master is not ready.']);
    exit;
}

$rates = [];
$result = $conn->query("SELECT id, category, rate_code, rate_member, rate_non_member, description, cdc FROM sm_rate_master ORDER BY description ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rates[] = [
            'id' => (int) $row['id'],
            'category' => (string) ($row['category'] ?? ''),
            'rate_code' => (string) ($row['rate_code'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'rate_member' => (float) ($row['rate_member'] ?? 0),
            'rate_non_member' => (float) ($row['rate_non_member'] ?? 0),
            'cdc' => (string) ($row['cdc'] ?? ''),
        ];
    }
}

echo json_encode(['status' => 'success', 'rates' => $rates]);
?>
