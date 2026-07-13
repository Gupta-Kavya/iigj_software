<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
define('AGREEMENT_PRINT_EXACT_LIBRARY', true);
require_once 'agreement-print-exact.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$agreement = agreement_exact_load($conn, $id, auth_current_user_id());
if (!$agreement) {
    http_response_code(404);
    echo 'Agreement not found.';
    exit;
}
$items = is_array($agreement['_items'] ?? null) ? $agreement['_items'] : agreement_get_items($conn, $id, $agreement);
$items = array_values(array_filter($items, function ($item) {
    return strtolower(trim((string) ($item['row_status'] ?? 'active'))) !== 'cancelled';
}));

function label_text($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function label_date($value)
{
    if (!$value || $value === '0000-00-00') {
        return '';
    }
    $time = strtotime((string) $value);
    return $time ? date('d.m.Y', $time) : (string) $value;
}

function label_weight($value, $unit)
{
    $value = trim((string) $value);
    $unit = trim((string) $unit);
    if ($value === '') {
        $value = '0.00';
    }
    return trim($value . ($unit !== '' ? ' ' . $unit : ''));
}

$agreementNo = (int) ($agreement['agreement_no'] ?? 0);
$agreementDate = label_date($agreement['agreement_date'] ?? '');
$agreementTime = trim((string) ($agreement['agreement_time'] ?? ''));
$deliveryDate = label_date($agreement['delivery_date'] ?? '');
$deliveryTime = trim((string) ($agreement['delivery_time'] ?? ''));
$docketNo = trim((string) ($agreement['docket_no'] ?? ''));
$total = max(1, count($items));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Agreement Labels <?php echo label_text($agreementNo); ?></title>
    <link rel="stylesheet" href="../css/print-preview.css">
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background: #eef1f5; color: #000; font-family: Arial, Helvetica, sans-serif; }
        .label-sheet { margin: 0; }
        .stone-label {
            break-after: page;
            height: 38mm;
            overflow: hidden;
            padding: 1.3mm 1.7mm;
            width: 75mm;
        }
        .label-card {
            border: 0.35mm solid #111;
            border-radius: 1.4mm;
            height: 100%;
            padding: 1.2mm 1.5mm;
        }
        .label-top {
            align-items: start;
            border-bottom: 0.22mm solid #111;
            display: grid;
            gap: 1mm;
            grid-template-columns: minmax(0, 1fr) 22mm;
            padding-bottom: 0.7mm;
        }
        .ref-line {
            display: grid;
            gap: 0.7mm;
            grid-template-columns: 10mm 1.5mm minmax(0, 1fr);
            min-width: 0;
        }
        .ref-line .label-key { font-size: 8.2pt; }
        .ref-line .label-value { font-size: 8.2pt; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .side-meta {
            display: grid;
            gap: 0.45mm;
            grid-template-columns: 8mm minmax(0, 1fr);
        }
        .side-meta .label-key,
        .side-meta .label-value { font-size: 6.8pt; }
        .label-main {
            display: grid;
            gap: 0.5mm 1.2mm;
            grid-template-columns: 35mm minmax(0, 1fr);
            padding-top: 0.7mm;
        }
        .metric {
            display: grid;
            gap: 0.45mm;
            grid-template-columns: 21mm minmax(0, 1fr);
            min-width: 0;
        }
        .metric .label-key {
            font-size: 7.1pt;
            min-width: 0;
            overflow: hidden;
            text-overflow: clip;
            white-space: nowrap;
        }
        .metric .pcs-key { font-size: 6.4pt; }
        .metric .label-value { font-size: 7.3pt; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .report-pill {
            align-self: start;
            border: 0.22mm solid #111;
            border-radius: 1mm;
            font-size: 6.5pt;
            font-weight: 700;
            justify-self: end;
            max-width: 100%;
            overflow: hidden;
            padding: 0.45mm 1mm;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .detail-block {
            border-bottom: 0.18mm solid #999;
            border-top: 0.18mm solid #999;
            margin-top: 0.7mm;
            padding: 0.55mm 0;
        }
        .detail-row {
            display: grid;
            gap: 0.8mm;
            grid-template-columns: 15mm minmax(0, 1fr);
            min-width: 0;
        }
        .detail-row + .detail-row { margin-top: 0.4mm; }
        .detail-row .label-key { font-size: 6.8pt; }
        .label-key {
            font-weight: 800;
            line-height: 1.1;
            white-space: nowrap;
        }
        .label-value {
            font-weight: 500;
            line-height: 1.1;
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .colour-value {
            font-size: 8.4pt;
            max-height: 3.6mm;
            overflow: hidden;
        }
        .category-value {
            font-size: 6.6pt;
            line-height: 1.06;
            max-height: 6.8mm;
            overflow: hidden;
        }
        .date-grid {
            display: grid;
            gap: 0.35mm 0.8mm;
            grid-template-columns: 1fr 1fr;
            padding-top: 0.55mm;
        }
        .date-item {
            display: grid;
            gap: 0.35mm;
            grid-template-columns: 13.8mm minmax(0, 1fr);
            min-width: 0;
        }
        .date-item .label-key,
        .date-item .label-value {
            font-size: 6.25pt;
            overflow: hidden;
            text-overflow: clip;
            white-space: nowrap;
        }
        @page { margin: 0; size: 75mm 38mm; }
        @media print {
            .stone-label { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="print-preview-app">
        <header class="print-preview-toolbar">
            <div class="print-preview-brand">
                <span class="print-preview-logo"><img src="assets/agreement-iigj.png" alt="IIGJ"></span>
                <div>
                    <div class="print-preview-title">Agreement Labels Preview</div>
                    <div class="print-preview-subtitle">Agreement <?php echo label_text($agreementNo); ?> - <?php echo (int) $total; ?> label<?php echo $total === 1 ? '' : 's'; ?></div>
                </div>
            </div>
            <div class="print-preview-tools" aria-label="Preview controls">
                <button type="button" class="print-preview-tool" data-preview-zoom="out" title="Zoom out">-</button>
                <span class="print-preview-zoom-value" id="print_preview_zoom_value">100%</span>
                <button type="button" class="print-preview-tool" data-preview-zoom="in" title="Zoom in">+</button>
                <button type="button" class="print-preview-tool" data-preview-zoom="fit" title="Fit width">Fit</button>
            </div>
            <div class="print-preview-actions">
                <a href="agreement-print.php?id=<?php echo (int) $id; ?>">Agreement</a>
                <button type="button" onclick="window.close()">Close</button>
                <button type="button" class="primary-action" onclick="window.print()">Print Labels</button>
            </div>
        </header>
        <main class="print-preview-stage">
            <aside class="print-preview-side">
                <h2>Label Details</h2>
                <table class="print-preview-info">
                    <tr><td>Agreement</td><td><?php echo label_text($agreementNo); ?></td></tr>
                    <tr><td>Date</td><td><?php echo label_text(trim($agreementDate . ' ' . $agreementTime)); ?></td></tr>
                    <tr><td>Labels</td><td><?php echo (int) $total; ?></td></tr>
                    <tr><td>Size</td><td>75 mm x 38 mm</td></tr>
                    <tr><td>Delivery</td><td><?php echo label_text(trim($deliveryDate . ' ' . $deliveryTime)); ?></td></tr>
                </table>
                <div class="print-preview-note">Printing uses the fixed label size. Select the label printer and print at actual size.</div>
            </aside>
            <section class="print-preview-document" aria-label="Agreement labels preview">
                <div class="print-preview-page-wrap" id="print_preview_page_wrap">
                    <div class="label-sheet preview-paper">
        <?php foreach ($items as $index => $item): ?>
            <?php
            $refNo = trim((string) ($item['ref_no'] ?? ''));
            $pcs = trim((string) ($item['pcs'] ?? ''));
            $stoneWeight = label_weight($item['stone_wt'] ?? '', $item['stone_wt_unit'] ?? 'ct');
            $reportSize = trim((string) ($item['a4_card'] ?? 'A4'));
            $category = trim((string) ($item['category'] ?? ''));
            $colour = trim((string) ($item['color'] ?? ''));
            ?>
            <section class="stone-label">
                <div class="label-card">
                    <div class="label-top">
                        <div class="ref-line">
                            <div class="label-key">Ref.no</div>
                            <div class="label-key">:</div>
                            <div class="label-value"><?php echo label_text($refNo); ?></div>
                        </div>
                        <div class="side-meta">
                            <div class="label-key">Sn.</div>
                            <div class="label-value"><?php echo '0/' . label_text($index + 1); ?></div>
                            <div class="label-key">Doc.</div>
                            <div class="label-value"><?php echo label_text($docketNo !== '' ? $docketNo : ('AG-' . $agreementNo)); ?></div>
                        </div>
                    </div>
                    <div class="label-main">
                        <div class="metric">
                            <div class="label-key pcs-key">Pcs For Testing</div>
                            <div class="label-value"><?php echo label_text($pcs !== '' ? $pcs : '0'); ?></div>
                            <div class="label-key">Stone Wt.</div>
                            <div class="label-value"><?php echo label_text($stoneWeight); ?></div>
                        </div>
                        <div class="report-pill"><?php echo 'Report ' . label_text($reportSize !== '' ? $reportSize : 'A4'); ?></div>
                    </div>
                    <div class="detail-block">
                        <div class="detail-row">
                            <div class="label-key">Colour</div>
                            <div class="label-value colour-value"><?php echo label_text($colour); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label-key">Category</div>
                            <div class="label-value category-value"><?php echo label_text($category); ?></div>
                        </div>
                    </div>
                    <div class="date-grid">
                        <div class="date-item">
                            <div class="label-key">Rec.Dt.</div>
                            <div class="label-value"><?php echo label_text($agreementDate); ?></div>
                        </div>
                        <div class="date-item">
                            <div class="label-key">Rec.Time</div>
                            <div class="label-value"><?php echo label_text($agreementTime); ?></div>
                        </div>
                        <div class="date-item">
                            <div class="label-key">Del.Dt.</div>
                            <div class="label-value"><?php echo label_text($deliveryDate); ?></div>
                        </div>
                        <div class="date-item">
                            <div class="label-key">Del.Time</div>
                            <div class="label-value"><?php echo label_text($deliveryTime); ?></div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="../js/print-preview.js"></script>
</body>
</html>
