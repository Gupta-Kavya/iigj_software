<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

function report_open_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userId = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$certiNo = (int) preg_replace('/[^0-9]/', '', (string) ($_GET['certi_no'] ?? $_POST['certi_no'] ?? '0'));
if ($certiNo <= 0) {
    http_response_code(400);
    exit('Please enter a valid certificate number.');
}

cstone_report_type_master_ready($conn);
$hasUserId = false;
$columnResult = @$conn->query("SHOW COLUMNS FROM sm_form_data LIKE 'user_id'");
if ($columnResult && $columnResult->num_rows > 0) {
    $hasUserId = true;
}

if ($hasUserId) {
    $stmt = $conn->prepare("SELECT certi_no, report_typ FROM sm_form_data WHERE {$scopeSql} AND certi_no = ? LIMIT 1");
    $stmt->bind_param('i', $certiNo);
} else {
    $stmt = $conn->prepare('SELECT certi_no, report_typ FROM sm_form_data WHERE certi_no = ? LIMIT 1');
    $stmt->bind_param('i', $certiNo);
}
if (!$stmt) {
    http_response_code(500);
    exit('Unable to prepare certificate query.');
}
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    http_response_code(404);
    exit('Certificate not found.');
}

$reportTypeId = (int) ($record['report_typ'] ?? 0);
$format = 'a4';
if ($reportTypeId > 0) {
    $typeStmt = $conn->prepare('SELECT report_format FROM sm_colour_stone_report_types WHERE id = ? LIMIT 1');
    if ($typeStmt) {
        $typeStmt->bind_param('i', $reportTypeId);
        $typeStmt->execute();
        $typeRow = $typeStmt->get_result()->fetch_assoc();
        $typeStmt->close();
        if ($typeRow && in_array($typeRow['report_format'] ?? '', ['a4', 'atm', 'postcard'], true)) {
            $format = (string) $typeRow['report_format'];
        }
    }
}

if ($format === 'postcard') {
    header('Location: repo-backend-postcard.php?postcard-id=' . urlencode((string) $certiNo));
    exit;
}
if ($format === 'a4') {
    header('Location: repo-backend-a4.php?a4-id=' . urlencode((string) $certiNo));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Opening ATM Card Report</title>
</head>
<body>
<form id="autoReportForm" method="post" action="repo-initiator-atm.php">
    <input type="hidden" name="from" value="<?php echo report_open_h($certiNo); ?>">
    <input type="hidden" name="to" value="<?php echo report_open_h($certiNo); ?>">
    <input type="hidden" name="output_mode" value="pvc">
</form>
<script>
document.getElementById("autoReportForm").submit();
</script>
<noscript>
    <p>Click the button below to open this ATM card report.</p>
    <button form="autoReportForm" type="submit">Open Report</button>
</noscript>
</body>
</html>
