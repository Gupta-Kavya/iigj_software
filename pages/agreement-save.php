<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'customer_helper.php';
require_once 'rate_helper.php';
require_once 'user_branch_helper.php';
require_once 'rate_condition_helper.php';
require_once 'atm_config.php';

header('Content-Type: application/json; charset=utf-8');
auth_block_demo_action('Agreement creation', 'agreement.php', true);

if (!agreement_table_ready($conn)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare agreement table.']);
    exit;
}
rate_master_table_ready($conn);
user_branch_location_ready($conn);
rate_condition_table_ready($conn);

function post_text($key, $max = 255)
{
    return substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}

function post_money($key)
{
    $value = preg_replace('/[^0-9.\-]/', '', (string) ($_POST[$key] ?? '0'));
    return is_numeric($value) ? (float) $value : 0.0;
}

function agreement_save_item_number($value)
{
    $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
    return is_numeric($value) ? (float) $value : 0.0;
}

$userId = auth_current_user_id();
$memberStatus = in_array(($_POST['member_status'] ?? ''), ['Member', 'Non Member'], true) ? $_POST['member_status'] : 'Non Member';
$mouCdc = agreement_mou_tier_code($_POST['mou_cdc'] ?? '');
$mouDiscountPercent = agreement_mou_discount_percent($mouCdc);
$customerName = post_text('customer_name', 160);
if ($customerName === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Customer name is required.']);
    exit;
}
$customerMasterId = max(0, (int) ($_POST['customer_master_id'] ?? 0));
if ($customerMasterId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please select customer name from the list.']);
    exit;
}
$customerScopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$customerStmt = $conn->prepare("SELECT id, customer_name FROM sm_customer_master WHERE id = ? AND (user_id = 0 OR {$customerScopeSql}) LIMIT 1");
if (!$customerStmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to validate selected customer.']);
    exit;
}
$customerStmt->bind_param('i', $customerMasterId);
$customerStmt->execute();
$selectedCustomer = $customerStmt->get_result()->fetch_assoc();
$customerStmt->close();
if (!$selectedCustomer || strcasecmp(trim((string) $selectedCustomer['customer_name']), $customerName) !== 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid customer from the list.']);
    exit;
}

$rawItems = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
$items = [];
$pcsTotal = 0;
$rateMap = [];
$rateResult = $conn->query('SELECT rate_code, description, rate_member, rate_non_member FROM sm_rate_master');
if ($rateResult) {
    while ($rateRow = $rateResult->fetch_assoc()) {
        $rateMap[trim((string) ($rateRow['description'] ?? ''))] = $rateRow;
    }
}
$locationName = user_branch_location_for_user($conn, $userId);
$conditionRules = rate_condition_list($conn, true);
foreach ($rawItems as $rawItem) {
    if (!is_array($rawItem)) continue;
    $item = [
        'ref_no' => substr(trim((string) ($rawItem['ref_no'] ?? '')), 0, 60),
        'category' => substr(trim((string) ($rawItem['category'] ?? '')), 0, 80),
        'particulars' => substr(trim((string) ($rawItem['particulars'] ?? '')), 0, 140),
        'color' => substr(trim((string) ($rawItem['color'] ?? '')), 0, 60),
        'gross_wt' => substr(trim((string) ($rawItem['gross_wt'] ?? '')), 0, 40),
        'gross_wt_unit' => agreement_weight_unit($rawItem['gross_wt_unit'] ?? 'ct'),
        'stone_wt' => substr(trim((string) ($rawItem['stone_wt'] ?? '')), 0, 40),
        'stone_wt_unit' => agreement_weight_unit($rawItem['stone_wt_unit'] ?? 'ct'),
        'dia_wt' => substr(trim((string) ($rawItem['dia_wt'] ?? '')), 0, 40),
        'dia_wt_unit' => 'ct',
        'bead_length' => substr(trim((string) ($rawItem['bead_length'] ?? '')), 0, 40),
        'pcs' => substr(trim((string) ($rawItem['pcs'] ?? '')), 0, 20),
        'a4_card' => substr(trim((string) ($rawItem['a4_card'] ?? '')), 0, 20),
        'topup' => !empty($rawItem['topup']) ? '1' : '',
        'rate' => substr(trim((string) ($rawItem['rate'] ?? '')), 0, 30),
        'discount_percent' => '0.00',
        'discount_amount' => '0.00',
        'amount' => substr(trim((string) ($rawItem['amount'] ?? '')), 0, 30),
    ];
    $contentCheck = $item;
    unset($contentCheck['ref_no']);
    unset($contentCheck['a4_card']);
    unset($contentCheck['topup']);
    if (implode('', $contentCheck) === '') continue;
    if ($item['category'] === '') {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Select category for every stone row.']);
        exit;
    }
    $rate = $rateMap[$item['category']] ?? null;
    if (!$rate) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Selected category is not available in rate master.']);
        exit;
    }
    $rateValue = $memberStatus === 'Member' ? (float) ($rate['rate_member'] ?? 0) : (float) ($rate['rate_non_member'] ?? 0);
    $item['rate'] = agreement_money($rateValue);
    $calculated = rate_condition_calculate_amount($item, $rate, $locationName, $conditionRules);
    if ($calculated['warning'] !== '') {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $calculated['warning']]);
        exit;
    }
    $grossAmount = (float) $calculated['amount'];
    $discountAmount = $mouDiscountPercent > 0 ? round($grossAmount * ($mouDiscountPercent / 100), 2) : 0.0;
    $item['discount_percent'] = agreement_money($mouDiscountPercent);
    $item['discount_amount'] = agreement_money($discountAmount);
    $item['amount'] = agreement_money(max(0, $grossAmount - $discountAmount));
    $pcsTotal += max(0, (int) $item['pcs']);
    $items[] = $item;
}

if (!$items) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Add at least one stone detail row.']);
    exit;
}

$agreementNo = agreement_next_no($conn, $userId);
$docketNo = post_text('docket_no', 60);
$depositorName = post_text('depositor_name', 160);
$category = post_text('category', 80);
$gstNo = post_text('gst_no', 40);
$address = post_text('address', 2000);
$mobileNo = post_text('mobile_no', 40);
$email = post_text('email', 120);
$idNo = post_text('id_no', 80);
$agreementDate = post_text('agreement_date', 20) ?: date('Y-m-d');
$agreementTime = post_text('agreement_time', 20);
$deliveryDate = post_text('delivery_date', 20);
$deliveryTime = post_text('delivery_time', 20);
$delivered = !empty($_POST['delivered']) ? 1 : 0;
$testingCharges = post_money('testing_charges');
$paymentCash = post_money('payment_cash');
$paymentCheque = post_money('payment_cheque');
$paymentNeft = post_money('payment_neft');
$paymentCard = post_money('payment_card');
$paymentTds = post_money('payment_tds');
$calculatedTestingCharges = 0.0;
foreach ($items as $item) {
    $calculatedTestingCharges += agreement_save_item_number($item['amount'] ?? 0);
}
if ($calculatedTestingCharges > 0) {
    $testingCharges = $calculatedTestingCharges;
}
$paidAmount = $paymentCash + $paymentCheque + $paymentNeft + $paymentCard + $paymentTds;
$dueAmount = max(0, $testingCharges - $paidAmount);
$refundAmount = max(0, $paidAmount - $testingCharges);
$chequeNo = post_text('cheque_no', 80);
$preparedBy = post_text('prepared_by', 120);
$remarks = post_text('remarks', 2000);
$signatureMode = in_array(($_POST['signature_mode'] ?? ''), ['manual', 'esign'], true) ? $_POST['signature_mode'] : 'manual';
$customerSignature = '';
if ($signatureMode === 'esign') {
    $signatureData = trim((string) ($_POST['customer_signature'] ?? ''));
    if (preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=\r\n]+$/', $signatureData) && strlen($signatureData) <= 500000) {
        $customerSignature = $signatureData;
    }
}
$itemsJson = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$conn->begin_transaction();

$stmt = $conn->prepare("INSERT INTO sm_stone_agreements
    (user_id, agreement_no, docket_no, customer_name, depositor_name, member_status, mou_cdc, category, gst_no, address, mobile_no, email, id_no, agreement_date, agreement_time, delivery_date, delivery_time, delivered, items_json, pcs_total, testing_charges, payment_cash, payment_cheque, payment_neft, payment_card, payment_tds, cheque_no, due_amount, refund_amount, prepared_by, remarks, signature_mode, customer_signature)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save agreement: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'iisssssssssssssssisiddddddsddssss',
    $userId,
    $agreementNo,
    $docketNo,
    $customerName,
    $depositorName,
    $memberStatus,
    $mouCdc,
    $category,
    $gstNo,
    $address,
    $mobileNo,
    $email,
    $idNo,
    $agreementDate,
    $agreementTime,
    $deliveryDate,
    $deliveryTime,
    $delivered,
    $itemsJson,
    $pcsTotal,
    $testingCharges,
    $paymentCash,
    $paymentCheque,
    $paymentNeft,
    $paymentCard,
    $paymentTds,
    $chequeNo,
    $dueAmount,
    $refundAmount,
    $preparedBy,
    $remarks,
    $signatureMode,
    $customerSignature
);

if (!$stmt->execute()) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save agreement: ' . $stmt->error]);
    exit;
}

$id = $stmt->insert_id;
$stmt->close();

if (!agreement_save_items($conn, $id, $userId, $agreementNo, $items)) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Agreement saved failed while preparing stone rows for reports.']);
    exit;
}

customer_master_upsert($conn, $userId, [
    'customer_name' => $customerName,
    'depositor_name' => $depositorName,
    'address' => $address,
    'mobile_no' => $mobileNo,
    'email' => $email,
    'member_status' => $memberStatus,
    'mou_cdc' => $mouCdc,
    'id_no' => $idNo,
    'gst_no' => $gstNo,
]);

$conn->commit();

$nextCertificateInfo = atm_next_certificate_number($conn, $userId);

echo json_encode([
    'status' => 'success',
    'message' => 'Agreement saved successfully.',
    'id' => $id,
    'agreement_no' => $agreementNo,
    'next_certificate_no' => (int) ($nextCertificateInfo['certi_no'] ?? 1),
    'print_url' => 'agreement-print.php?id=' . $id,
]);
?>
