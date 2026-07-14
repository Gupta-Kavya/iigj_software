<?php
require_once 'auth.php';
require_once 'user_branch_helper.php';

function atm_default_fields()
{
    return [
        'reportNo' => 'block',
        'date' => 'block',
        'stoneName' => 'block',
        'weight' => 'block',
        'shapeCut' => 'block',
        'dimension' => 'block',
        'colour' => 'block',
        'opticCharacter' => 'none',
        'refractiveIndex' => 'block',
        'specificGravity' => 'block',
        'magnification' => 'none',
        'speciesGroup' => 'block',
        'origin' => 'none',
        'hardness' => 'none',
        'remarks' => 'block',
        'issuedTo' => 'none',
        'diamondWeight' => 'none',
        'clarity' => 'none',
        'finish' => 'none',
        'cutGrade' => 'none',
        'tableValue' => 'none',
        'crown' => 'none',
        'girdle' => 'none',
        'pavilionDepth' => 'none',
        'tableDepth' => 'none',
        'fluorescence' => 'none',
        'description' => 'none',
        'face' => 'none',
        'specificComments' => 'none',
        'testCarriedOut' => 'none',
        'rudrakshaRemarks' => 'none',
        'agreementNo' => 'block',
        'certificateNo' => 'block',
        'reportTypeName' => 'block',
        'itemDescription' => 'block',
        'grossWeight' => 'block',
        'grossUnit' => 'none',
        'stoneUnit' => 'none',
        'testedPcs' => 'block',
        'pcs' => 'none',
        'testedPcsRemark' => 'none',
        'totalStones' => 'none',
        'stoneWeight1' => 'block',
        'stoneWeight2' => 'none',
        'stoneWeight3' => 'none',
        'stoneWeight4' => 'none',
        'stoneWeight5' => 'none',
        'stoneWeight6' => 'none',
        'stoneWeight7' => 'none',
        'stoneWeight8' => 'none',
        'measurement1' => 'block',
        'measurement2' => 'none',
        'measurement3' => 'none',
        'measurement4' => 'none',
        'measurement5' => 'none',
        'lengthTested' => 'block',
        'riValue' => 'block',
        'sgValue' => 'block',
        'opticValue' => 'block',
        'speciesMode' => 'none',
        'variety' => 'block',
        'treatmentComment1' => 'block',
        'treatmentComment2' => 'none',
        'treatmentTitle1' => 'none',
        'treatmentTitle2' => 'none',
        'description2' => 'none',
        'description3' => 'none',
        'treatmentTitle3' => 'none',
        'prefix1' => 'none',
        'prefix2' => 'none',
        'ebayProductNo' => 'none',
        'reportTypeId' => 'none',
        'baseType' => 'none',
        'location' => 'none',
        'reportSize' => 'none',
        'testTickRi' => 'block',
        'testTickSg' => 'block',
        'testTickMagnification' => 'block',
        'testTickUvFluorescence' => 'block',
        'testTickAbsSpectrum' => 'block',
        'testTickIrSpectrum' => 'block',
        'testTickEdxrf' => 'block',
        'testTickLrs' => 'block',
        'testTickUvVisNir' => 'block',
        'testTickLaIcpms' => 'block',
        'testTickXray' => 'block',
        'testTickUvImaging' => 'block',
    ];
}

function atm_field_definitions($type = 'S')
{
    $type = atm_report_type($type);
    $baseType = atm_base_report_type($type);
    $definitions = [
        'reportNo' => ['label' => 'Report No', 'column' => 'report_no'],
        'date' => ['label' => 'Date', 'column' => 'date'],
        'stoneName' => ['label' => 'Stone Name', 'column' => 'stone_name'],
        'weight' => ['label' => 'Weight', 'column' => 'stone_wt'],
        'shapeCut' => ['label' => 'Shape / Cut', 'column' => 'shape_cut'],
        'dimension' => ['label' => 'Dimension', 'column' => 'dimension'],
        'colour' => ['label' => 'Colour', 'column' => 'color'],
        'opticCharacter' => ['label' => 'Optic Character', 'column' => 'optic_char'],
        'refractiveIndex' => ['label' => 'Refractive Index', 'column' => 'ref_index'],
        'specificGravity' => ['label' => 'Specific Gravity', 'column' => 'spe_gravit'],
        'magnification' => ['label' => 'Magnification', 'column' => 'magni'],
        'speciesGroup' => ['label' => 'Species / Group', 'column' => 'spe_group'],
        'origin' => ['label' => 'Origin', 'column' => 'origin'],
        'hardness' => ['label' => 'Hardness', 'column' => 'hardness'],
        'remarks' => ['label' => 'Comment', 'column' => 'comment'],
        'issuedTo' => ['label' => 'Issued To', 'column' => 'issued_to'],
        'diamondWeight' => ['label' => 'Diamond Weight', 'column' => 'dia_wt'],
        'clarity' => ['label' => 'Clarity', 'column' => 'clarity'],
        'finish' => ['label' => 'Finish', 'column' => 'finish'],
        'cutGrade' => ['label' => 'Cut', 'column' => 'cut'],
        'tableValue' => ['label' => 'Table', 'column' => 'table'],
        'crown' => ['label' => 'Crown Height', 'column' => 'crown'],
        'girdle' => ['label' => 'Girdle', 'column' => 'girdle'],
        'pavilionDepth' => ['label' => 'Pavilion Depth', 'column' => 'pav_depth'],
        'tableDepth' => ['label' => 'Table Depth', 'column' => 'tab_depth'],
        'fluorescence' => ['label' => 'Fluorescence', 'column' => 'flurance'],
        'description' => ['label' => 'Description', 'column' => 'desc'],
        'face' => ['label' => 'Face', 'column' => 'faces'],
        'specificComments' => ['label' => 'Specific Comments', 'column' => 'comment'],
        'testCarriedOut' => ['label' => 'Test Carried Out', 'column' => 'rem2'],
        'rudrakshaRemarks' => ['label' => 'Remarks', 'column' => 'rem1'],
        'agreementNo' => ['label' => 'Agreement No', 'column' => 'ag_no'],
        'certificateNo' => ['label' => 'Certificate No', 'column' => 'certi_no'],
        'reportTypeName' => ['label' => 'Report Type', 'column' => 'category'],
        'itemDescription' => ['label' => 'Item', 'column' => 'desc1'],
        'grossWeight' => ['label' => 'Gross Weight', 'column' => 'gross_wt'],
        'grossUnit' => ['label' => 'Gross Unit', 'column' => 'unit_grs'],
        'stoneUnit' => ['label' => 'Stone Unit', 'column' => 'unit_stn'],
        'testedPcs' => ['label' => 'No. of Tested Pieces', 'column' => 'testd_pcs'],
        'pcs' => ['label' => 'PCS', 'column' => 'pcs'],
        'testedPcsRemark' => ['label' => 'Tested Pcs Remark', 'column' => 'tpremark'],
        'totalStones' => ['label' => 'Total Stone', 'column' => 'tot_stone'],
        'stoneWeight1' => ['label' => 'Stone Weight 1', 'column' => 'stone_wt1'],
        'stoneWeight2' => ['label' => 'Stone Weight 2', 'column' => 'stone_wt2'],
        'stoneWeight3' => ['label' => 'Stone Weight 3', 'column' => 'stone_wt3'],
        'stoneWeight4' => ['label' => 'Stone Weight 4', 'column' => 'stone_wt4'],
        'stoneWeight5' => ['label' => 'Stone Weight 5', 'column' => 'stone_wt5'],
        'stoneWeight6' => ['label' => 'Stone Weight 6', 'column' => 'stone_wt6'],
        'stoneWeight7' => ['label' => 'Stone Weight 7', 'column' => 'stone_wt7'],
        'stoneWeight8' => ['label' => 'Stone Weight 8', 'column' => 'stone_wt8'],
        'measurement1' => ['label' => 'Measurement 1', 'column' => 'dime1'],
        'measurement2' => ['label' => 'Measurement 2', 'column' => 'dime2'],
        'measurement3' => ['label' => 'Measurement 3', 'column' => 'dime3'],
        'measurement4' => ['label' => 'Measurement 4', 'column' => 'dime4'],
        'measurement5' => ['label' => 'Measurement 5', 'column' => 'dime5'],
        'lengthTested' => ['label' => 'Length Tested', 'column' => 'bead_lenth'],
        'riValue' => ['label' => 'R.I.', 'column' => 'ri'],
        'sgValue' => ['label' => 'S.G.', 'column' => 'sg'],
        'opticValue' => ['label' => 'Optic Char.', 'column' => 'optic'],
        'speciesMode' => ['label' => 'Species Mode', 'column' => 'title_rem'],
        'variety' => ['label' => 'Variety', 'column' => 'variety'],
        'treatmentComment1' => ['label' => 'Treatment Comment 1', 'column' => 'trtcoment1'],
        'treatmentComment2' => ['label' => 'Treatment Comment 2', 'column' => 'trtcoment2'],
        'treatmentTitle1' => ['label' => 'Treatment Title 1', 'column' => 'title_rem1'],
        'treatmentTitle2' => ['label' => 'Treatment Title 2', 'column' => 'title_rem2'],
        'description2' => ['label' => 'Description 2', 'column' => 'desc2'],
        'description3' => ['label' => 'Description 3', 'column' => 'desc3'],
        'treatmentTitle3' => ['label' => 'Treatment Title 3', 'column' => 'title_rem3'],
        'prefix1' => ['label' => 'Prefix 1', 'column' => 'prefix1'],
        'prefix2' => ['label' => 'Prefix 2', 'column' => 'prefix2'],
        'ebayProductNo' => ['label' => 'Ebay Product No.', 'column' => 'productno'],
        'reportTypeId' => ['label' => 'Report Type ID', 'column' => 'report_typ'],
        'baseType' => ['label' => 'Base Type', 'column' => 'type'],
        'location' => ['label' => 'Location', 'column' => 'location'],
        'reportSize' => ['label' => 'Report Size', 'column' => 'repsize'],
        'format' => ['label' => 'Format', 'column' => 'format'],
        'reptype' => ['label' => 'Report Type Code', 'column' => 'reptype'],
        'id' => ['label' => 'ID', 'column' => 'id'],
        'unit' => ['label' => 'Unit', 'column' => 'unit'],
        'sign1' => ['label' => 'Sign 1', 'column' => 'sign1'],
        'sign2' => ['label' => 'Sign 2', 'column' => 'sign2'],
        'finish1' => ['label' => 'Finish 1', 'column' => 'finish1'],
        'clarity1' => ['label' => 'Clarity 1', 'column' => 'clarity1'],
        'diamondPcs' => ['label' => 'Diamond PCS', 'column' => 'diapcs'],
        'diamondWeight1' => ['label' => 'Diamond Weight 1', 'column' => 'diawt1'],
        'diamondPcs1' => ['label' => 'Diamond PCS 1', 'column' => 'diapcs1'],
        'diamondPcs2' => ['label' => 'Diamond PCS 2', 'column' => 'diapcs2'],
        'diamondPcs3' => ['label' => 'Diamond PCS 3', 'column' => 'diapcs3'],
        'goldPurity' => ['label' => 'Gold Purity', 'column' => 'gold_purit'],
        'cutGradeAlt' => ['label' => 'Cut Grade', 'column' => 'cutgrade'],
        'clarityGrade' => ['label' => 'Clarity Grade', 'column' => 'clr_grade'],
        'diamondColour' => ['label' => 'Diamond Colour', 'column' => 'dc'],
        'symmetry' => ['label' => 'Symmetry', 'column' => 'symmetry'],
        'tableSize' => ['label' => 'Table Size', 'column' => 'table_size'],
        'pavilionDepthAlt' => ['label' => 'Pavilion Depth', 'column' => 'pavi_depth'],
        'culet' => ['label' => 'Culet', 'column' => 'culet'],
        'fluorescenceAlt' => ['label' => 'Fluorescence', 'column' => 'flurence'],
        'additionalDepth' => ['label' => 'Additional Depth', 'column' => 'ad_depth'],
        'additionalAngle' => ['label' => 'Additional Angle', 'column' => 'ad_angle'],
        'additionalPavilion' => ['label' => 'Additional Pavilion', 'column' => 'ad_pavi'],
        'additionalLength' => ['label' => 'Additional Length', 'column' => 'ad_length'],
        'additionalLowerHalf' => ['label' => 'Additional Lower Half', 'column' => 'ad_lwr_hf'],
        'additional' => ['label' => 'Additional', 'column' => 'additional'],
        'naturalSynthetic' => ['label' => 'Natural/Synthetic', 'column' => 'nat_syn'],
        'naturalDiamondWeight' => ['label' => 'Natural Dia Wt', 'column' => 'nat_dia_wt'],
        'syntheticDiamondWeight' => ['label' => 'Synthetic Dia Wt', 'column' => 'syn_dia_wt'],
        'referenceDiamondWeight' => ['label' => 'Reference Dia Wt', 'column' => 'ref_dia_wt'],
        'nonDiamondWeight' => ['label' => 'Non Dia Wt', 'column' => 'non_dia_wt'],
        'naturalDiamondPcs' => ['label' => 'Natural Dia PCS', 'column' => 'nat_dia_pc'],
        'syntheticDiamondPcs' => ['label' => 'Synthetic Dia PCS', 'column' => 'syn_dia_pc'],
        'referenceDiamondPcs' => ['label' => 'Reference Dia PCS', 'column' => 'ref_dia_pc'],
        'nonDiamondPcs' => ['label' => 'Non Dia PCS', 'column' => 'non_dia_pc'],
        'ns' => ['label' => 'N/S', 'column' => 'n_s'],
        'ws1' => ['label' => 'WS1', 'column' => 'WS1'],
        'ws2' => ['label' => 'WS2', 'column' => 'WS2'],
        'ws3' => ['label' => 'WS3', 'column' => 'WS3'],
        'ws4' => ['label' => 'WS4', 'column' => 'WS4'],
        'ws5' => ['label' => 'WS5', 'column' => 'WS5'],
        'ws6' => ['label' => 'WS6', 'column' => 'WS6'],
        'ws7' => ['label' => 'WS7', 'column' => 'WS7'],
        'diamondSymbols' => ['label' => 'Diamond Symbols JSON', 'column' => 'diamond_symbols_json'],
        'cr1' => ['label' => 'CR1', 'column' => 'cr1'],
        'cr2' => ['label' => 'CR2', 'column' => 'cr2'],
        'cr3' => ['label' => 'CR3', 'column' => 'cr3'],
        'cr4' => ['label' => 'CR4', 'column' => 'cr4'],
        'cr5' => ['label' => 'CR5', 'column' => 'cr5'],
        'cr6' => ['label' => 'CR6', 'column' => 'cr6'],
        'cr7' => ['label' => 'CR7', 'column' => 'cr7'],
        'cr8' => ['label' => 'CR8', 'column' => 'cr8'],
        'cs1' => ['label' => 'CS1', 'column' => 'cs1'],
        'cs2' => ['label' => 'CS2', 'column' => 'cs2'],
        'cs3' => ['label' => 'CS3', 'column' => 'cs3'],
        'cs4' => ['label' => 'CS4', 'column' => 'cs4'],
        'cs5' => ['label' => 'CS5', 'column' => 'cs5'],
        'cs6' => ['label' => 'CS6', 'column' => 'cs6'],
        'cs7' => ['label' => 'CS7', 'column' => 'cs7'],
        'cs8' => ['label' => 'CS8', 'column' => 'cs8'],
        'testTickRi' => ['label' => 'RI Tick', 'column' => 'tri', 'valueType' => 'tick'],
        'testTickSg' => ['label' => 'SG Tick', 'column' => 'tsg', 'valueType' => 'tick'],
        'testTickMagnification' => ['label' => 'Magnification Tick', 'column' => 'tmag', 'valueType' => 'tick'],
        'testTickUvFluorescence' => ['label' => 'UV Fluorescence Tick', 'column' => 'tuvf', 'valueType' => 'tick'],
        'testTickAbsSpectrum' => ['label' => 'ABS Spectrum Tick', 'column' => 'tabs', 'valueType' => 'tick'],
        'testTickIrSpectrum' => ['label' => 'IR Spectrum Tick', 'column' => 'tirs', 'valueType' => 'tick'],
        'testTickEdxrf' => ['label' => 'EDXRF Tick', 'column' => 'tedxrf', 'valueType' => 'tick'],
        'testTickLrs' => ['label' => 'LRS Tick', 'column' => 'tlrs', 'valueType' => 'tick'],
        'testTickUvVisNir' => ['label' => 'UV-VIS-NIR Tick', 'column' => 'tuvnir', 'valueType' => 'tick'],
        'testTickLaIcpms' => ['label' => 'LA-ICPMS Tick', 'column' => 'tlaicpms', 'valueType' => 'tick'],
        'testTickXray' => ['label' => 'X-Ray Tick', 'column' => 'txray', 'valueType' => 'tick'],
        'testTickUvImaging' => ['label' => 'UV Imaging Tick', 'column' => 'tuvimg', 'valueType' => 'tick'],
    ];

    if ($baseType === 'J') {
        $definitions['reportNo']['label'] = 'Report Number';
        $definitions['weight']['label'] = 'Gross Weight';
        $definitions['shapeCut']['label'] = 'Shape';
        $definitions['colour']['label'] = 'Color';
        $definitions['remarks']['label'] = 'Comments';
        $definitions['face']['label'] = 'Diamond Pcs';
        $definitions['face']['column'] = 'stone_pcs';
        $definitions['goldPurity']['label'] = 'Metal Type / Gold Purity';
        for ($i = 1; $i <= 7; $i++) {
            $definitions['cr' . $i]['label'] = 'Colour Stone Colour ' . $i;
            $definitions['cs' . $i]['label'] = 'Colour Stone Name ' . $i;
            $definitions['stoneWeight' . $i]['label'] = 'Colour Stone Weight ' . $i;
        }
    }
    if ($baseType === 'D') {
        $definitions['weight']['label'] = 'Carat Weight';
        $definitions['weight']['column'] = 'diawt1';
        $definitions['diamondWeight']['label'] = 'Carat Weight';
        $definitions['diamondWeight']['column'] = 'diawt1';
        $definitions['dimension']['label'] = 'Measurement';
        $definitions['dimension']['column'] = 'dime1';
        $definitions['tableValue']['column'] = 'table_size';
        $definitions['pavilionDepth']['column'] = 'pavi_depth';
        $definitions['fluorescence']['column'] = 'flurence';
    }
    if ($baseType === 'DS') {
        $definitions['weight']['label'] = 'Total Weight';
        $definitions['face']['label'] = 'Total Pcs';
        $definitions['face']['column'] = 'pcs';
        $definitions['pcs']['label'] = 'Total Pcs';
        $definitions['testedPcs']['label'] = 'Total Pcs';
        $definitions['shapeCut']['label'] = 'Shape And Cut';
        $definitions['naturalDiamondWeight']['label'] = 'Natural Diamond Weight';
        $definitions['syntheticDiamondWeight']['label'] = 'Synthetic Diamond Weight';
        $definitions['referenceDiamondWeight']['label'] = 'Referal Weight';
        $definitions['nonDiamondWeight']['label'] = 'Non Diamond Weight';
        $definitions['naturalDiamondPcs']['label'] = 'Natural Diamond Pcs';
        $definitions['syntheticDiamondPcs']['label'] = 'Synthetic Diamond Pcs';
        $definitions['referenceDiamondPcs']['label'] = 'Referal Pcs';
        $definitions['nonDiamondPcs']['label'] = 'Non Diamond Pcs';
    }

    return $definitions;
}

function atm_field_map($type = 'S')
{
    $fieldMap = [];
    foreach (atm_field_definitions($type) as $key => $definition) {
        $fieldMap[$key] = [
            $definition['label'],
            $definition['column'],
            $definition['valueType'] ?? '',
        ];
    }
    return $fieldMap;
}

function atm_truthy_tick_value($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (float) $value > 0;
    }
    $value = strtolower(trim((string) $value));
    return in_array($value, ['1', 'yes', 'true', 'on', 'checked', 'tick', '✓', '✔'], true);
}

function atm_read_json($filename, $defaults)
{
    if (!is_file($filename)) {
        $legacy = __DIR__ . '/user_data/user_' . auth_current_user_id() . '/' . basename($filename);
        if (is_file($legacy)) {
            $filename = $legacy;
        } else {
            return $defaults;
        }
    }

    $decoded = json_decode(file_get_contents($filename), true);
    return is_array($decoded) ? array_replace_recursive($defaults, $decoded) : $defaults;
}

function atm_legacy_user_file($filename, $userId = 0)
{
    $userId = $userId > 0 ? (int) $userId : auth_current_user_id();
    return __DIR__ . '/user_data/user_' . $userId . '/' . basename($filename);
}

function atm_branch_user_file_for_user($conn, $userId, $filename)
{
    return __DIR__ . '/user_data/' . user_branch_storage_code($conn, $userId) . '/' . basename($filename);
}

function atm_branch_image_path_for_user($conn, $userId, $certiNo, $folder = 'st_images', $extensions = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'])
{
    $rawName = trim((string) $certiNo);
    $rawName = preg_replace('/[^0-9A-Za-z _.-]/', '', $rawName);
    if ($rawName === '') {
        return '';
    }
    $nameCandidates = array_values(array_unique(array_filter([
        $rawName,
        str_replace(' ', '_', $rawName),
        str_replace(' ', '-', $rawName),
        preg_replace('/[^0-9A-Za-z_-]/', '', $rawName),
        strtolower($rawName),
        strtolower(str_replace(' ', '_', $rawName)),
        strtolower(str_replace(' ', '-', $rawName)),
    ], function ($name) {
        return trim((string) $name) !== '';
    })));
    $folder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $folder);
    if ($folder === '') {
        $folder = 'st_images';
    }
    $storageCode = user_branch_storage_code($conn, $userId);
    $dirs = [
        __DIR__ . '/user_data/' . $storageCode . '/' . $folder,
        __DIR__ . '/user_data/user_' . (int) $userId . '/' . $folder,
        __DIR__ . '/assets/' . $folder,
    ];
    foreach ($dirs as $dir) {
        foreach ($nameCandidates as $name) {
            foreach ($extensions as $ext) {
                $path = $dir . '/' . $name . '.' . $ext;
                if (is_file($path)) {
                    return $path;
                }
            }
        }
    }
    return '';
}

function atm_branch_stone_path_for_user($conn, $userId, $certiNo, $extensions = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'])
{
    return atm_branch_image_path_for_user($conn, $userId, $certiNo, 'st_images', $extensions);
}

function atm_read_json_direct($filename, $defaults)
{
    if (!is_file($filename)) {
        return $defaults;
    }

    $decoded = json_decode(file_get_contents($filename), true);
    return is_array($decoded) ? array_replace_recursive($defaults, $decoded) : $defaults;
}

function atm_table_has_column($conn, $table, $column)
{
    if (!$conn) {
        return false;
    }
    $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
    $column = (string) $column;
    if ($table === '' || $column === '') {
        return false;
    }
    $result = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
    return $result && $result->num_rows > 0;
}

function atm_certificate_branch_location($conn, $userId)
{
    if (!$conn || !function_exists('user_branch_location_for_user')) {
        return '';
    }
    return user_branch_location_for_user($conn, $userId);
}

function atm_form_data_certificate_scope_sql($conn, $userId)
{
    $branchLocation = atm_certificate_branch_location($conn, $userId);
    if ($branchLocation !== '' && atm_table_has_column($conn, 'sm_form_data', 'location')) {
        return "`location` = '" . $conn->real_escape_string($branchLocation) . "'";
    }
    if (atm_table_has_column($conn, 'sm_form_data', 'user_id')) {
        return user_branch_scope_sql($conn, $userId, 'user_id');
    }
    return '1=1';
}

function atm_form_masters_certificate_scope($conn, $userId)
{
    $branchLocation = atm_certificate_branch_location($conn, $userId);
    if (
        $branchLocation !== ''
        && atm_table_has_column($conn, 'sm_form_masters', 'agreement_id')
        && atm_table_has_column($conn, 'sm_stone_agreements', 'agreement_branch_location')
    ) {
        return [
            'join' => 'INNER JOIN sm_stone_agreements a ON a.id = m.agreement_id',
            'where' => "a.agreement_branch_location = '" . $conn->real_escape_string($branchLocation) . "'",
        ];
    }
    if (atm_table_has_column($conn, 'sm_form_masters', 'user_id')) {
        return [
            'join' => '',
            'where' => user_branch_scope_sql($conn, $userId, 'user_id'),
        ];
    }
    return [
        'join' => '',
        'where' => '1=1',
    ];
}

function atm_last_certificate_number($conn, $userId)
{
    $last = 0;
    if (atm_table_has_column($conn, 'sm_form_data', 'certi_no')) {
        $scopeSql = atm_form_data_certificate_scope_sql($conn, $userId);
        $result = @$conn->query("SELECT MAX(certi_no) AS last_certi_no FROM sm_form_data WHERE {$scopeSql}");
        $row = $result ? $result->fetch_assoc() : null;
        $last = max($last, (int) ($row['last_certi_no'] ?? 0));
    }
    if (atm_table_has_column($conn, 'sm_form_masters', 'certi_no')) {
        $scope = atm_form_masters_certificate_scope($conn, $userId);
        $result = @$conn->query("SELECT MAX(m.certi_no) AS last_certi_no FROM sm_form_masters m {$scope['join']} WHERE {$scope['where']}");
        $row = $result ? $result->fetch_assoc() : null;
        $last = max($last, (int) ($row['last_certi_no'] ?? 0));
    }
    return $last;
}

function atm_user_dir()
{
    $userId = auth_current_user_id();
    $storageCode = isset($GLOBALS['conn']) ? user_branch_storage_code($GLOBALS['conn'], $userId) : ('user_' . $userId);
    $dir = __DIR__ . '/user_data/' . $storageCode;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function atm_user_file($filename)
{
    return atm_user_dir() . '/' . basename($filename);
}

function atm_user_asset_dir()
{
    $dir = atm_user_dir() . '/assets';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function atm_user_asset_relative($filename)
{
    $userId = auth_current_user_id();
    $storageCode = isset($GLOBALS['conn']) ? user_branch_storage_code($GLOBALS['conn'], $userId) : ('user_' . $userId);
    return 'user_data/' . $storageCode . '/assets/' . basename($filename);
}

function atm_builder_asset_path($relativePath)
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if ($relativePath === '' || strpos($relativePath, 'user_data/') !== 0) {
        return '';
    }
    $path = realpath(__DIR__ . '/' . $relativePath);
    $base = realpath(__DIR__ . '/user_data');
    if (!$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
        return '';
    }
    return $path;
}

function atm_normalize_additional_images($images, $maxX, $maxY, $defaultW = 80, $defaultH = 80)
{
    if (!is_array($images)) {
        return [];
    }
    $normalized = [];
    foreach ($images as $index => $image) {
        if (!is_array($image)) {
            continue;
        }
        $src = str_replace('\\', '/', trim((string) ($image['src'] ?? '')));
        if ($src === '' || strpos($src, 'user_data/') !== 0 || atm_builder_asset_path($src) === '') {
            continue;
        }
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($image['id'] ?? ''));
        if ($id === '') {
            $id = 'img_' . ($index + 1);
        }
        $label = trim((string) ($image['label'] ?? 'Image ' . ($index + 1)));
        $normalized[] = [
            'id' => substr($id, 0, 60),
            'label' => substr($label !== '' ? $label : 'Image ' . ($index + 1), 0, 80),
            'src' => $src,
            'display' => atm_display_value($image['display'] ?? 'block'),
            'x' => atm_clamp($image['x'] ?? 0, 0, $maxX),
            'y' => atm_clamp($image['y'] ?? 0, 0, $maxY),
            'w' => atm_clamp($image['w'] ?? $defaultW, 10, $maxX),
            'h' => atm_clamp($image['h'] ?? $defaultH, 10, $maxY),
        ];
    }
    return $normalized;
}

function atm_user_stone_dir()
{
    return atm_user_image_dir('st_images');
}

function atm_user_image_dir($folder = 'st_images')
{
    $folder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $folder);
    if ($folder === '') {
        $folder = 'st_images';
    }
    $dir = atm_user_dir() . '/' . $folder;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function atm_user_stone_relative($filename)
{
    return atm_user_image_relative($filename, 'st_images');
}

function atm_user_image_relative($filename, $folder = 'st_images')
{
    $userId = auth_current_user_id();
    $storageCode = isset($GLOBALS['conn']) ? user_branch_storage_code($GLOBALS['conn'], $userId) : ('user_' . $userId);
    $folder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $folder);
    if ($folder === '') {
        $folder = 'st_images';
    }
    return 'user_data/' . $storageCode . '/' . $folder . '/' . $filename;
}

function atm_display_value($value)
{
    return $value === 'none' ? 'none' : 'block';
}

function atm_report_type($type)
{
    $type = strtoupper((string) $type);
    if (preg_match('/^(CS|PR|JT|DS|DG)[0-9]+$/', $type)) {
        return $type;
    }
    return in_array($type, ['D', 'J', 'DS', 'R', 'P'], true) ? $type : 'S';
}

function atm_base_report_type($type)
{
    $type = atm_report_type($type);
    if (preg_match('/^CS[0-9]+$/', $type)) {
        return 'S';
    }
    if (preg_match('/^PR[0-9]+$/', $type)) {
        return 'P';
    }
    if (preg_match('/^JT[0-9]+$/', $type)) {
        return 'J';
    }
    if (preg_match('/^DS[0-9]+$/', $type)) {
        return 'DS';
    }
    if (preg_match('/^DG[0-9]+$/', $type)) {
        return 'D';
    }
    return $type;
}

function cstone_report_type_master_ready($conn)
{
    $ready = (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_colour_stone_report_types` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL DEFAULT 1,
        `base_type` varchar(2) NOT NULL DEFAULT 'S',
        `report_name` varchar(160) NOT NULL,
        `report_format` varchar(20) NOT NULL DEFAULT 'a4',
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_colour_report_type_user` (`user_id`,`base_type`,`active`,`report_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$ready) {
        return false;
    }
    $formatColumn = @$conn->query("SHOW COLUMNS FROM sm_colour_stone_report_types LIKE 'report_format'");
    if (!$formatColumn || $formatColumn->num_rows === 0) {
        @$conn->query("ALTER TABLE sm_colour_stone_report_types ADD report_format varchar(20) NOT NULL DEFAULT 'a4' AFTER report_name");
        @$conn->query("UPDATE sm_colour_stone_report_types SET report_format = 'postcard' WHERE LOWER(report_name) LIKE '%post card%' OR LOWER(report_name) LIKE '%postcard%'");
        @$conn->query("UPDATE sm_colour_stone_report_types SET report_format = 'atm' WHERE report_format = 'a4' AND LOWER(report_name) LIKE '%card size%'");
    }
    $baseTypeColumn = @$conn->query("SHOW COLUMNS FROM sm_colour_stone_report_types LIKE 'base_type'");
    if (!$baseTypeColumn || $baseTypeColumn->num_rows === 0) {
        @$conn->query("ALTER TABLE sm_colour_stone_report_types ADD base_type varchar(2) NOT NULL DEFAULT 'S' AFTER user_id");
        @$conn->query("UPDATE sm_colour_stone_report_types SET base_type = 'S' WHERE base_type IS NULL OR base_type = ''");
    }
    $count = @$conn->query('SELECT COUNT(*) AS total FROM sm_colour_stone_report_types');
    $row = $count ? $count->fetch_assoc() : null;
    if ((int) ($row['total'] ?? 0) === 0) {
        $defaults = [
            'SET OF LOOSE STONE (3-5 PCS)',
            'SINGLE LOOSE GEM STONE LOW COST CARD SIZE',
            'SINGLE LOOSE GEM STONE LOW COST POST CARD SIZE EBAAY',
            'SINGLE LOOSE STONE',
            'STRUNG GEM STONE GROUP TESTING',
            'STRUNG GEM STONE SINGLE PIECE TESTED',
        ];
        $stmt = $conn->prepare("INSERT INTO sm_colour_stone_report_types (user_id, base_type, report_name, report_format, active) VALUES (1, 'S', ?, ?, 1)");
        if ($stmt) {
            foreach ($defaults as $name) {
                $lower = strtolower($name);
                $format = (strpos($lower, 'post card') !== false || strpos($lower, 'postcard') !== false) ? 'postcard' : (strpos($lower, 'card size') !== false ? 'atm' : 'a4');
                $stmt->bind_param('ss', $name, $format);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
    $jewelCount = @$conn->query("SELECT COUNT(*) AS total FROM sm_colour_stone_report_types WHERE base_type = 'J'");
    $jewelRow = $jewelCount ? $jewelCount->fetch_assoc() : null;
    if ((int) ($jewelRow['total'] ?? 0) === 0) {
        $defaults = [
            ['Diamond And Colour Stone Jewellery (Post Card)', 'postcard'],
            ['Colour Stone Jewellery (Post Card)', 'postcard'],
            ['Diamond Jewellery (ATM)', 'atm'],
        ];
        $stmt = $conn->prepare("INSERT INTO sm_colour_stone_report_types (user_id, base_type, report_name, report_format, active) VALUES (1, 'J', ?, ?, 1)");
        if ($stmt) {
            foreach ($defaults as $row) {
                [$name, $format] = $row;
                $stmt->bind_param('ss', $name, $format);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
    $screeningCount = @$conn->query("SELECT COUNT(*) AS total FROM sm_colour_stone_report_types WHERE base_type = 'DS'");
    $screeningRow = $screeningCount ? $screeningCount->fetch_assoc() : null;
    if ((int) ($screeningRow['total'] ?? 0) === 0) {
        $defaults = [
            ['Diamond Screening (A4)', 'a4'],
            ['Diamond Screening (ATM)', 'atm'],
            ['Diamond Screening (Postcard)', 'postcard'],
        ];
        $stmt = $conn->prepare("INSERT INTO sm_colour_stone_report_types (user_id, base_type, report_name, report_format, active) VALUES (1, 'DS', ?, ?, 1)");
        if ($stmt) {
            foreach ($defaults as $row) {
                [$name, $format] = $row;
                $stmt->bind_param('ss', $name, $format);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
    $diamondCount = @$conn->query("SELECT COUNT(*) AS total FROM sm_colour_stone_report_types WHERE base_type = 'D'");
    $diamondRow = $diamondCount ? $diamondCount->fetch_assoc() : null;
    if ((int) ($diamondRow['total'] ?? 0) === 0) {
        $defaults = [
            ['Natural Diamond Grading (A4)', 'a4'],
            ['Synthetic Diamond Grading (A4)', 'a4'],
            ['Natural Diamond Grading (ATM)', 'atm'],
        ];
        $stmt = $conn->prepare("INSERT INTO sm_colour_stone_report_types (user_id, base_type, report_name, report_format, active) VALUES (1, 'D', ?, ?, 1)");
        if ($stmt) {
            foreach ($defaults as $row) {
                [$name, $format] = $row;
                $stmt->bind_param('ss', $name, $format);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
    $pearlCount = @$conn->query("SELECT COUNT(*) AS total FROM sm_colour_stone_report_types WHERE base_type = 'P'");
    $pearlRow = $pearlCount ? $pearlCount->fetch_assoc() : null;
    if ((int) ($pearlRow['total'] ?? 0) === 0) {
        $defaults = [
            ['Pearl Report (A4)', 'a4'],
            ['Pearl Report (ATM)', 'atm'],
            ['Pearl Report (Postcard)', 'postcard'],
        ];
        $stmt = $conn->prepare("INSERT INTO sm_colour_stone_report_types (user_id, base_type, report_name, report_format, active) VALUES (1, 'P', ?, ?, 1)");
        if ($stmt) {
            foreach ($defaults as $row) {
                [$name, $format] = $row;
                $stmt->bind_param('ss', $name, $format);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
    return true;
}

function cstone_report_type_rows($conn, $userId = 0, $activeOnly = true, $baseType = '')
{
    if (!$conn || !cstone_report_type_master_ready($conn)) {
        return [];
    }
    $userId = (int) $userId;
    $baseType = strtoupper(trim((string) $baseType));
    $baseTypeSql = in_array($baseType, ['S', 'P', 'J', 'DS', 'D'], true) ? " AND base_type = '" . $conn->real_escape_string($baseType) . "'" : '';
    $scope = $userId > 0 ? '(' . user_branch_scope_sql($conn, $userId, 'user_id') . ' OR `user_id` = 1)' : '1=1';
    $activeSql = $activeOnly ? ' AND active = 1' : '';
    $result = @$conn->query("SELECT id, user_id, base_type, report_name, report_format, active FROM sm_colour_stone_report_types WHERE {$scope}{$baseTypeSql}{$activeSql} ORDER BY base_type ASC, CASE WHEN user_id = {$userId} THEN 0 ELSE 1 END, report_name ASC, id ASC");
    if (!$result) {
        return [];
    }
    $rows = [];
    $seen = [];
    while ($row = $result->fetch_assoc()) {
        $name = trim((string) ($row['report_name'] ?? ''));
        $key = strtoupper((string) ($row['base_type'] ?? 'S')) . ':' . strtolower($name);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $rows[] = $row;
    }
    return $rows;
}

function cstone_report_type_code($id, $baseType = 'S')
{
    $baseType = strtoupper(trim((string) $baseType));
    if ($baseType === 'J') {
        return 'JT' . max(0, (int) $id);
    }
    if ($baseType === 'DS') {
        return 'DS' . max(0, (int) $id);
    }
    if ($baseType === 'D') {
        return 'DG' . max(0, (int) $id);
    }
    if ($baseType === 'P') {
        return 'PR' . max(0, (int) $id);
    }
    return 'CS' . max(0, (int) $id);
}

function atm_record_layout_type($record)
{
    $baseType = atm_report_type($record['type'] ?? 'S');
    if (in_array($baseType, ['S', 'P', 'J', 'DS', 'D'], true)) {
        $reportTypeId = (int) ($record['report_typ'] ?? 0);
        if ($reportTypeId > 0) {
            return cstone_report_type_code($reportTypeId, $baseType);
        }
    }
    return $baseType;
}

function atm_report_type_labels($conn = null)
{
    $labels = [
        'S' => 'Colour Stone',
        'P' => 'Pearl',
        'D' => 'Diamond',
        'J' => 'Jewellery',
        'R' => 'Rudraksha',
        'DS' => 'Diamond Screening',
    ];
    if ($conn) {
        $userId = function_exists('auth_current_user_id') ? auth_current_user_id() : 0;
        foreach (cstone_report_type_rows($conn, $userId, true) as $row) {
            $baseType = strtoupper((string) ($row['base_type'] ?? 'S'));
            $prefix = $baseType === 'J' ? 'Diamond Jewellery' : ($baseType === 'DS' ? 'Diamond Screening' : ($baseType === 'D' ? 'Diamond Grading' : ($baseType === 'P' ? 'Pearl' : 'Colour Stone')));
            $labels[cstone_report_type_code($row['id'], $baseType)] = $prefix . ' - ' . (string) $row['report_name'];
        }
    }
    return $labels;
}

function atm_numbering_settings_defaults()
{
    return [
        'start_number' => 1,
        'report_prefix' => 'R',
        'locked' => false,
    ];
}

function atm_normalize_report_prefix($prefix)
{
    $prefix = strtoupper(trim((string) $prefix));
    $prefix = preg_replace('/[^A-Z0-9\\/_-]/', '', $prefix);
    $prefix = substr($prefix, 0, 20);
    return $prefix !== '' ? $prefix : 'R';
}

function atm_ensure_numbering_settings_table($conn)
{
    static $done = false;
    if ($done) {
        return true;
    }
    $done = true;
    return (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_certificate_number_settings` (
        `user_id` INT NOT NULL,
        `start_number` INT NOT NULL DEFAULT 1,
        `report_prefix` VARCHAR(20) NOT NULL DEFAULT 'R',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function atm_user_has_certificates($conn, $userId)
{
    if (!atm_table_has_column($conn, 'sm_form_data', 'user_id')) {
        $scopeSql = atm_form_data_certificate_scope_sql($conn, $userId);
        $result = @$conn->query("SELECT COUNT(*) AS total FROM sm_form_data WHERE {$scopeSql}");
        $row = $result ? $result->fetch_assoc() : null;
        return ((int) ($row['total'] ?? 0)) > 0;
    }

    $scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
    $result = @$conn->query("SELECT COUNT(*) AS total FROM sm_form_data WHERE {$scopeSql}");
    $row = $result ? $result->fetch_assoc() : null;
    return ((int) ($row['total'] ?? 0)) > 0;
}

function atm_branch_owner_user_id($conn, $userId)
{
    $ids = user_branch_user_ids($conn, $userId);
    return $ids ? (int) min($ids) : (int) $userId;
}

function atm_numbering_settings($conn, $userId)
{
    $userId = atm_branch_owner_user_id($conn, $userId);
    $settings = atm_numbering_settings_defaults();
    $settings['locked'] = atm_user_has_certificates($conn, $userId);
    if (!atm_ensure_numbering_settings_table($conn)) {
        return $settings;
    }
    $stmt = $conn->prepare('SELECT start_number, report_prefix FROM sm_certificate_number_settings WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return $settings;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $settings['start_number'] = max(1, (int) ($row['start_number'] ?? 1));
        $settings['report_prefix'] = atm_normalize_report_prefix($row['report_prefix'] ?? 'R');
    }
    return $settings;
}

function atm_save_numbering_settings($conn, $userId, $startNumber, $reportPrefix)
{
    $userId = atm_branch_owner_user_id($conn, $userId);
    if (!atm_ensure_numbering_settings_table($conn)) {
        return [false, 'Unable to prepare certificate numbering settings table.'];
    }
    $current = atm_numbering_settings($conn, $userId);
    $startNumber = max(1, (int) $startNumber);
    $reportPrefix = atm_normalize_report_prefix($reportPrefix);
    if (!empty($current['locked'])) {
        $startNumber = (int) $current['start_number'];
    }
    $stmt = $conn->prepare('INSERT INTO sm_certificate_number_settings (user_id, start_number, report_prefix, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE start_number = VALUES(start_number), report_prefix = VALUES(report_prefix), updated_at = NOW()');
    if (!$stmt) {
        return [false, 'Unable to save certificate numbering settings.'];
    }
    $stmt->bind_param('iis', $userId, $startNumber, $reportPrefix);
    $ok = $stmt->execute();
    $stmt->close();
    return [$ok, $ok ? '' : 'Unable to save certificate numbering settings.'];
}

function atm_next_certificate_number($conn, $userId)
{
    $settings = atm_numbering_settings($conn, $userId);
    $last = atm_last_certificate_number($conn, $userId);
    $next = $last > 0 ? ($last + 1) : max(1, (int) $settings['start_number']);
    return [
        'certi_no' => $next,
        'report_no' => atm_normalize_report_prefix($settings['report_prefix']) . $next,
        'settings' => $settings,
    ];
}

function atm_layout_file($type, $kind = 'positions')
{
    $type = atm_report_type($type);
    if (preg_match('/^CS([0-9]+)$/', $type, $match)) {
        $suffix = 'colour-stone-type-' . (int) $match[1];
        return atm_user_file($kind === 'settings' ? 'settings-' . $suffix . '.json' : 'positions-' . $suffix . '.json');
    }
    if (preg_match('/^PR([0-9]+)$/', $type, $match)) {
        $suffix = 'pearl-type-' . (int) $match[1];
        return atm_user_file($kind === 'settings' ? 'settings-' . $suffix . '.json' : 'positions-' . $suffix . '.json');
    }
    if (preg_match('/^JT([0-9]+)$/', $type, $match)) {
        $suffix = 'jewellery-type-' . (int) $match[1];
        return atm_user_file($kind === 'settings' ? 'settings-' . $suffix . '.json' : 'positions-' . $suffix . '.json');
    }
    if (preg_match('/^DS([0-9]+)$/', $type, $match)) {
        $suffix = 'diamond-screening-type-' . (int) $match[1];
        return atm_user_file($kind === 'settings' ? 'settings-' . $suffix . '.json' : 'positions-' . $suffix . '.json');
    }
    if (preg_match('/^DG([0-9]+)$/', $type, $match)) {
        $suffix = 'diamond-grading-type-' . (int) $match[1];
        return atm_user_file($kind === 'settings' ? 'settings-' . $suffix . '.json' : 'positions-' . $suffix . '.json');
    }
    if ($type === 'S') {
        return atm_user_file($kind === 'settings' ? 'settings.json' : 'positions.json');
    }
    if ($type === 'D') {
        return atm_user_file($kind === 'settings' ? 'settings-diamond.json' : 'positions-diamond.json');
    }
    if ($type === 'J') {
        return atm_user_file($kind === 'settings' ? 'settings-jewellery.json' : 'positions-jewellery.json');
    }
    if ($type === 'P') {
        return atm_user_file($kind === 'settings' ? 'settings-pearl.json' : 'positions-pearl.json');
    }
    return atm_user_file($kind === 'settings' ? 'settings-rudraksha.json' : 'positions-rudraksha.json');
}

function atm_diamond_field_keys()
{
    return ['diamondWeight', 'clarity', 'finish', 'cutGrade', 'tableValue', 'crown', 'girdle', 'pavilionDepth', 'tableDepth', 'fluorescence'];
}

function atm_rudraksha_field_keys()
{
    return ['description', 'face', 'specificComments', 'testCarriedOut', 'rudrakshaRemarks'];
}

function atm_jewellery_field_keys()
{
    return ['description', 'weight', 'goldPurity', 'diamondWeight', 'shapeCut', 'colour', 'clarity', 'finish', 'stoneName', 'remarks', 'issuedTo', 'stoneWeight1', 'stoneWeight2', 'stoneWeight3', 'stoneWeight4', 'stoneWeight5', 'stoneWeight6', 'stoneWeight7', 'cr1', 'cr2', 'cr3', 'cr4', 'cr5', 'cr6', 'cr7', 'cs1', 'cs2', 'cs3', 'cs4', 'cs5', 'cs6', 'cs7'];
}

function atm_diamond_screening_field_keys()
{
    return ['reportNo', 'date', 'shapeCut', 'weight', 'face', 'naturalDiamondWeight', 'syntheticDiamondWeight', 'referenceDiamondWeight', 'nonDiamondWeight', 'naturalDiamondPcs', 'syntheticDiamondPcs', 'referenceDiamondPcs', 'nonDiamondPcs'];
}

function atm_common_field_keys()
{
    return ['reportNo', 'date', 'stoneName', 'shapeCut', 'dimension', 'origin', 'remarks', 'issuedTo'];
}

function atm_database_field_keys($type = 'S')
{
    $definitions = atm_field_definitions($type);
    $columns = null;
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $result = @$GLOBALS['conn']->query('SHOW COLUMNS FROM `sm_form_data`');
        if ($result) {
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[(string) $row['Field']] = true;
            }
            $result->free();
        }
    }

    $keys = [];
    foreach ($definitions as $key => $definition) {
        $column = (string) ($definition['column'] ?? '');
        if ($column === '') {
            continue;
        }
        if ($columns === null || isset($columns[$column])) {
            $keys[] = $key;
        }
    }
    return $keys;
}

function atm_builder_field_keys($type = 'S')
{
    return atm_database_field_keys(atm_report_type($type));
}

function atm_default_positions($type = 'S')
{
    $type = atm_report_type($type);
    $baseType = atm_base_report_type($type);
    $fields = [];
    $x = 7;
    $y = 46;
    $w = 178;
    $h = 10;
    $diamondKeys = atm_diamond_field_keys();
    $diamondY = 46;
    $builderKeys = array_flip(atm_builder_field_keys($type));
    foreach (atm_field_definitions($type) as $key => $definition) {
        if (!isset($builderKeys[$key])) {
            continue;
        }
        $fieldY = in_array($key, $diamondKeys, true) ? $diamondY : $y;
        $defaultDisplay = isset(atm_default_fields()[$key]) ? atm_default_fields()[$key] : 'none';
        if ($baseType === 'S' && in_array($key, ['speciesGroup', 'speciesMode'], true)) {
            $defaultDisplay = 'none';
        }
        $isTickField = isset($definition['valueType']) && $definition['valueType'] === 'tick';
        $fields[$key] = [
            'label' => $definition['label'],
            'column' => $definition['column'],
            'valueType' => $definition['valueType'] ?? '',
            'display' => $defaultDisplay,
            'showLabel' => $isTickField ? 'none' : 'block',
            'showColon' => 'block',
            'labelFontWeight' => 'normal',
            'fontWeight' => 'normal',
            'fontSize' => null,
            'fontColor' => '',
            'labelFontColor' => '',
            'valueFontColor' => '',
            'labelWidth' => null,
            'labelAlign' => 'left',
            'valueAlign' => 'left',
            'x' => $x,
            'y' => $fieldY,
            'w' => $isTickField ? 14 : $w,
            'h' => $isTickField ? 8 : $h,
        ];
        if (in_array($key, $diamondKeys, true)) {
            $diamondY += 11;
        } else {
            $y += 11;
        }
    }

    if ($baseType === 'D') {
        $layoutY = 40;
        $visibleKeys = array_values(array_unique(array_merge(atm_common_field_keys(), $diamondKeys)));
        foreach ($visibleKeys as $key) {
            if (!isset($fields[$key])) continue;
            $fields[$key]['y'] = $layoutY;
            $fields[$key]['h'] = 8;
            $layoutY += 9;
        }
    }
    if ($baseType === 'J') {
        $layoutY = 40;
        foreach (atm_builder_field_keys($type) as $key) {
            if (!isset($fields[$key])) continue;
            $fields[$key]['y'] = $layoutY;
            $fields[$key]['h'] = in_array($key, ['description', 'remarks'], true) ? 14 : 8;
            $layoutY += in_array($key, ['description', 'remarks'], true) ? 15 : 9;
        }
    }
    if ($baseType === 'R') {
        $layoutY = 40;
        foreach (array_merge(['reportNo', 'date', 'description', 'weight', 'shapeCut', 'dimension', 'colour', 'face', 'specificComments', 'testCarriedOut', 'rudrakshaRemarks', 'issuedTo']) as $key) {
            if (!isset($fields[$key])) continue;
            $fields[$key]['y'] = $layoutY;
            $fields[$key]['h'] = 8;
            $layoutY += 9;
        }
    }

    return [
        'table' => ['top' => 54, 'left' => 1, 'width' => 179, 'height' => 114, 'display' => 'block', 'fontSize' => 6, 'rowSpacing' => 2, 'fontFamily' => 'Arial', 'fontColor' => '#000000', 'labelWidth' => 75],
        'fields' => $fields,
        'gemstone' => ['top' => 100, 'left' => 215, 'width' => 39, 'height' => 39, 'display' => 'block'],
        'qrcode' => ['top' => 104, 'left' => 274, 'width' => 31, 'height' => 31, 'display' => 'block'],
        'additionalImages' => [],
    ];
}

function atm_read_positions($type = 'S')
{
    $type = atm_report_type($type);
    $defaults = atm_default_positions($type);
    $saved = atm_read_json(atm_layout_file($type), []);
    if (!is_array($saved)) {
        return $defaults;
    }

    $positions = array_replace_recursive($defaults, $saved);
    $defaultFieldSettings = [];
    foreach ($defaults['fields'] as $key => $field) {
        $defaultFieldSettings[$key] = $field['display'];
    }
    $fieldSettings = atm_read_json(atm_layout_file($type, 'settings'), $defaultFieldSettings);

    foreach (atm_field_definitions($type) as $key => $definition) {
        if (!isset($defaults['fields'][$key])) {
            unset($positions['fields'][$key]);
            continue;
        }
        $savedField = isset($saved['fields'][$key]) && is_array($saved['fields'][$key]) ? $saved['fields'][$key] : [];
        $positions['fields'][$key] = array_replace($defaults['fields'][$key], array_intersect_key($savedField, [
            'label' => true,
            'valueType' => true,
            'display' => true,
            'showLabel' => true,
            'showColon' => true,
            'labelFontWeight' => true,
            'fontWeight' => true,
            'fontSize' => true,
            'fontColor' => true,
            'labelFontColor' => true,
            'valueFontColor' => true,
            'labelWidth' => true,
            'labelAlign' => true,
            'valueAlign' => true,
            'x' => true,
            'y' => true,
            'w' => true,
            'h' => true,
        ]));
        $positions['fields'][$key]['label'] = trim((string) ($positions['fields'][$key]['label'] ?? '')) !== ''
            ? (string) $positions['fields'][$key]['label']
            : $definition['label'];
        $positions['fields'][$key]['column'] = $definition['column'];
        $positions['fields'][$key]['valueType'] = $definition['valueType'] ?? '';
        if (atm_base_report_type($type) === 'S' && in_array($key, ['speciesGroup', 'speciesMode'], true)) {
            $positions['fields'][$key]['display'] = 'none';
        }
        if ($type === 'J' && $key === 'reportNo' && in_array(trim((string) $positions['fields'][$key]['label']), ['Certificate No', 'Certificate Number'], true)) {
            $positions['fields'][$key]['label'] = 'Report No';
        }
        if (!isset($saved['fields'][$key]['display']) && isset($fieldSettings[$key])) {
            $positions['fields'][$key]['display'] = atm_display_value($fieldSettings[$key]);
        }
    }

    if (!isset($positions['table']['labelWidth'])) {
        $positions['table']['labelWidth'] = $defaults['table']['labelWidth'];
    }
    $positions['additionalImages'] = atm_normalize_additional_images($positions['additionalImages'] ?? [], 321, 204, 40, 40);

    return $positions;
}

function atm_default_print_settings()
{
    return [
        'includeBack' => false,
        'backAlignment' => 'same',
        'frontImage' => '',
        'backImage' => '',
    ];
}

function atm_default_qr_settings()
{
    return [
        'urlPattern' => 'https://rtrlu.com/index.php?certi-no={certi_no}',
    ];
}

function atm_read_qr_settings($conn)
{
    $defaults = atm_default_qr_settings();
    if (!$conn) {
        return $defaults;
    }

    $userId = auth_current_user_id();
    $stmt = @$conn->prepare('SELECT url_pattern FROM sm_qr_settings WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return $defaults;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || trim((string) $row['url_pattern']) === '') {
        return $defaults;
    }

    return ['urlPattern' => trim((string) $row['url_pattern'])];
}

function atm_save_qr_settings($conn, $urlPattern)
{
    if (!$conn) {
        return false;
    }

    $userId = auth_current_user_id();
    $urlPattern = trim((string) $urlPattern);
    if ($urlPattern === '') {
        $urlPattern = atm_default_qr_settings()['urlPattern'];
    }

    $stmt = $conn->prepare('INSERT INTO sm_qr_settings (user_id, url_pattern) VALUES (?, ?) ON DUPLICATE KEY UPDATE url_pattern = VALUES(url_pattern), updated_at = CURRENT_TIMESTAMP');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $userId, $urlPattern);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function atm_build_qr_url($urlPattern, $record)
{
    $pattern = trim((string) $urlPattern);
    if ($pattern === '') {
        $pattern = atm_default_qr_settings()['urlPattern'];
    }

    $certiNo = isset($record['certi_no']) ? (string) $record['certi_no'] : '';
    $reportNo = isset($record['report_no']) && trim((string) $record['report_no']) !== ''
        ? (string) $record['report_no']
        : ('R' . $certiNo);

    return str_replace(
        ['{certi_no}', '{certificate_no}', '{report_no}'],
        [rawurlencode($certiNo), rawurlencode($certiNo), rawurlencode($reportNo)],
        $pattern
    );
}

function atm_clamp($value, $minimum, $maximum)
{
    return max($minimum, min($maximum, (float) $value));
}
