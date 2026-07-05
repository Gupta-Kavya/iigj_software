<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'customer_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!customer_master_table_ready($conn)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Customer master is not ready.']);
    exit;
}

$userId = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$query = customer_master_clean($_GET['q'] ?? '', 80);
$like = '%' . $query . '%';
$rows = [];

if ($query === '') {
    $stmt = $conn->prepare("SELECT id, customer_name, depositor_name, address, mobile_no, email, member_status, mou_cdc, id_no, gst_no
        FROM sm_customer_master
        WHERE (user_id = 0 OR {$scopeSql})
        ORDER BY customer_name ASC
        LIMIT 25");
} else {
    $stmt = $conn->prepare("SELECT id, customer_name, depositor_name, address, mobile_no, email, member_status, mou_cdc, id_no, gst_no
        FROM sm_customer_master
        WHERE (user_id = 0 OR {$scopeSql})
          AND (customer_name LIKE ? OR mobile_no LIKE ? OR email LIKE ? OR gst_no LIKE ?)
        ORDER BY CASE WHEN customer_name LIKE ? THEN 0 ELSE 1 END, customer_name ASC
        LIMIT 25");
    $prefix = $query . '%';
    $stmt->bind_param('sssss', $like, $like, $like, $like, $prefix);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'customer_name' => (string) $row['customer_name'],
            'depositor_name' => (string) ($row['depositor_name'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'mobile_no' => (string) ($row['mobile_no'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'member_status' => (string) ($row['member_status'] ?? 'Non Member'),
            'mou_cdc' => (string) ($row['mou_cdc'] ?? ''),
            'id_no' => (string) ($row['id_no'] ?? ''),
            'gst_no' => (string) ($row['gst_no'] ?? ''),
        ];
    }
    $stmt->close();
}

echo json_encode(['status' => 'success', 'customers' => $rows]);
?>
