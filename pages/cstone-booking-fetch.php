<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'user_branch_helper.php';

header('Content-Type: application/json; charset=utf-8');

form_master_table_ready($conn);

$userId = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$agreementNo = (int) preg_replace('/[^0-9]/', '', (string) ($_POST['agreement_no'] ?? '0'));
$certiNo = (int) preg_replace('/[^0-9]/', '', (string) ($_POST['certi_no'] ?? '0'));

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

$existing = null;
$hasUserId = false;
$columnResult = @$conn->query("SHOW COLUMNS FROM sm_form_data LIKE 'user_id'");
if ($columnResult && $columnResult->num_rows > 0) {
    $hasUserId = true;
}
if ($hasUserId) {
    $dataStmt = $conn->prepare("SELECT id, certi_no, report_no FROM sm_form_data WHERE {$scopeSql} AND certi_no = ? LIMIT 1");
    if ($dataStmt) {
        $dataStmt->bind_param('i', $certiNo);
        $dataStmt->execute();
        $existing = $dataStmt->get_result()->fetch_assoc();
        $dataStmt->close();
    }
} else {
    $dataStmt = $conn->prepare('SELECT id, certi_no, report_no FROM sm_form_data WHERE certi_no = ? LIMIT 1');
    if ($dataStmt) {
        $dataStmt->bind_param('i', $certiNo);
        $dataStmt->execute();
        $existing = $dataStmt->get_result()->fetch_assoc();
        $dataStmt->close();
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
    'existing_report' => $existing ? true : false,
]);
?>
