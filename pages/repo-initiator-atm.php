<?php
require_once 'auth.php';
auth_require_login();
require_once 'assets/vendor/autoload.php';
require_once 'db_connect.php';
require_once 'atm_config.php';

ini_set('pcre.backtrack_limit', '20000000');

function pvc_card_image_src($source)
{
    if (is_string($source) && strpos($source, 'data:image/') === 0) {
        return $source;
    }

    if (!is_string($source) || !is_file($source)) {
        return '';
    }

    $contents = @file_get_contents($source);
    if ($contents === false) {
        return '';
    }

    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $mime = $extension === 'png' ? 'image/png' : ($extension === 'webp' ? 'image/webp' : 'image/jpeg');
    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function pvc_add_card_page($mpdf, $imageSource, $pageIndex)
{
    if ($pageIndex > 0) {
        $mpdf->AddPageByArray([
            'orientation' => 'P',
            'sheet-size' => [85.60, 53.98],
            'margin-left' => 0,
            'margin-right' => 0,
            'margin-top' => 0,
            'margin-bottom' => 0,
            'margin-header' => 0,
            'margin-footer' => 0,
        ]);
    }

    $imageSrc = pvc_card_image_src($imageSource);
    if ($imageSrc === '') {
        return false;
    }

    $html = '<img src="' . htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') . '" style="display:block;width:86mm;height:54.38mm;margin:0;padding:0;border:0;">';
    // Slight negative positioning/oversize hides tiny printer/PDF rounding edges on CR80 PVC cards.
    $mpdf->WriteFixedPosHTML($html, -0.20, -0.20, 86.00, 54.38, 'hidden');
    return true;
}

$from = filter_input(INPUT_POST, 'from', FILTER_VALIDATE_INT);
$to = filter_input(INPUT_POST, 'to', FILTER_VALIDATE_INT);
$outputMode = isset($_POST['output_mode']) && $_POST['output_mode'] === 'pvc' ? 'pvc' : 'sheet';
if ($from === false || $from === null || $to === false || $to === null || $from < 1 || $to < $from) {
    http_response_code(400);
    exit('Please enter a valid certificate range.');
}

$userId = auth_current_user_id();
$hasFormUserId = atm_table_has_column($conn, 'sm_form_data', 'user_id');
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$statement = $hasFormUserId
    ? $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND certi_no BETWEEN ? AND ? ORDER BY certi_no ASC")
    : $conn->prepare("SELECT * FROM sm_form_data WHERE certi_no BETWEEN ? AND ? ORDER BY certi_no ASC");
if (!$statement) {
    http_response_code(500);
    exit('Unable to prepare certificate range query: ' . $conn->error);
}
if ($hasFormUserId) {
    $statement->bind_param('ii', $from, $to);
} else {
    $statement->bind_param('ii', $from, $to);
}
$statement->execute();
$result = $statement->get_result();
$records = [];
while ($row = $result->fetch_assoc()) $records[] = $row;
$statement->close();

if (!$records) {
    http_response_code(404);
    exit('No certificates were found for this account in that range. Please confirm the hosted database contains these reports for the logged-in user.');
}

$positionsByType = [
    'S' => atm_read_positions('S'),
    'D' => atm_read_positions('D'),
    'J' => atm_read_positions('J'),
    'R' => atm_read_positions('R'),
];
$fieldSettingsByType = [
    'S' => atm_read_json(atm_layout_file('S', 'settings'), atm_default_fields()),
    'D' => atm_read_json(atm_layout_file('D', 'settings'), atm_default_fields()),
    'J' => atm_read_json(atm_layout_file('J', 'settings'), array_map(function ($field) { return $field['display']; }, atm_default_positions('J')['fields'])),
    'R' => array_map(function ($field) { return $field['display']; }, atm_default_positions('R')['fields']),
];
foreach ($records as $record) {
    $layoutType = atm_record_layout_type($record);
    if (!isset($positionsByType[$layoutType])) {
        $positionsByType[$layoutType] = atm_read_positions($layoutType);
        $fieldSettingsByType[$layoutType] = atm_read_json(
            atm_layout_file($layoutType, 'settings'),
            array_map(function ($field) { return $field['display']; }, atm_default_positions($layoutType)['fields'])
        );
    }
}
$printSettings = atm_read_json(atm_user_file('atm-print-settings.json'), atm_default_print_settings());
$qrSettings = atm_read_qr_settings($conn);

$pdfOptions = $outputMode === 'pvc'
    ? [
        'mode' => 'utf-8',
        'format' => [85.60, 53.98],
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
        'margin_header' => 0,
        'margin_footer' => 0,
    ]
    : [
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
    ];

$mpdf = new \Mpdf\Mpdf($pdfOptions);
$mpdf->SetAutoPageBreak(false, 0);

if ($outputMode === 'pvc') {
    require_once 'atm-card-renderer.php';

    $frontImage = isset($printSettings['frontImage']) ? trim((string) $printSettings['frontImage']) : '';
    $frontFile = $frontImage !== '' ? __DIR__ . '/' . $frontImage : '';
    if (!is_file($frontFile)) {
        http_response_code(422);
        exit('Please upload an ATM front card image before generating PVC cards.');
    }

    $backImage = isset($printSettings['backImage']) ? trim((string) $printSettings['backImage']) : '';
    $backFile = $backImage !== '' ? __DIR__ . '/' . $backImage : '';
    if (!empty($printSettings['includeBack']) && !is_file($backFile)) {
        http_response_code(422);
        exit('Please upload an ATM back card image or disable back printing before generating PVC cards.');
    }

    $fieldMap = atm_field_map('S');

    if (isset($positionsByType['J']['fields'])) {
        if (isset($positionsByType['J']['fields']['reportNo'])) $positionsByType['J']['fields']['reportNo']['label'] = 'Report Number';
        if (isset($positionsByType['J']['fields']['weight'])) $positionsByType['J']['fields']['weight']['label'] = 'Gross Weight';
        if (isset($positionsByType['J']['fields']['shapeCut'])) $positionsByType['J']['fields']['shapeCut']['label'] = 'Shape';
        if (isset($positionsByType['J']['fields']['colour'])) $positionsByType['J']['fields']['colour']['label'] = 'Color';
        if (isset($positionsByType['J']['fields']['face'])) {
            $positionsByType['J']['fields']['face']['label'] = 'Diamond Pcs';
            $positionsByType['J']['fields']['face']['column'] = 'stone_pcs';
        }
        if (isset($positionsByType['J']['fields']['remarks'])) $positionsByType['J']['fields']['remarks']['label'] = 'Comments';
    }

    $pages = [];
    foreach ($records as $record) {
        $recordType = atm_record_layout_type($record);
        if (!isset($positionsByType[$recordType])) {
            $recordType = atm_base_report_type($recordType);
        }
        $frontCard = atm_render_front_card(
            $record,
            $positionsByType[$recordType],
            $fieldSettingsByType[$recordType],
            $fieldMap,
            $frontFile,
            $qrSettings
        );
        if ($frontCard !== '') {
            $pages[] = $frontCard;
        }
        if (!empty($printSettings['includeBack'])) {
            $pages[] = $backFile;
        }
    }

    if (!$pages) {
        http_response_code(500);
        exit('Unable to render PVC cards. Please confirm the card front image exists and PHP GD image support is enabled.');
    }

    $writtenPages = 0;
    foreach ($pages as $cardSource) {
        if (pvc_add_card_page($mpdf, $cardSource, $writtenPages)) {
            $writtenPages++;
        }
    }

    if ($writtenPages === 0) {
        http_response_code(500);
        exit('Unable to write PVC card pages into the PDF.');
    }
} else {
    ob_start();
    include 'atm-report-template-v3.php';
    $html = ob_get_clean();
    $mpdf->WriteHTML($html);
}

$mpdf->SetDisplayMode('fullpage');
$mpdf->SetAuthor('SMARTLINK SOFT');
$mpdf->SetTitle(($outputMode === 'pvc' ? 'PVC Card Certificates ' : 'ATM Certificates ') . $from . '-' . $to);
$mpdf->SetCreator('SMARTLINK SOFT');
$filenamePrefix = $outputMode === 'pvc' ? 'pvc-card-certificates-' : 'atm-certificates-';
$mpdf->Output($filenamePrefix . $from . '-' . $to . '.pdf', \Mpdf\Output\Destination::INLINE);
