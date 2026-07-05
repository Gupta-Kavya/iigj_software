<?php
require_once 'auth.php';
auth_require_login();
require_once("db_connect.php");
require_once("postcard_config.php");
require_once("a4-report-renderer.php");

$certiNo = isset($_POST["postcard-id"]) ? (int) $_POST["postcard-id"] : (isset($_GET["postcard-id"]) ? (int) $_GET["postcard-id"] : 0);
if ($certiNo < 1) {
    $certiNo = isset($_POST["a4-id"]) ? (int) $_POST["a4-id"] : (isset($_GET["a4-id"]) ? (int) $_GET["a4-id"] : 0);
}
$userId = auth_current_user_id();

if ($certiNo < 1) {
    http_response_code(400);
    exit('Please enter a valid certificate number.');
}

$hasFormUserId = atm_table_has_column($conn, 'sm_form_data', 'user_id');
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$stmt = $hasFormUserId
    ? $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND certi_no = ? LIMIT 1")
    : $conn->prepare("SELECT * FROM sm_form_data WHERE certi_no = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit('Unable to prepare certificate query: ' . $conn->error);
}
if ($hasFormUserId) {
    $stmt->bind_param('i', $certiNo);
} else {
    $stmt->bind_param('i', $certiNo);
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('No certificate found for this account and certificate number. Please confirm the hosted database contains this report for the logged-in user.');
}

$reportType = atm_record_layout_type($row);
$settings = postcard_read_settings($reportType);
$settings['qrSettings'] = atm_read_qr_settings($conn);
$renderedPage = a4_render_report_page($row, $settings);
$renderError = $renderedPage ? '' : 'Certificate data was found, but no Postcard background image is available. Please upload a Postcard background in Postcard Builder before generating this certificate.';
$orientation = isset($settings['orientation']) && $settings['orientation'] === 'landscape' ? 'landscape' : 'portrait';
$pageWidth = (int) ($settings['pageWidthMm'] ?? ($orientation === 'landscape' ? 150 : 100)) . 'mm';
$pageHeight = (int) ($settings['pageHeightMm'] ?? ($orientation === 'landscape' ? 100 : 150)) . 'mm';
$paperLabel = $orientation === 'landscape' ? '15cm x 10cm' : '10cm x 15cm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
@page {
    size: <?php echo $pageWidth; ?> <?php echo $pageHeight; ?>;
    margin: 0;
}
* {
    box-sizing: border-box;
}
html, body {
    margin: 0;
    padding: 0;
}
body {
    background: #e5e7eb;
    color: #111827;
    font-family: Arial, sans-serif;
}
.print-toolbar {
    align-items: center;
    background: #ffffff;
    border-bottom: 1px solid #d0d5dd;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
    display: flex;
    gap: 12px;
    justify-content: space-between;
    padding: 12px 18px;
    position: sticky;
    top: 0;
    z-index: 10;
}
.print-toolbar h1 {
    font-size: 16px;
    margin: 0;
}
.print-toolbar p {
    color: #667085;
    font-size: 12px;
    margin: 3px 0 0;
}
.print-toolbar button {
    background: #171717;
    border: 0;
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    padding: 10px 16px;
}
.print-stage {
    padding: 20px;
}
.postcard-sheet {
    background: #ffffff;
    box-shadow: 0 16px 42px rgba(15, 23, 42, .22);
    width: <?php echo $pageWidth; ?>;
    height: <?php echo $pageHeight; ?>;
    margin: 0 auto;
    overflow: hidden;
}
.postcard-page-img {
    display: block;
    width: <?php echo $pageWidth; ?>;
    height: <?php echo $pageHeight; ?>;
    margin: 0;
    padding: 0;
}
.postcard-empty {
    font-family: Arial, sans-serif;
    padding: 20mm;
}
@media print {
    html,
    body {
        background: #ffffff;
        width: <?php echo $pageWidth; ?>;
        height: <?php echo $pageHeight; ?>;
        overflow: hidden;
    }
    .print-toolbar {
        display: none !important;
    }
    .print-stage {
        margin: 0;
        padding: 0;
    }
    .postcard-sheet {
        box-shadow: none;
        margin: 0;
        page-break-after: avoid;
        page-break-before: avoid;
        page-break-inside: avoid;
    }
}
</style>
</head>
<body>
<div class="print-toolbar">
    <div>
        <h1>Postcard Certificate Print Preview</h1>
        <p>Certificate No: <?php echo htmlspecialchars((string) $certiNo, ENT_QUOTES, 'UTF-8'); ?> &middot; Print <?php echo htmlspecialchars($orientation, ENT_QUOTES, 'UTF-8'); ?> at actual size / 100% on <?php echo htmlspecialchars($paperLabel, ENT_QUOTES, 'UTF-8'); ?> paper.</p>
    </div>
    <button type="button" onclick="window.print()">Print Postcard Certificate</button>
</div>
<div class="print-stage">
<div class="postcard-sheet">
<?php if ($renderedPage): ?>
<img class="postcard-page-img" src="<?php echo htmlspecialchars($renderedPage, ENT_QUOTES, 'UTF-8'); ?>" alt="Postcard report">
<?php else: ?>
<div class="postcard-empty"><?php echo htmlspecialchars($renderError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
</div>
</div>
</body>
</html>
