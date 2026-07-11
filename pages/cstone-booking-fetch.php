<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'user_branch_helper.php';

header('Content-Type: application/json; charset=utf-8');

form_master_table_ready($conn);
agreement_form_data_type_ready($conn);

$userId = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$agreementNo = (int) preg_replace('/[^0-9]/', '', (string) ($_POST['agreement_no'] ?? '0'));
$certiNo = (int) preg_replace('/[^0-9]/', '', (string) ($_POST['certi_no'] ?? '0'));
$reportType = strtoupper(trim((string) ($_POST['report_type'] ?? '')));
$allowedReportTypes = ['S', 'P', 'J', 'DS', 'D', 'R'];
$filterType = in_array($reportType, $allowedReportTypes, true) ? $reportType : '';

if ($agreementNo <= 0 || $certiNo <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Enter agreement no and certificate no.']);
    exit;
}

$booking = agreement_ensure_form_master_for_certificate($conn, $userId, $agreementNo, $certiNo);

if (!$booking) {
    echo json_encode(['status' => 'not_found', 'message' => 'This certificate is not booked in the selected agreement.']);
    exit;
}
if (strtolower(trim((string) ($booking['status'] ?? ''))) === 'cancelled') {
    echo json_encode(['status' => 'not_found', 'message' => 'This agreement row is cancelled and cannot be used for feeding.']);
    exit;
}

$existing = null;
$existingOtherType = null;
$hasUserId = false;
$columnResult = @$conn->query("SHOW COLUMNS FROM sm_form_data LIKE 'user_id'");
if ($columnResult && $columnResult->num_rows > 0) {
    $hasUserId = true;
}
if ($hasUserId) {
    $typeSql = $filterType !== '' ? ' AND `type` = ?' : '';
    $dataStmt = $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND ag_no = ? AND certi_no = ?{$typeSql} LIMIT 1");
    if ($dataStmt) {
        if ($filterType !== '') {
            $dataStmt->bind_param('iis', $agreementNo, $certiNo, $filterType);
        } else {
            $dataStmt->bind_param('ii', $agreementNo, $certiNo);
        }
        $dataStmt->execute();
        $existing = $dataStmt->get_result()->fetch_assoc();
        $dataStmt->close();
    }
    if (!$existing && $filterType !== '') {
        $otherStmt = $conn->prepare("SELECT id, `type`, category, report_no FROM sm_form_data WHERE {$scopeSql} AND ag_no = ? AND certi_no = ? LIMIT 1");
        if ($otherStmt) {
            $otherStmt->bind_param('ii', $agreementNo, $certiNo);
            $otherStmt->execute();
            $existingOtherType = $otherStmt->get_result()->fetch_assoc();
            $otherStmt->close();
        }
    }
} else {
    $typeSql = $filterType !== '' ? ' AND `type` = ?' : '';
    $dataStmt = $conn->prepare("SELECT * FROM sm_form_data WHERE ag_no = ? AND certi_no = ?{$typeSql} LIMIT 1");
    if ($dataStmt) {
        if ($filterType !== '') {
            $dataStmt->bind_param('iis', $agreementNo, $certiNo, $filterType);
        } else {
            $dataStmt->bind_param('ii', $agreementNo, $certiNo);
        }
        $dataStmt->execute();
        $existing = $dataStmt->get_result()->fetch_assoc();
        $dataStmt->close();
    }
    if (!$existing && $filterType !== '') {
        $otherStmt = $conn->prepare('SELECT id, `type`, category, report_no FROM sm_form_data WHERE ag_no = ? AND certi_no = ? LIMIT 1');
        if ($otherStmt) {
            $otherStmt->bind_param('ii', $agreementNo, $certiNo);
            $otherStmt->execute();
            $existingOtherType = $otherStmt->get_result()->fetch_assoc();
            $otherStmt->close();
        }
    }
}

echo json_encode([
    'status' => 'success',
    'booking' => [
        'agreement_no' => (int) $booking['agreement_no'],
        'certi_no' => (int) $booking['certi_no'],
        'report_no' => (string) ($booking['report_no'] ?: $booking['ref_no']),
        'ref_no' => (string) $booking['ref_no'],
        'category' => (string) $booking['category'],
        'particulars' => (string) $booking['particulars'],
        'color' => (string) $booking['color'],
        'gross_wt' => (string) $booking['gross_wt'],
        'gross_wt_unit' => (string) ($booking['gross_wt_unit'] ?? 'ct'),
        'stone_wt' => (string) $booking['stone_wt'],
        'stone_wt_unit' => (string) ($booking['stone_wt_unit'] ?? 'ct'),
        'dia_wt' => (string) $booking['dia_wt'],
        'bead_length' => (string) $booking['bead_length'],
        'pcs' => (string) $booking['pcs'],
        'a4_card' => (string) $booking['a4_card'],
        'topup' => (int) $booking['topup'],
        'rate' => (string) $booking['rate'],
        'amount' => (string) $booking['amount'],
        'status' => (string) $booking['status'],
    ],
    'existing_report' => $existing ?: null,
    'existing_other_type' => $existingOtherType ?: null,
]);
?>
