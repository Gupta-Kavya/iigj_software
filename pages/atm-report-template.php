<?php
$cardWidthPx = 321.25984252;
$cardHeightPx = 204.09448819;
$table = $positions['table'];
$gemstone = $positions['gemstone'];
$qrcode = $positions['qrcode'];

$percent = function ($value, $total) { return round(((float) $value / $total) * 100, 4); };
$frontImage = str_replace('\\', '/', realpath(__DIR__ . '/2.jpg'));
$backFile = __DIR__ . '/' . $printSettings['backImage'];
$backImage = is_file($backFile) ? str_replace('\\', '/', realpath($backFile)) : $frontImage;

$fieldMap = [
    'reportNo' => ['Report No', 'report_no'], 'date' => ['Date', 'date'],
    'stoneName' => ['Stone Name', 'stone_name'], 'weight' => ['Weight', 'stone_wt'],
    'shapeCut' => ['Shape / Cut', 'shape_cut'], 'dimension' => ['Dimension', 'dimension'],
    'colour' => ['Colour', 'color'], 'opticCharacter' => ['Optic Character', 'optic_char'],
    'refractiveIndex' => ['Refractive Index', 'ref_index'],
    'specificGravity' => ['Specific Gravity', 'spe_gravit'],
    'magnification' => ['Magnification', 'magni'], 'speciesGroup' => ['Species / Group', 'spe_group'],
    'origin' => ['Origin', 'origin'], 'hardness' => ['Hardness', 'hardness'],
    'remarks' => ['Remarks', 'comment'], 'issuedTo' => ['Issued To', 'issued_to'],
];

function atm_render_grid($records, $side, $mirror, $frontImage, $backImage, $positions, $fieldSettings, $fieldMap, $percent, $cardWidthPx, $cardHeightPx)
{
    $slotRecords = array_pad(array_values($records), 8, null);
    if ($side === 'back' && $mirror) {
        for ($row = 0; $row < 4; $row++) {
            $left = $row * 2;
            $right = $left + 1;
            $temp = $slotRecords[$left];
            $slotRecords[$left] = $slotRecords[$right];
            $slotRecords[$right] = $temp;
        }
    }

    echo '<table class="sheet-grid">';
    for ($row = 0; $row < 4; $row++) {
        echo '<tr>';
        for ($column = 0; $column < 2; $column++) {
            $record = $slotRecords[$row * 2 + $column];
            echo '<td class="sheet-slot">';
            if ($record) {
                if ($side === 'back') {
                    echo '<div class="atm-card"><img class="card-background" src="' . htmlspecialchars($backImage) . '"></div>';
                } else {
                    echo '<div class="atm-card">';
                    echo '<img class="card-background" src="' . htmlspecialchars($frontImage) . '">';

                    if ($positions['table']['display'] !== 'none') {
                        $style = 'top:' . $percent($positions['table']['top'], $cardHeightPx) . '%;left:' . $percent($positions['table']['left'], $cardWidthPx) . '%;';
                        $style .= 'width:' . $percent($positions['table']['width'], $cardWidthPx) . '%;height:' . $percent($positions['table']['height'], $cardHeightPx) . '%;';
                        $style .= 'font-size:' . (float) $positions['table']['fontSize'] . 'px;';
                        echo '<div class="report-data" style="' . $style . '"><table>';
                        foreach ($fieldMap as $settingKey => $definition) {
                            if (isset($fieldSettings[$settingKey]) && $fieldSettings[$settingKey] === 'none') continue;
                            $value = isset($record[$definition[1]]) ? $record[$definition[1]] : '';
                            if ($value === '' || $value === null) continue;
                            echo '<tr><td class="label">' . htmlspecialchars($definition[0]) . '</td><td>: ' . htmlspecialchars($value) . '</td></tr>';
                        }
                        echo '</table></div>';
                    }

                    if ($positions['gemstone']['display'] !== 'none') {
                        $stoneFile = __DIR__ . '/assets/st_images/' . $record['certi_no'] . '.jpg';
                        if (is_file($stoneFile)) {
                            $stoneStyle = 'top:' . $percent($positions['gemstone']['top'], $cardHeightPx) . '%;left:' . $percent($positions['gemstone']['left'], $cardWidthPx) . '%;';
                            $stoneStyle .= 'width:' . $percent($positions['gemstone']['width'], $cardWidthPx) . '%;height:' . $percent($positions['gemstone']['height'], $cardHeightPx) . '%;';
                            echo '<img class="placed-image" style="' . $stoneStyle . '" src="' . htmlspecialchars(str_replace('\\', '/', realpath($stoneFile))) . '">';
                        }
                    }

                    if ($positions['qrcode']['display'] !== 'none') {
                        $qrStyle = 'top:' . $percent($positions['qrcode']['top'], $cardHeightPx) . '%;left:' . $percent($positions['qrcode']['left'], $cardWidthPx) . '%;';
                        $qrStyle .= 'width:' . $percent($positions['qrcode']['width'], $cardWidthPx) . '%;height:' . $percent($positions['qrcode']['height'], $cardHeightPx) . '%;';
                        $verifyUrl = 'https://rtrlu.com/index.php?certi-no=' . rawurlencode($record['certi_no']);
                        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;data=' . rawurlencode($verifyUrl);
                        echo '<div class="qr-code" style="' . $qrStyle . '"><img src="' . $qrImageUrl . '" style="width:100%;height:100%;"></div>';
                    }
                    echo '</div>';
                }
            }
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
}
?>
<style>
@page { margin: 10mm; }
body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
.sheet-grid { width: 190mm; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
.sheet-slot { width: 95mm; height: 64mm; padding: 0; text-align: center; vertical-align: middle; }
.atm-card { width: 85.60mm; height: 53.98mm; position: relative; overflow: hidden; border: 0.15mm solid #aaa; margin: 0 auto; }
.card-background { position: absolute; left: 0; top: 0; width: 85.60mm; height: 53.98mm; z-index: 0; }
.report-data { position: absolute; z-index: 2; overflow: hidden; text-align: left; line-height: 1.15; }
.report-data table { width: 100%; border-collapse: collapse; }
.report-data td { padding: 0.2mm 0.4mm; vertical-align: top; }
.report-data td.label { width: 43%; font-weight: bold; }
.placed-image { position: absolute; z-index: 2; }
.qr-code { position: absolute; z-index: 3; text-align: center; }
.pdf-page-break { page-break-after: always; }
</style>
<?php
$sheets = array_chunk($records, 8);
$lastSheet = count($sheets) - 1;
foreach ($sheets as $sheetIndex => $sheetRecords) {
    atm_render_grid($sheetRecords, 'front', false, $frontImage, $backImage, $positions, $fieldSettings, $fieldMap, $percent, $cardWidthPx, $cardHeightPx);
    if ($printSettings['includeBack'] || $sheetIndex < $lastSheet) echo '<div class="pdf-page-break"></div>';
    if ($printSettings['includeBack']) {
        atm_render_grid($sheetRecords, 'back', $printSettings['backAlignment'] === 'mirror', $frontImage, $backImage, $positions, $fieldSettings, $fieldMap, $percent, $cardWidthPx, $cardHeightPx);
        if ($sheetIndex < $lastSheet) echo '<div class="pdf-page-break"></div>';
    }
}
?>
