<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'customer_helper.php';
require_once 'user_branch_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!agreement_table_ready($conn)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare agreement table.']);
    exit;
}
customer_master_table_ready($conn);

$userId = auth_current_user_id();
$agreementNo = (int) preg_replace('/[^0-9]/', '', (string) ($_GET['agreement_no'] ?? '0'));
if ($agreementNo <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Enter agreement number to edit.']);
    exit;
}

$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$stmt = $conn->prepare("SELECT * FROM sm_stone_agreements WHERE {$scopeSql} AND agreement_no = ? ORDER BY id DESC LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to load agreement.']);
    exit;
}
$stmt->bind_param('i', $agreementNo);
$stmt->execute();
$agreement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$agreement) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Agreement not found in your branch.']);
    exit;
}

$items = agreement_get_items($conn, (int) $agreement['id'], $agreement);
$firstCertificateNo = 0;
foreach ($items as $item) {
    $certiNo = agreement_ref_certificate_no($item['ref_no'] ?? '');
    if ($certiNo > 0 && ($firstCertificateNo === 0 || $certiNo < $firstCertificateNo)) {
        $firstCertificateNo = $certiNo;
    }
}

$customerId = 0;
$customerName = trim((string) ($agreement['customer_name'] ?? ''));
if ($customerName !== '') {
    $customerScopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
    $customerStmt = $conn->prepare("SELECT id FROM sm_customer_master WHERE customer_name = ? AND (user_id = 0 OR {$customerScopeSql}) ORDER BY user_id = 0 ASC, id DESC LIMIT 1");
    if ($customerStmt) {
        $customerStmt->bind_param('s', $customerName);
        $customerStmt->execute();
        $customerRow = $customerStmt->get_result()->fetch_assoc();
        $customerStmt->close();
        $customerId = (int) ($customerRow['id'] ?? 0);
    }
}

echo json_encode([
    'status' => 'success',
    'agreement' => [
        'id' => (int) $agreement['id'],
        'agreement_no' => (int) $agreement['agreement_no'],
        'collection_center_id' => (int) ($agreement['collection_center_id'] ?? 0),
        'collection_center_code' => (string) ($agreement['collection_center_code'] ?? ''),
        'collection_center_name' => (string) ($agreement['collection_center_name'] ?? ''),
        'docket_no' => (string) ($agreement['docket_no'] ?? ''),
        'customer_master_id' => $customerId,
        'customer_name' => (string) ($agreement['customer_name'] ?? ''),
        'depositor_name' => (string) ($agreement['depositor_name'] ?? ''),
        'member_status' => (string) ($agreement['member_status'] ?? 'Non Member'),
        'mou_cdc' => agreement_mou_tier_code($agreement['mou_cdc'] ?? ''),
        'category' => (string) ($agreement['category'] ?? 'Regular'),
        'gst_no' => (string) ($agreement['gst_no'] ?? ''),
        'address' => (string) ($agreement['address'] ?? ''),
        'mobile_no' => (string) ($agreement['mobile_no'] ?? ''),
        'email' => (string) ($agreement['email'] ?? ''),
        'id_no' => (string) ($agreement['id_no'] ?? ''),
        'agreement_date' => (string) ($agreement['agreement_date'] ?? ''),
        'agreement_time' => (string) ($agreement['agreement_time'] ?? ''),
        'delivery_date' => (string) ($agreement['delivery_date'] ?? ''),
        'delivery_time' => (string) ($agreement['delivery_time'] ?? ''),
        'agreement_status' => agreement_status_clean($agreement['agreement_status'] ?? 'IN_PROCESS'),
        'agreement_status_label' => agreement_status_label($agreement['agreement_status'] ?? 'IN_PROCESS'),
        'status_updated_at' => (string) ($agreement['status_updated_at'] ?? ''),
        'testing_charges' => agreement_money($agreement['testing_charges'] ?? 0),
        'payment_cash' => agreement_money($agreement['payment_cash'] ?? 0),
        'payment_cheque' => agreement_money($agreement['payment_cheque'] ?? 0),
        'payment_neft' => agreement_money($agreement['payment_neft'] ?? 0),
        'payment_card' => agreement_money($agreement['payment_card'] ?? 0),
        'payment_tds' => agreement_money($agreement['payment_tds'] ?? 0),
        'cheque_no' => (string) ($agreement['cheque_no'] ?? ''),
        'due_amount' => agreement_money($agreement['due_amount'] ?? 0),
        'refund_amount' => agreement_money($agreement['refund_amount'] ?? 0),
        'prepared_by' => (string) ($agreement['prepared_by'] ?? ''),
        'remarks' => (string) ($agreement['remarks'] ?? ''),
        'signature_mode' => (string) ($agreement['signature_mode'] ?? 'manual'),
        'customer_signature' => (string) ($agreement['customer_signature'] ?? ''),
        'pcs_total' => (int) ($agreement['pcs_total'] ?? 0),
        'first_certificate_no' => $firstCertificateNo > 0 ? $firstCertificateNo : 1,
        'items' => $items,
    ],
]);
?>
