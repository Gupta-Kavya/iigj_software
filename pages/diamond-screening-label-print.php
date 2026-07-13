<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

function ds_label_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ds_label_date($value)
{
    if (!$value || $value === '0000-00-00') {
        return '';
    }
    $time = strtotime((string) $value);
    return $time ? date('d.m.Y', $time) : (string) $value;
}

function ds_label_num($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '0.00';
    }
    if (is_numeric($value)) {
        return number_format((float) $value, 2, '.', '');
    }
    return $value;
}

function ds_label_int($value)
{
    $value = trim((string) $value);
    return $value === '' ? '0' : (string) (int) $value;
}

$certiNo = (int) preg_replace('/[^0-9]/', '', (string) ($_GET['certi_no'] ?? '0'));
if ($certiNo <= 0) {
    http_response_code(400);
    exit('Please enter a valid certificate number.');
}

$userId = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$columnResult = @$conn->query("SHOW COLUMNS FROM sm_form_data LIKE 'user_id'");
$hasUserId = $columnResult && $columnResult->num_rows > 0;

if ($hasUserId) {
    $stmt = $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND certi_no = ? AND `type` = 'DS' LIMIT 1");
    $stmt->bind_param('i', $certiNo);
} else {
    $stmt = $conn->prepare("SELECT * FROM sm_form_data WHERE certi_no = ? AND `type` = 'DS' LIMIT 1");
    $stmt->bind_param('i', $certiNo);
}
if (!$stmt) {
    http_response_code(500);
    exit('Unable to prepare label query.');
}
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    http_response_code(404);
    exit('Diamond screening certificate not found.');
}

$qrSettings = atm_read_qr_settings($conn);
$qrUrl = atm_build_qr_url($qrSettings['urlPattern'] ?? '', $record);
$qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($qrUrl);
$referenceNo = trim((string) ($record['report_no'] ?? ''));
if ($referenceNo === '') {
    $referenceNo = (string) ($record['certi_no'] ?? $certiNo);
}
$shapeCut = trim((string) ($record['shape_cut'] ?? ''));
$totalWeight = trim((string) ($record['dia_wt'] ?? $record['stone_wt'] ?? $record['gross_wt'] ?? ''));
$totalPcs = trim((string) ($record['pcs'] ?? $record['testd_pcs'] ?? $record['stone_pcs'] ?? $record['faces'] ?? ''));
$rows = [
    ['NATURAL DIAMOND', $record['nat_dia_wt'] ?? '', $record['nat_dia_pc'] ?? ''],
    ['SYNTHETIC DIAMOND', $record['syn_dia_wt'] ?? '', $record['syn_dia_pc'] ?? ''],
    ['REFERAL', $record['ref_dia_wt'] ?? '', $record['ref_dia_pc'] ?? ''],
    ['NON-DIAMOND', $record['non_dia_wt'] ?? '', $record['non_dia_pc'] ?? ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Diamond Screening Label <?php echo ds_label_h($referenceNo); ?></title>
<link rel="stylesheet" href="../css/print-preview.css">
<style>
*{box-sizing:border-box}html,body{margin:0;padding:0}body{background:#eef1f5;color:#000;font-family:Arial,Helvetica,sans-serif}.label-page{background:#fff;height:74mm;overflow:hidden;padding:3mm 3.5mm;width:105mm}.label-card{height:100%;position:relative}.label-header{align-items:center;border-bottom:.35mm solid #111;display:grid;grid-template-columns:25mm 1fr 25mm;min-height:14mm;padding-bottom:1.4mm}.logo-left img{height:8mm;max-width:22mm;object-fit:contain}.logo-right{text-align:right}.logo-right img{height:11mm;max-width:22mm;object-fit:contain}.title{font-size:8.2pt;font-weight:800;text-align:center;text-transform:uppercase}.meta{display:grid;gap:1.2mm 4mm;grid-template-columns:1fr 1fr;margin-top:2.2mm}.meta-row{display:grid;grid-template-columns:25mm 2mm minmax(0,1fr);min-width:0}.meta-label,.meta-colon{font-size:7.5pt;line-height:1.1;text-transform:uppercase}.meta-value{font-size:8.4pt;font-weight:800;line-height:1.1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.shape-row{grid-column:1/-1}.shape-row .meta-value{white-space:normal}.results{border:.55mm solid #111;border-radius:1mm;margin-top:2.3mm;padding:1.6mm 3mm 1.8mm}.results-title{font-size:7.6pt;margin-bottom:1mm;text-transform:uppercase}.result-row{display:grid;grid-template-columns:42mm 2mm 12mm 5mm 2mm 11mm;line-height:1.18}.result-name,.result-weight,.result-unit,.result-pcs{font-size:8.3pt;font-weight:800}.notes-wrap{display:grid;gap:2mm;grid-template-columns:minmax(0,1fr) 18mm;margin-top:1.6mm}.notes-title{font-size:7.2pt;font-weight:800;margin-bottom:.6mm}.notes{font-size:5.3pt;font-weight:700;line-height:1.22;margin:0;padding-left:3mm}.notes li{margin:0 0 .7mm}.qr{align-self:start;text-align:right}.qr img{height:16.8mm;width:16.8mm}.small-note{font-size:4.8pt;font-weight:700;line-height:1.15;margin-top:.7mm;text-align:center}@page{margin:0;size:105mm 74mm}@media print{.label-page{break-after:page;margin:0}}
</style>
</head>
<body>
<div class="print-preview-app">
    <header class="print-preview-toolbar">
        <div class="print-preview-brand">
            <span class="print-preview-logo"><img src="assets/agreement-iigj.png" alt="IIGJ"></span>
            <div>
                <div class="print-preview-title">Diamond Screening Label Preview</div>
                <div class="print-preview-subtitle"><?php echo ds_label_h($referenceNo); ?> - 105 mm x 74 mm</div>
            </div>
        </div>
        <div class="print-preview-tools" aria-label="Preview controls">
            <button type="button" class="print-preview-tool" data-preview-zoom="out" title="Zoom out">-</button>
            <span class="print-preview-zoom-value" id="print_preview_zoom_value">100%</span>
            <button type="button" class="print-preview-tool" data-preview-zoom="in" title="Zoom in">+</button>
            <button type="button" class="print-preview-tool" data-preview-zoom="fit" title="Fit width">Fit</button>
        </div>
        <div class="print-preview-actions">
            <button type="button" onclick="window.close()">Close</button>
            <button type="button" class="primary-action" onclick="window.print()">Print Label</button>
        </div>
    </header>
    <main class="print-preview-stage">
        <aside class="print-preview-side">
            <h2>Label Details</h2>
            <table class="print-preview-info">
                <tr><td>Reference</td><td><?php echo ds_label_h($referenceNo); ?></td></tr>
                <tr><td>Date</td><td><?php echo ds_label_h(ds_label_date($record['date'] ?? '')); ?></td></tr>
                <tr><td>Weight</td><td><?php echo ds_label_h(ds_label_num($totalWeight)); ?> ct.</td></tr>
                <tr><td>Pcs</td><td><?php echo ds_label_h(ds_label_int($totalPcs)); ?></td></tr>
                <tr><td>Size</td><td>105 mm x 74 mm</td></tr>
            </table>
            <div class="print-preview-note">Printing keeps the label page at the fixed diamond screening label size.</div>
        </aside>
        <section class="print-preview-document" aria-label="Diamond screening label preview">
            <div class="print-preview-page-wrap" id="print_preview_page_wrap">
<section class="label-page preview-paper">
    <div class="label-card">
        <div class="label-header">
            <div class="logo-left"><img src="assets/agreement-gjepc.svg" alt="GJEPC"></div>
            <div class="title">Diamond Packet Screening Report</div>
            <div class="logo-right"><img src="assets/agreement-iigj.png" alt="IIGJ"></div>
        </div>
        <div class="meta">
            <div class="meta-row"><div class="meta-label">Reference No.</div><div class="meta-colon">:</div><div class="meta-value"><?php echo ds_label_h($referenceNo); ?></div></div>
            <div class="meta-row"><div class="meta-label">Date</div><div class="meta-colon">:</div><div class="meta-value"><?php echo ds_label_h(ds_label_date($record['date'] ?? '')); ?></div></div>
            <div class="meta-row"><div class="meta-label">Total Weight</div><div class="meta-colon">:</div><div class="meta-value"><?php echo ds_label_h(ds_label_num($totalWeight)); ?> ct.</div></div>
            <div class="meta-row"><div class="meta-label">Pcs</div><div class="meta-colon">:</div><div class="meta-value"><?php echo ds_label_h(ds_label_int($totalPcs)); ?></div></div>
            <div class="meta-row shape-row"><div class="meta-label">Shape/Cut</div><div class="meta-colon">:</div><div class="meta-value"><?php echo ds_label_h($shapeCut); ?></div></div>
        </div>
        <div class="results">
            <div class="results-title">Test Results</div>
            <?php foreach ($rows as $row): ?>
                <div class="result-row">
                    <div class="result-name"><?php echo ds_label_h($row[0]); ?></div>
                    <div class="result-name">:</div>
                    <div class="result-weight"><?php echo ds_label_h(ds_label_num($row[1])); ?></div>
                    <div class="result-unit">CT</div>
                    <div class="result-unit">|</div>
                    <div class="result-pcs"><?php echo ds_label_h(ds_label_int($row[2])); ?> PC</div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="notes-wrap">
            <div>
                <div class="notes-title">Notes:</div>
                <ol class="notes">
                    <li>Results given here are based on screening of the given packet only for natural diamond, synthetic diamond or non-diamond and does not indicate the presence or absence of treatments.</li>
                    <li>Pieces marked as referal require further analyses, hence, for their conclusive identification, they should be submitted under regular packet lot category.</li>
                    <li>Report will become invalid once the packet is opened.</li>
                </ol>
            </div>
            <div class="qr"><img src="<?php echo ds_label_h($qrImage); ?>" alt="QR"><div class="small-note">Scan to verify</div></div>
        </div>
    </div>
</section>
            </div>
        </section>
    </main>
</div>
<script src="../js/print-preview.js"></script>
</body>
</html>
