<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'customer_helper.php';

header('Content-Type: application/json; charset=utf-8');

$userId = auth_current_user_id();
$customerName = customer_master_clean($_POST['customer_name'] ?? '', 180);
if ($customerName === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Customer name is required.']);
    exit;
}

$mobileNo = customer_master_clean($_POST['mobile_no'] ?? '', 60);
$gstNo = customer_master_clean($_POST['gst_no'] ?? '', 60);
$sourceKey = sha1('manual|' . user_branch_storage_code($conn, $userId) . '|' . mb_strtolower($customerName) . '|' . $mobileNo . '|' . $gstNo);
$data = [
    'customer_name' => $customerName,
    'depositor_name' => customer_master_clean($_POST['depositor_name'] ?? '', 180),
    'address' => $_POST['address'] ?? '',
    'mobile_no' => $mobileNo,
    'email' => customer_master_clean($_POST['email'] ?? '', 160),
    'member_status' => $_POST['member_status'] ?? 'Non Member',
    'mou_cdc' => customer_master_clean($_POST['mou_cdc'] ?? '', 120),
    'id_no' => customer_master_clean($_POST['id_no'] ?? '', 100),
    'gst_no' => $gstNo,
    'source_key' => $sourceKey,
];

if (!customer_master_upsert($conn, $userId, $data)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save customer.']);
    exit;
}

$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$stmt = $conn->prepare("SELECT id, customer_name, depositor_name, address, mobile_no, email, member_status, mou_cdc, id_no, gst_no FROM sm_customer_master WHERE source_key = ? AND {$scopeSql} LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to fetch saved customer.']);
    exit;
}
$stmt->bind_param('s', $sourceKey);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Customer saved.',
    'customer' => $customer,
]);
?>
