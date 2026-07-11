<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'user_branch_helper.php';
require_once 'waapi_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

if (!agreement_table_ready($conn)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare agreement table.']);
    exit;
}

$userId = auth_current_user_id();
$agreementId = max(0, (int) ($_POST['agreement_id'] ?? 0));
$newStatus = agreement_status_clean($_POST['agreement_status'] ?? '');
if ($agreementId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Load an agreement before updating status.']);
    exit;
}

$scopeSql = user_branch_scope_sql($conn, $userId, 'a.user_id');
$stmt = $conn->prepare("SELECT a.*, u.company_name FROM sm_stone_agreements a LEFT JOIN sm_users u ON u.id = a.user_id WHERE a.id = ? AND {$scopeSql} LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to load agreement.']);
    exit;
}
$stmt->bind_param('i', $agreementId);
$stmt->execute();
$agreement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$agreement) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Agreement not found in your branch.']);
    exit;
}

$nowDate = date('Y-m-d');
$nowTime = date('H:i');
$isDelivered = $newStatus === 'DELIVERED' ? 1 : 0;
$deliveryDate = $isDelivered ? $nowDate : (($agreement['delivery_date'] ?? '') !== '' ? (string) $agreement['delivery_date'] : null);
$deliveryTime = $isDelivered ? $nowTime : (string) ($agreement['delivery_time'] ?? '');

$update = $conn->prepare("UPDATE sm_stone_agreements
    SET agreement_status = ?, status_updated_at = NOW(), delivered = ?, delivery_date = ?, delivery_time = ?, updated_at = NOW()
    WHERE id = ?");
if (!$update) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare status update.']);
    exit;
}
$update->bind_param('sissi', $newStatus, $isDelivered, $deliveryDate, $deliveryTime, $agreementId);
if (!$update->execute()) {
    $message = $update->error;
    $update->close();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to update status: ' . $message]);
    exit;
}
$update->close();

$agreement['agreement_status'] = $newStatus;
$agreement['delivered'] = $isDelivered;
$agreement['delivery_date'] = $deliveryDate;
$agreement['delivery_time'] = $deliveryTime;

function agreement_status_whatsapp_message($agreement)
{
    $customerName = trim((string) ($agreement['customer_name'] ?? 'Customer'));
    if ($customerName === '') {
        $customerName = 'Customer';
    }
    $agreementNo = (int) ($agreement['agreement_no'] ?? 0);
    $status = agreement_status_label($agreement['agreement_status'] ?? 'IN_PROCESS');
    $pcsTotal = (int) ($agreement['pcs_total'] ?? 0);
    $amount = agreement_money($agreement['testing_charges'] ?? 0);
    $deliveryDate = agreement_date_display($agreement['delivery_date'] ?? '');
    $deliveryTime = trim((string) ($agreement['delivery_time'] ?? ''));

    $lines = [
        'Dear ' . $customerName . ',',
        'Your agreement status has been updated.',
        'Agreement No: ' . $agreementNo,
        'Status: ' . $status,
    ];
    if ($pcsTotal > 0) {
        $lines[] = 'Total Stones: ' . $pcsTotal;
    }
    if ((float) $amount > 0) {
        $lines[] = 'Estimated Charges: Rs. ' . $amount;
    }
    if (($agreement['agreement_status'] ?? '') === 'DELIVERED') {
        $deliveredAt = trim($deliveryDate . ($deliveryTime !== '' ? ' ' . $deliveryTime : ''));
        if ($deliveredAt !== '') {
            $lines[] = 'Delivered On: ' . $deliveredAt;
        }
    } elseif ($deliveryDate !== '') {
        $lines[] = 'Expected Delivery: ' . trim($deliveryDate . ($deliveryTime !== '' ? ' ' . $deliveryTime : ''));
    }
    $lines[] = 'IIGJ RLC';
    return implode("\n", $lines);
}

$chatIds = waapi_chat_ids_from_mobile_field($agreement['mobile_no'] ?? '');
$waResult = waapi_send_text_message($conn, $chatIds, agreement_status_whatsapp_message($agreement));

echo json_encode([
    'status' => $waResult['ok'] ? ($waResult['failed'] ? 'partial' : 'success') : 'warning',
    'message' => $waResult['ok']
        ? 'Status updated. ' . $waResult['message']
        : 'Status updated, but WhatsApp was not sent: ' . $waResult['message'],
    'agreement_status' => $newStatus,
    'agreement_status_label' => agreement_status_label($newStatus),
    'delivery_date' => $deliveryDate ?: '',
    'delivery_time' => $deliveryTime,
    'whatsapp' => $waResult,
]);
?>
