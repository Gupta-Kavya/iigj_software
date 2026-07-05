<?php
$cardWidthPx = 321.25984252;
$cardHeightPx = 204.09448819;
$cardWidthMm = 85.60;
$cardHeightMm = 53.98;
$slotWidthMm = 95;
$slotHeightMm = 64;
$cardOffsetX = ($slotWidthMm - $cardWidthMm) / 2;
$cardOffsetY = ($slotHeightMm - $cardHeightMm) / 2;

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

function atm_mm($value) { return number_format((float) $value, 3, '.', ''); }

function atm_render_sheet_v2($sheetRecords, $side, $mirror, $frontImage, $backImage, $positions, $fieldSettings, $fieldMap, $geometry)
{
    $records = array_pad(array_values($sheetRecords), 8, null);
    if ($side === 'back' && $mirror) {
        for ($row = 0; $row < 4; $row++) {
            $index = $row * 2;
            $temporary = $records[$index];
            $records[$index] = $records[$index + 1];
            $records[$index + 1] = $temporary;
        }
    }

    echo '<div class="atm-sheet">';
    foreach ($records as $index => $record) {
        if (!$record) continue;
        $row = intdiv($index, 2);
        $column = $index % 2;
        $cardX = $geometry['offsetX'] + ($column * $geometry['slotW']);
        $cardY = $geometry['offsetY'] + ($row * $geometry['slotH']);
        $background = $side === 'back' ? $backImage : $frontImage;
        echo '<img class="positioned" src="' . htmlspecialchars($background) . '" style="left:' . atm_mm($cardX) . 'mm;top:' . atm_mm($cardY) . 'mm;width:' . atm_mm($geometry['cardW']) . 'mm;height:' . atm_mm($geometry['cardH']) . 'mm;">';
        echo '<div class="card-outline positioned" style="left:' . atm_mm($cardX) . 'mm;top:' . atm_mm($cardY) . 'mm;width:' . atm_mm($geometry['cardW']) . 'mm;height:' . atm_mm($geometry['cardH']) . 'mm;"></div>';
        if ($side === 'back') continue;

        if ($positions['table']['display'] !== 'none') {
            $x = $cardX + ((float) $positions['table']['left'] / $geometry['pxW'] * $geometry['cardW']);
            $y = $cardY + ((float) $positions['table']['top'] / $geometry['pxH'] * $geometry['cardH']);
            $width = (float) $positions['table']['width'] / $geometry['pxW'] * $geometry['cardW'];
            $height = (float) $positions['table']['height'] / $geometry['pxH'] * $geometry['cardH'];
            echo '<div class="report-data positioned" style="left:' . atm_mm($x) . 'mm;top:' . atm_mm($y) . 'mm;width:' . atm_mm($width) . 'mm;height:' . atm_mm($height) . 'mm;font-size:' . (float) $positions['table']['fontSize'] . 'px;"><table>';
            foreach ($fieldMap as $settingKey => $definition) {
                if (isset($fieldSettings[$settingKey]) && $fieldSettings[$settingKey] === 'none') continue;
                $value = isset($record[$definition[1]]) ? trim((string) $record[$definition[1]]) : '';
                if ($value === '') continue;
                echo '<tr><td class="field-label">' . htmlspecialchars($definition[0]) . '</td><td>: ' . htmlspecialchars($value) . '</td></tr>';
            }
            echo '</table></div>';
        }

        if ($positions['gemstone']['display'] !== 'none') {
            $stoneFile = __DIR__ . '/assets/st_images/' . $record['certi_no'] . '.jpg';
            if (is_file($stoneFile)) {
                $x = $cardX + ((float) $positions['gemstone']['left'] / $geometry['pxW'] * $geometry['cardW']);
                $y = $cardY + ((float) $positions['gemstone']['top'] / $geometry['pxH'] * $geometry['cardH']);
                $width = (float) $positions['gemstone']['width'] / $geometry['pxW'] * $geometry['cardW'];
                $height = (float) $positions['gemstone']['height'] / $geometry['pxH'] * $geometry['cardH'];
                echo '<img class="positioned overlay-image" src="' . htmlspecialchars(str_replace('\\', '/', realpath($stoneFile))) . '" style="left:' . atm_mm($x) . 'mm;top:' . atm_mm($y) . 'mm;width:' . atm_mm($width) . 'mm;height:' . atm_mm($height) . 'mm;">';
            }
        }

        if ($positions['qrcode']['display'] !== 'none') {
            $x = $cardX + ((float) $positions['qrcode']['left'] / $geometry['pxW'] * $geometry['cardW']);
            $y = $cardY + ((float) $positions['qrcode']['top'] / $geometry['pxH'] * $geometry['cardH']);
            $width = (float) $positions['qrcode']['width'] / $geometry['pxW'] * $geometry['cardW'];
            $height = (float) $positions['qrcode']['height'] / $geometry['pxH'] * $geometry['cardH'];
            $verifyUrl = 'https://rtrlu.com/index.php?certi-no=' . rawurlencode($record['certi_no']);
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;data=' . rawurlencode($verifyUrl);
            echo '<img class="positioned overlay-image" src="' . $qrImageUrl . '" style="left:' . atm_mm($x) . 'mm;top:' . atm_mm($y) . 'mm;width:' . atm_mm($width) . 'mm;height:' . atm_mm($height) . 'mm;">';
        }
    }
    echo '</div>';
}

$geometry = ['pxW' => $cardWidthPx, 'pxH' => $cardHeightPx, 'cardW' => $cardWidthMm, 'cardH' => $cardHeightMm, 'slotW' => $slotWidthMm, 'slotH' => $slotHeightMm, 'offsetX' => $cardOffsetX, 'offsetY' => $cardOffsetY];
?>
<style>
@page { margin: 10mm; }
body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
.atm-sheet { position: relative; width: 190mm; height: 256mm; overflow: hidden; }
.positioned { position: absolute; }
.card-outline { border: 0.15mm solid #999; z-index: 2; }
.report-data { z-index: 5; overflow: hidden; text-align: left; line-height: 1.12; }
.report-data table { width: 100%; border-collapse: collapse; }
.report-data td { padding: 0.1mm 0.3mm; vertical-align: top; }
.report-data .field-label { width: 43%; font-weight: bold; }
.overlay-image { z-index: 6; }
.pdf-page-break { page-break-after: always; height: 0; }
</style>
<?php
$sheets = array_chunk($records, 8);
$lastSheet = count($sheets) - 1;
foreach ($sheets as $sheetIndex => $sheetRecords) {
    atm_render_sheet_v2($sheetRecords, 'front', false, $frontImage, $backImage, $positions, $fieldSettings, $fieldMap, $geometry);
    if ($printSettings['includeBack'] || $sheetIndex < $lastSheet) echo '<div class="pdf-page-break"></div>';
    if ($printSettings['includeBack']) {
        atm_render_sheet_v2($sheetRecords, 'back', $printSettings['backAlignment'] === 'mirror', $frontImage, $backImage, $positions, $fieldSettings, $fieldMap, $geometry);
        if ($sheetIndex < $lastSheet) echo '<div class="pdf-page-break"></div>';
    }
}
?>
