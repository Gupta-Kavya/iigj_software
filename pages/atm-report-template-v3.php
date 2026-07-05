<?php
require_once 'atm-card-renderer.php';

$frontImage = isset($printSettings['frontImage']) ? trim((string) $printSettings['frontImage']) : '';
$frontFile = $frontImage !== '' ? __DIR__ . '/' . $frontImage : '';
if (!is_file($frontFile)) {
    http_response_code(422);
    exit('Please upload an ATM front card image before generating ATM card sheets.');
}
$backImagePath = isset($printSettings['backImage']) ? trim((string) $printSettings['backImage']) : '';
$backFile = $backImagePath !== '' ? __DIR__ . '/' . $backImagePath : '';
$backImage = '';
if (!empty($printSettings['includeBack'])) {
    if (!is_file($backFile)) {
        http_response_code(422);
        exit('Please upload an ATM back card image or disable back printing before generating ATM card sheets.');
    }
    $backMime = strtolower(pathinfo($backFile, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
    $backImage = 'data:' . $backMime . ';base64,' . base64_encode(file_get_contents($backFile));
}
$fieldMap = atm_field_map('S');

function atm_render_image_grid($sheetRecords, $side, $mirror, $frontFile, $backImage, $positionsByType, $fieldSettingsByType, $fieldMap, $qrSettings)
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

    echo '<table class="sheet-grid">';
    for ($row = 0; $row < 4; $row++) {
        echo '<tr>';
        for ($column = 0; $column < 2; $column++) {
            $record = $records[$row * 2 + $column];
            echo '<td class="sheet-slot">';
            if ($record) {
                $recordType = atm_record_layout_type($record);
                if (!isset($positionsByType[$recordType])) {
                    $recordType = atm_base_report_type($recordType);
                }
                $source = $side === 'back'
                    ? $backImage
                    : atm_render_front_card($record, $positionsByType[$recordType], $fieldSettingsByType[$recordType], $fieldMap, $frontFile, $qrSettings);
                echo '<img class="complete-card" src="' . htmlspecialchars($source) . '">';
            }
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
}

function atm_render_single_cards($records, $frontFile, $backImage, $positions, $fieldSettings, $fieldMap, $qrSettings, $includeBack)
{
    $pages = [];
    foreach ($records as $record) {
        $pages[] = atm_render_front_card($record, $positions, $fieldSettings, $fieldMap, $frontFile, $qrSettings);
        if ($includeBack) {
            $pages[] = $backImage;
        }
    }

    $lastPage = count($pages) - 1;
    foreach ($pages as $index => $source) {
        $pageClass = $index < $lastPage ? 'pvc-card-page pvc-has-next' : 'pvc-card-page';
        echo '<div class="' . $pageClass . '">';
        echo '<img class="pvc-complete-card" src="' . htmlspecialchars($source) . '">';
        echo '</div>';
    }
}
?>
<style>
@page { margin: <?php echo $outputMode === 'pvc' ? '0' : '10mm'; ?>; }
html, body { margin: 0; padding: 0; }
body { line-height: 0; }
.sheet-grid { width: 190mm; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
.sheet-slot { width: 95mm; height: 64mm; padding: 0; text-align: center; vertical-align: middle; }
.complete-card { width: 85.60mm; height: 53.98mm; border: 0.15mm solid #999; }
.pdf-page-break { page-break-after: always; height: 0; }
.pvc-card-page {
    position: relative;
    width: 85.60mm;
    height: 53.98mm;
    margin: 0;
    padding: 0;
    overflow: hidden;
    line-height: 0;
}
.pvc-complete-card {
    position: absolute;
    top: -0.15mm;
    left: -0.15mm;
    display: block;
    width: 85.90mm;
    height: 54.28mm;
    max-width: none;
    margin: 0;
    padding: 0;
    border: 0;
}
.pvc-has-next { page-break-after: always; }
</style>
<?php
$outputMode = isset($outputMode) && $outputMode === 'pvc' ? 'pvc' : 'sheet';
if ($outputMode === 'pvc') {
    atm_render_single_cards(
        $records,
        $frontFile,
        $backImage,
        $positions,
        $fieldSettings,
        $fieldMap,
        $qrSettings,
        !empty($printSettings['includeBack'])
    );
    return;
}

$sheets = array_chunk($records, 8);
$lastSheet = count($sheets) - 1;
foreach ($sheets as $sheetIndex => $sheetRecords) {
    atm_render_image_grid($sheetRecords, 'front', false, $frontFile, $backImage, $positionsByType, $fieldSettingsByType, $fieldMap, $qrSettings);
    if ($printSettings['includeBack'] || $sheetIndex < $lastSheet) echo '<div class="pdf-page-break"></div>';
    if ($printSettings['includeBack']) {
        atm_render_image_grid($sheetRecords, 'back', $printSettings['backAlignment'] === 'mirror', $frontFile, $backImage, $positionsByType, $fieldSettingsByType, $fieldMap, $qrSettings);
        if ($sheetIndex < $lastSheet) echo '<div class="pdf-page-break"></div>';
    }
}
?>
