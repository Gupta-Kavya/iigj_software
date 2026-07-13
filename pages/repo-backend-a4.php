<?php
require_once 'auth.php';
auth_require_login();
require_once("db_connect.php");
require_once("a4-report-renderer.php");

$certiNo = isset($_POST["a4-id"]) ? (int) $_POST["a4-id"] : (isset($_GET["a4-id"]) ? (int) $_GET["a4-id"] : 0);
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
$stmt->bind_param('i', $certiNo);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('No certificate found for this account and certificate number. Please confirm the hosted database contains this report for the logged-in user.');
}

$reportType = atm_record_layout_type($row);
$settings = a4_read_settings($reportType);
$settings['qrSettings'] = atm_read_qr_settings($conn);
$renderedPage = a4_render_report_page($row, $settings);
$renderError = $renderedPage ? '' : 'Certificate data was found, but no A4 background image is available. Please upload an A4 background in A4 Builder before generating this certificate.';
$orientation = isset($settings['orientation']) && $settings['orientation'] === 'portrait' ? 'portrait' : 'landscape';
$pageWidth = $orientation === 'portrait' ? '210mm' : '297mm';
$pageHeight = $orientation === 'portrait' ? '297mm' : '210mm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="../css/print-preview.css">
<style>
@page {
    size: A4 <?php echo $orientation; ?>;
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
    background: #eef1f5;
    color: #111827;
    font-family: Arial, sans-serif;
}
.a4-sheet {
    background: #ffffff;
    height: <?php echo $pageHeight; ?>;
    margin: 0 auto;
    overflow: hidden;
    width: <?php echo $pageWidth; ?>;
}
.a4-page-img {
    display: block;
    height: <?php echo $pageHeight; ?>;
    margin: 0;
    padding: 0;
    width: <?php echo $pageWidth; ?>;
}
.a4-empty {
    font-family: Arial, sans-serif;
    padding: 20mm;
}
@media print {
    html,
    body {
        background: #ffffff;
        height: <?php echo $pageHeight; ?>;
        overflow: hidden;
        width: <?php echo $pageWidth; ?>;
    }
    .a4-sheet {
        box-shadow: none;
        margin: 0;
        page-break-after: avoid;
        page-break-before: avoid;
        page-break-inside: avoid;
    }
}
</style>
</head>
<body data-preview-default="fit" data-preview-max-zoom="0.81">
<div class="print-preview-app">
    <header class="print-preview-toolbar">
        <div class="print-preview-brand">
            <span class="print-preview-logo"><img src="assets/agreement-iigj.png" alt="IIGJ"></span>
            <div>
                <div class="print-preview-title">A4 Report Preview</div>
                <div class="print-preview-subtitle">Certificate <?php echo htmlspecialchars((string) $certiNo, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($orientation, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <div class="print-preview-tools" aria-label="Preview controls">
            <button type="button" class="print-preview-tool" data-preview-zoom="out" title="Zoom out">-</button>
            <span class="print-preview-zoom-value" id="print_preview_zoom_value">81%</span>
            <button type="button" class="print-preview-tool" data-preview-zoom="in" title="Zoom in">+</button>
            <button type="button" class="print-preview-tool" data-preview-zoom="fit" title="Fit width">Fit</button>
        </div>
        <div class="print-preview-actions">
            <button type="button" onclick="window.close()">Close</button>
            <button type="button" class="primary-action" onclick="window.print()">Print A4 Report</button>
        </div>
    </header>
    <main class="print-preview-stage">
        <aside class="print-preview-side">
            <h2>Report Details</h2>
            <table class="print-preview-info">
                <tr><td>Certificate</td><td><?php echo htmlspecialchars((string) $certiNo, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>Format</td><td>A4 <?php echo htmlspecialchars($orientation, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>Type</td><td><?php echo htmlspecialchars((string) $reportType, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>Paper</td><td><?php echo htmlspecialchars($pageWidth . ' x ' . $pageHeight, ENT_QUOTES, 'UTF-8'); ?></td></tr>
            </table>
            <div class="print-preview-note">Print at actual size / 100% so report placement matches the builder exactly.</div>
        </aside>
        <section class="print-preview-document" aria-label="A4 report preview">
            <div class="print-preview-page-wrap" id="print_preview_page_wrap">
                <div class="a4-sheet preview-paper">
                    <?php if ($renderedPage): ?>
                        <img class="a4-page-img" src="<?php echo htmlspecialchars($renderedPage, ENT_QUOTES, 'UTF-8'); ?>" alt="A4 report">
                    <?php else: ?>
                        <div class="a4-empty"><?php echo htmlspecialchars($renderError, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="../js/print-preview.js"></script>
</body>
</html>
