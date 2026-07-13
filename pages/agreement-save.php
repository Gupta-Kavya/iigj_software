<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'customer_helper.php';
require_once 'rate_helper.php';
require_once 'waapi_helper.php';
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

function agreement_save_release_number_lock($conn, $lockName)
{
    if (!$lockName) {
        return;
    }
    $unlockStmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if ($unlockStmt) {
        $unlockStmt->bind_param('s', $lockName);
        $unlockStmt->execute();
        $unlockStmt->close();
    }
}

function agreement_save_location_letter($conn, $userId)
{
    $location = function_exists('user_branch_location_for_user') ? user_branch_location_for_user($conn, $userId) : '';
    $letter = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $location)), 0, 1));
    return $letter !== '' ? $letter : 'S';
}

function agreement_save_assign_refs(array $items, $agreementNo, $startCertiNo, $locationLetter)
{
    $agreementNo = max(1, (int) $agreementNo);
    $startCertiNo = max(1, (int) $startCertiNo);
    $locationLetter = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', (string) $locationLetter), 0, 1));
    if ($locationLetter === '') {
        $locationLetter = 'S';
    }
    foreach ($items as $index => &$item) {
        $certiNo = $startCertiNo + (int) $index;
        $item['ref_no'] = $agreementNo . $locationLetter . $certiNo;
    }
    unset($item);
    return $items;
}

function agreement_save_cancelled_row_message($agreement, array $cancelledRows)
{
    $customerName = trim((string) ($agreement['customer_name'] ?? 'Customer')) ?: 'Customer';
    $agreementNo = (int) ($agreement['agreement_no'] ?? 0);
    $testingCharges = agreement_money($agreement['testing_charges'] ?? 0);
    $refundAmount = agreement_money($agreement['refund_amount'] ?? 0);
    $itemWord = count($cancelledRows) === 1 ? 'item has' : 'items have';
    $lines = [
        'Dear ' . $customerName . ',',
        '',
        'Your stone testing agreement has been updated.',
        'The following submitted ' . $itemWord . ' been cancelled.',
        '',
        'Agreement No: ' . $agreementNo,
    ];

    $lines[] = 'Cancelled Item Details:';
    foreach ($cancelledRows as $row) {
        $refNo = trim((string) ($row['ref_no'] ?? ''));
        $category = trim((string) ($row['category'] ?? ''));
        $pcs = max(0, (int) ($row['pcs'] ?? 0));
        $amount = agreement_money($row['amount'] ?? 0);
        $reason = trim((string) ($row['row_cancel_reason'] ?? ''));
        $lines[] = '';
        $lines[] = 'Ref No.   : ' . ($refNo !== '' ? $refNo : 'N/A');
        if ($category !== '') {
            $lines[] = 'Category  : ' . $category;
        }
        if ($pcs > 0) {
            $lines[] = 'PCS       : ' . $pcs;
        }
        $lines[] = 'Amount    : Rs. ' . $amount;
        if ($reason !== '') {
            $lines[] = 'Reason    : ' . $reason;
        }
    }

    $lines[] = '';
    $lines[] = 'Updated Estimated Charges: Rs. ' . $testingCharges;
    if ((float) $refundAmount > 0) {
        $lines[] = 'Refund Amount           : Rs. ' . $refundAmount;
    }
    $lines[] = '';
    $lines[] = 'For any query, please contact the lab.';
    $lines[] = 'IIGJ RLC';
    return implode("\n", $lines);
}

$userId = auth_current_user_id();
user_collection_center_ready($conn);
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
$rateResult = $conn->query('SELECT rate_code, description, rate_member, rate_non_member, cdc FROM sm_rate_master');
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
        'row_status' => strtolower(trim((string) ($rawItem['row_status'] ?? 'active'))) === 'cancelled' ? 'cancelled' : 'active',
        'row_cancel_reason' => substr(trim((string) ($rawItem['row_cancel_reason'] ?? '')), 0, 300),
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
    if ($item['row_status'] === 'cancelled' && $item['row_cancel_reason'] === '') {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Enter cancellation reason for every cancelled row.']);
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
    $cdcAllowed = strtoupper(trim((string) ($rate['cdc'] ?? ''))) === 'Y';
    $discountPercent = $cdcAllowed ? $mouDiscountPercent : 0.0;
    $discountAmount = $discountPercent > 0 ? round($grossAmount * ($discountPercent / 100), 2) : 0.0;
    $item['discount_percent'] = agreement_money($discountPercent);
    $item['discount_amount'] = agreement_money($discountAmount);
    $item['amount'] = agreement_money(max(0, $grossAmount - $discountAmount));
    if ($item['row_status'] !== 'cancelled') {
        $pcsTotal += max(0, (int) $item['pcs']);
    }
    $items[] = $item;
}

if (!$items) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Add at least one stone detail row.']);
    exit;
}

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
$cancelledRefundAmount = 0.0;
foreach ($items as $item) {
    if (($item['row_status'] ?? 'active') === 'cancelled') {
        $cancelledRefundAmount += agreement_save_item_number($item['amount'] ?? 0);
        continue;
    }
    $calculatedTestingCharges += agreement_save_item_number($item['amount'] ?? 0);
}
if ($calculatedTestingCharges > 0) {
    $testingCharges = $calculatedTestingCharges;
}
$paidAmount = $paymentCash + $paymentCheque + $paymentNeft + $paymentCard + $paymentTds;
$dueAmount = max(0, $testingCharges - $paidAmount);
$refundAmount = max(0, $paidAmount - $testingCharges, $cancelledRefundAmount);
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
$editAgreementId = max(0, (int) ($_POST['edit_agreement_id'] ?? 0));
$editAgreementNo = max(0, (int) ($_POST['edit_agreement_no'] ?? 0));
$existingAgreement = null;
$previousRowsByRef = [];
if ($editAgreementId > 0 && $editAgreementNo > 0) {
    $scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
    $editStmt = $conn->prepare("SELECT id, agreement_no, agreement_branch_location, collection_center_id, collection_center_code, collection_center_name FROM sm_stone_agreements WHERE id = ? AND agreement_no = ? AND {$scopeSql} LIMIT 1");
    if (!$editStmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to validate agreement for editing.']);
        exit;
    }
    $editStmt->bind_param('ii', $editAgreementId, $editAgreementNo);
    $editStmt->execute();
    $existingAgreement = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();
    if (!$existingAgreement) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Agreement not found for editing.']);
        exit;
    }
    $oldRowsStmt = $conn->prepare('SELECT ref_no, row_status, row_cancel_reason FROM sm_stone_agreement_items WHERE agreement_id = ?');
    if ($oldRowsStmt) {
        $oldRowsStmt->bind_param('i', $editAgreementId);
        $oldRowsStmt->execute();
        $oldRowsResult = $oldRowsStmt->get_result();
        while ($oldRow = $oldRowsResult->fetch_assoc()) {
            $oldRef = trim((string) ($oldRow['ref_no'] ?? ''));
            if ($oldRef !== '') {
                $previousRowsByRef[$oldRef] = [
                    'row_status' => strtolower(trim((string) ($oldRow['row_status'] ?? 'active'))),
                    'row_cancel_reason' => trim((string) ($oldRow['row_cancel_reason'] ?? '')),
                ];
            }
        }
        $oldRowsStmt->close();
    }
}

$postedCollectionCenterId = max(0, (int) ($_POST['collection_center_id'] ?? 0));
if ($existingAgreement) {
    $collectionCenter = [
        'id' => (int) ($existingAgreement['collection_center_id'] ?? 0),
        'center_code' => user_collection_center_code_normalize($existingAgreement['collection_center_code'] ?? ''),
        'center_name' => trim((string) ($existingAgreement['collection_center_name'] ?? '')),
    ];
    if ($collectionCenter['center_code'] === '') {
        $collectionCenter = user_collection_center_for_user($conn, $userId, $postedCollectionCenterId);
    }
} else {
    $collectionCenter = user_collection_center_for_user($conn, $userId, $postedCollectionCenterId);
}
$collectionCenterId = (int) ($collectionCenter['id'] ?? 0);
$collectionCenterCode = user_collection_center_code_normalize($collectionCenter['center_code'] ?? '');
$collectionCenterName = substr(trim((string) ($collectionCenter['center_name'] ?? '')), 0, 120);
if ($collectionCenterCode === '') {
    $collectionCenterCode = agreement_save_location_letter($conn, $userId);
}
if ($collectionCenterName === '') {
    $collectionCenterName = $collectionCenterCode;
}
$agreementBranchLocation = user_branch_location_clean($locationName, $conn);
if ($existingAgreement && trim((string) ($existingAgreement['agreement_branch_location'] ?? '')) !== '') {
    $agreementBranchLocation = user_branch_location_clean($existingAgreement['agreement_branch_location'], $conn);
}
if ($agreementBranchLocation === '') {
    $agreementBranchLocation = user_branch_location_for_user($conn, $userId);
}

$newlyCancelledRows = [];
if ($existingAgreement) {
    foreach ($items as $item) {
        if (($item['row_status'] ?? 'active') !== 'cancelled') {
            continue;
        }
        $refNo = trim((string) ($item['ref_no'] ?? ''));
        if ($refNo === '') {
            continue;
        }
        $previousStatus = strtolower(trim((string) ($previousRowsByRef[$refNo]['row_status'] ?? 'active')));
        if ($previousStatus !== 'cancelled') {
            $newlyCancelledRows[] = $item;
        }
    }
}

$numberLockName = '';
if (!$existingAgreement) {
    $lockBranch = $agreementBranchLocation !== '' ? ('branch_' . strtolower($agreementBranchLocation)) : (function_exists('user_branch_storage_code') ? user_branch_storage_code($conn, $userId) : ('user_' . $userId));
    $numberLockName = 'iigj_agreement_number_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $lockBranch);
    $lockStmt = $conn->prepare('SELECT GET_LOCK(?, 10) AS lock_status');
    if (!$lockStmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to prepare agreement number lock.']);
        exit;
    }
    $lockStmt->bind_param('s', $numberLockName);
    $lockStmt->execute();
    $lockResult = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();
    if (!$lockResult || (int) ($lockResult['lock_status'] ?? 0) !== 1) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Another agreement is being saved for this location. Please try again in a few seconds.']);
        exit;
    }
}

$conn->begin_transaction();

if ($existingAgreement) {
    $id = (int) $existingAgreement['id'];
    $agreementNo = (int) $existingAgreement['agreement_no'];
    $stmt = $conn->prepare("UPDATE sm_stone_agreements SET
        agreement_branch_location = ?, docket_no = ?, customer_name = ?, depositor_name = ?, member_status = ?, mou_cdc = ?, category = ?, gst_no = ?, address = ?, mobile_no = ?, email = ?, id_no = ?, agreement_date = ?, agreement_time = ?, delivery_date = ?, delivery_time = ?, delivered = ?, items_json = ?, pcs_total = ?, testing_charges = ?, payment_cash = ?, payment_cheque = ?, payment_neft = ?, payment_card = ?, payment_tds = ?, cheque_no = ?, due_amount = ?, refund_amount = ?, prepared_by = ?, remarks = ?, signature_mode = ?, customer_signature = ?, updated_at = NOW()
        WHERE id = ?");
    if (!$stmt) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to update agreement: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param(
        'ssssssssssssssssisiddddddsddssssi',
        $agreementBranchLocation,
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
        $customerSignature,
        $id
    );
} else {
    $agreementNo = agreement_next_no_for_branch($conn, $agreementBranchLocation, $userId);
    $nextCertificateInfoForAgreement = atm_next_certificate_number($conn, $userId);
    $items = agreement_save_assign_refs(
        $items,
        $agreementNo,
        (int) ($nextCertificateInfoForAgreement['certi_no'] ?? 1),
        $collectionCenterCode
    );
    foreach ($items as &$item) {
        $item['collection_center_id'] = $collectionCenterId;
        $item['collection_center_code'] = $collectionCenterCode;
        $item['collection_center_name'] = $collectionCenterName;
    }
    unset($item);
    $itemsJson = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO sm_stone_agreements
        (user_id, agreement_branch_location, agreement_no, collection_center_id, collection_center_code, collection_center_name, docket_no, customer_name, depositor_name, member_status, mou_cdc, category, gst_no, address, mobile_no, email, id_no, agreement_date, agreement_time, delivery_date, delivery_time, delivered, items_json, pcs_total, testing_charges, payment_cash, payment_cheque, payment_neft, payment_card, payment_tds, cheque_no, due_amount, refund_amount, prepared_by, remarks, signature_mode, customer_signature)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $conn->rollback();
        agreement_save_release_number_lock($conn, $numberLockName);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to save agreement: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param(
        'isiisssssssssssssssssisiddddddsddssss',
        $userId,
        $agreementBranchLocation,
        $agreementNo,
        $collectionCenterId,
        $collectionCenterCode,
        $collectionCenterName,
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
}

if (!$stmt->execute()) {
    $conn->rollback();
    agreement_save_release_number_lock($conn, $numberLockName);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => ($existingAgreement ? 'Unable to update agreement: ' : 'Unable to save agreement: ') . $stmt->error]);
    exit;
}

if (!$existingAgreement) {
    $id = $stmt->insert_id;
}
$stmt->close();

if (!agreement_save_items($conn, $id, $userId, $agreementNo, $items, $collectionCenter)) {
    $conn->rollback();
    agreement_save_release_number_lock($conn, $numberLockName);
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

$nextCertificateInfo = atm_next_certificate_number($conn, $userId);
$conn->commit();
agreement_save_release_number_lock($conn, $numberLockName);

$rowCancellationWhatsapp = null;
$rowCancellationMessage = '';
if ($newlyCancelledRows) {
    $agreementForMessage = [
        'customer_name' => $customerName,
        'agreement_no' => $agreementNo,
        'testing_charges' => $testingCharges,
        'refund_amount' => $refundAmount,
    ];
    $chatIds = waapi_chat_ids_from_mobile_field($mobileNo);
    $rowCancellationWhatsapp = waapi_send_text_message($conn, $chatIds, agreement_save_cancelled_row_message($agreementForMessage, $newlyCancelledRows));
    $rowCancellationMessage = $rowCancellationWhatsapp['ok']
        ? 'Cancellation WhatsApp sent to customer.'
        : 'Agreement updated, but cancellation WhatsApp was not sent: ' . ($rowCancellationWhatsapp['message'] ?? 'Unknown error.');
}

$response = [
    'status' => 'success',
    'message' => $existingAgreement ? 'Agreement updated successfully.' : 'Agreement saved successfully.',
    'id' => $id,
    'agreement_no' => $agreementNo,
    'edit_mode' => $existingAgreement ? 1 : 0,
    'next_certificate_no' => (int) ($nextCertificateInfo['certi_no'] ?? 1),
    'print_url' => 'agreement-print.php?id=' . $id,
    'labels_url' => 'agreement-labels-print.php?id=' . $id,
];
if ($rowCancellationWhatsapp !== null) {
    $response['row_cancellation_whatsapp'] = $rowCancellationWhatsapp;
    $response['row_cancellation_message'] = $rowCancellationMessage;
}

echo json_encode($response);
?>
