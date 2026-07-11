<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'user_branch_helper.php';
require_once 'atm_config.php';
require_once 'agreement_helper.php';

header('Content-Type: application/json');

function cstone_post($key, $default = '')
{
    return trim((string) ($_POST[$key] ?? $default));
}

function cstone_weight_with_unit($weightKey, $unitKey)
{
    $weight = cstone_post($weightKey);
    if ($weight === '') {
        return '';
    }
    $unit = strtolower(cstone_post($unitKey, 'ct'));
    $allowedUnits = ['ct', 'gms', 'kg', 'pcs'];
    if (!in_array($unit, $allowedUnits, true)) {
        $unit = 'ct';
    }
    return preg_match('/\b(ct|gms|kg|pcs)\b/i', $weight) ? $weight : trim($weight . ' ' . $unit);
}

function cstone_int($key, $default = 0)
{
    $value = preg_replace('/[^0-9-]/', '', (string) ($_POST[$key] ?? ''));
    return $value === '' ? (int) $default : (int) $value;
}

function cstone_columns($conn)
{
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM `sm_form_data`');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }
    return $columns;
}

function cstone_next_id($conn)
{
    $result = $conn->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM sm_form_data');
    $row = $result ? $result->fetch_assoc() : null;
    return max(1, (int) ($row['next_id'] ?? 1));
}

function cstone_next_number($conn)
{
    $result = $conn->query('SELECT COALESCE(MAX(certi_no), 0) + 1 AS next_certi_no FROM sm_form_data');
    $row = $result ? $result->fetch_assoc() : null;
    $certiNo = max(1, (int) ($row['next_certi_no'] ?? 1));
    return [
        'certi_no' => $certiNo,
        'report_no' => 'R' . $certiNo,
    ];
}

function cstone_insert_dynamic($conn, array $columns, array $values)
{
    $insert = [];
    foreach ($values as $column => $value) {
        if (isset($columns[$column])) {
            $insert[$column] = $value;
        }
    }
    if (!$insert) {
        throw new Exception('No matching sm_form_data columns found.');
    }

    $columnSql = implode(', ', array_map(function ($column) {
        return '`' . str_replace('`', '', $column) . '`';
    }, array_keys($insert)));
    $placeholders = implode(', ', array_fill(0, count($insert), '?'));
    $types = '';
    foreach ($insert as $value) {
        $types .= is_int($value) ? 'i' : 's';
    }

    $stmt = $conn->prepare("INSERT INTO `sm_form_data` ({$columnSql}) VALUES ({$placeholders})");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $bindValues = array_values($insert);
    $refs = [];
    $refs[] = $types;
    foreach ($bindValues as $index => $value) {
        $refs[] = &$bindValues[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();
}

function cstone_update_dynamic($conn, array $columns, array $values, $id)
{
    $update = [];
    foreach ($values as $column => $value) {
        if ($column !== 'id' && isset($columns[$column])) {
            $update[$column] = $value;
        }
    }
    if (!$update) {
        throw new Exception('No matching sm_form_data columns found.');
    }

    $setSql = implode(', ', array_map(function ($column) {
        return '`' . str_replace('`', '', $column) . '` = ?';
    }, array_keys($update)));
    $types = '';
    foreach ($update as $value) {
        $types .= is_int($value) ? 'i' : 's';
    }
    $types .= 'i';

    $stmt = $conn->prepare("UPDATE `sm_form_data` SET {$setSql} WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $bindValues = array_values($update);
    $bindValues[] = (int) $id;
    $refs = [];
    $refs[] = $types;
    foreach ($bindValues as $index => $value) {
        $refs[] = &$bindValues[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();
}

$userId = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
$columns = cstone_columns($conn);
form_master_table_ready($conn);
cstone_report_type_master_ready($conn);
$baseType = strtoupper(cstone_post('report_type', 'S'));
$baseType = in_array($baseType, ['S', 'P'], true) ? $baseType : 'S';
$typeLabel = $baseType === 'P' ? 'Pearl' : 'Colour stone';
$lockName = 'sm_form_data_cstone_save_' . $baseType;
$lockStmt = $conn->prepare('SELECT GET_LOCK(?, 10) AS lock_status');
$lockStmt->bind_param('s', $lockName);
$lockStmt->execute();
$lockResult = $lockStmt->get_result()->fetch_assoc();
$lockStmt->close();

if (!$lockResult || (int) $lockResult['lock_status'] !== 1) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Another report is being saved. Please try again in a few seconds.']);
    exit;
}

try {
    $agreementNo = cstone_int('agreement_no');
    $certiNo = cstone_int('certificate_no');
    if ($certiNo <= 0) {
        $certiNo = cstone_int('certi_no');
    }
    if ($agreementNo <= 0 || $certiNo <= 0) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Enter a booked agreement no and certificate no.']);
        exit;
    }
    $editExisting = cstone_post('edit_existing_report') === '1';
    $editExistingId = cstone_int('edit_existing_report_id');

    $booking = agreement_ensure_form_master_for_certificate($conn, $userId, $agreementNo, $certiNo);
    if (!$booking) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'This certificate is not booked in the selected agreement.']);
        exit;
    }
    if (strtolower(trim((string) ($booking['status'] ?? ''))) === 'cancelled') {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'This agreement row is cancelled and cannot be used for feeding.']);
        exit;
    }

    if (isset($columns['user_id'])) {
        $existsStmt = $conn->prepare("SELECT id, ag_no FROM sm_form_data WHERE {$scopeSql} AND ag_no = ? AND certi_no = ? AND `type` = ? LIMIT 1");
        $existsStmt->bind_param('iis', $agreementNo, $certiNo, $baseType);
    } else {
        $existsStmt = $conn->prepare("SELECT id, ag_no FROM sm_form_data WHERE ag_no = ? AND certi_no = ? AND `type` = ? LIMIT 1");
        $existsStmt->bind_param('iis', $agreementNo, $certiNo, $baseType);
    }
    $existsStmt->execute();
    $existingReport = $existsStmt->get_result()->fetch_assoc();
    $existsStmt->close();
    if ($existingReport && (!$editExisting || (int) $existingReport['id'] !== $editExistingId)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Certificate no ' . $certiNo . ' is already generated.']);
        exit;
    }
    if (!$existingReport && $editExisting) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'This saved certificate could not be found for editing. Please reload the booked certificate.']);
        exit;
    }
    $reportNo = (string) ($booking['report_no'] ?: $booking['ref_no']);
    $selectedReportTypeId = max(0, (int) ($_POST['report_type_id'] ?? 0));
    $selectedReportTypeName = cstone_post('report_type_text', $baseType === 'P' ? 'PEARL' : 'COLOUR STONE');
    $selectedReportFormat = strtolower(cstone_post('report_format', 'a4'));
    $selectedReportFormat = in_array($selectedReportFormat, ['a4', 'atm', 'postcard'], true) ? $selectedReportFormat : 'a4';
    if ($selectedReportTypeId > 0) {
        $typeStmt = $conn->prepare("SELECT report_name, report_format FROM sm_colour_stone_report_types WHERE id = ? AND base_type = ? AND ({$scopeSql} OR user_id = 1) AND active = 1 LIMIT 1");
        if ($typeStmt) {
            $typeStmt->bind_param('is', $selectedReportTypeId, $baseType);
            $typeStmt->execute();
            $typeRow = $typeStmt->get_result()->fetch_assoc();
            $typeStmt->close();
            if ($typeRow) {
                $selectedReportTypeName = (string) $typeRow['report_name'];
                $selectedReportFormat = in_array(($typeRow['report_format'] ?? 'a4'), ['a4', 'atm', 'postcard'], true) ? (string) $typeRow['report_format'] : 'a4';
            } else {
                $selectedReportTypeId = 0;
            }
        }
    }

    user_branch_location_ready($conn);
    $location = user_branch_location_for_user($conn, $userId);
    if ($location === '') {
        $location = 'SITAPURA';
    }

    $tests = [];
    foreach ((array) ($_POST['tests'] ?? []) as $test) {
        $tests[] = trim((string) $test);
    }
    $testText = cstone_post('test_carried_out');
    if ($testText === '' && $tests) {
        $testText = implode(', ', $tests);
    }

    $stoneWeight1 = cstone_weight_with_unit('stone_weight_1', 'stone_weight_unit_1');
    $stoneWeightUnit = cstone_post('stone_weight_unit_1') ?: cstone_post('stone_weight_unit', 'ct');
    $speciesMode = cstone_post('species_mode', 'Species/Variety');
    $varietyValue = cstone_post('stone_name');
    if (strcasecmp($speciesMode, 'Others') === 0 || strcasecmp($varietyValue, 'Others') === 0) {
        $speciesMode = 'Others';
        $speciesGroupValue = '';
    } else {
        $speciesGroupValue = cstone_post('species_grp');
    }
    $values = [
        'id' => cstone_next_id($conn),
        'user_id' => $userId,
        'ag_no' => $agreementNo,
        'report_no' => $reportNo,
        'certi_no' => $certiNo,
        'date' => cstone_post('date'),
        'stone_wt1' => $stoneWeight1,
        'stone_wt2' => cstone_weight_with_unit('stone_weight_2', 'stone_weight_unit_2'),
        'stone_wt3' => cstone_weight_with_unit('stone_weight_3', 'stone_weight_unit_3'),
        'stone_wt4' => cstone_weight_with_unit('stone_weight_4', 'stone_weight_unit_4'),
        'stone_wt5' => cstone_weight_with_unit('stone_weight_5', 'stone_weight_unit_5'),
        'stone_wt6' => '',
        'stone_wt7' => '',
        'stone_wt8' => '',
        'bead_lenth' => cstone_post('length_tested'),
        'gross_wt' => cstone_post('gross_weight'),
        'pcs' => cstone_int('stone_pcs'),
        'dime1' => cstone_post('measurement_1'),
        'dime2' => cstone_post('measurement_2'),
        'dime3' => cstone_post('measurement_3'),
        'dime4' => cstone_post('measurement_4'),
        'dime5' => cstone_post('measurement_5'),
        'shape_cut' => cstone_post('shape_cut'),
        'trtcoment1' => cstone_post('treatment_comment_desc'),
        'trtcoment2' => cstone_post('treatment_comment_desc_2'),
        'ri' => cstone_post('ri'),
        'sg' => cstone_post('specefic_grav'),
        'sign1' => '',
        'sign2' => '',
        'unit_grs' => cstone_post('gross_unit', 'ct'),
        'variety' => $varietyValue,
        'tpremark' => cstone_post('tested_pcs_remark'),
        'unit_stn' => $stoneWeightUnit,
        'title_rem' => $speciesMode,
        'optic' => cstone_post('optic_char'),
        'desc1' => cstone_post('item_desc'),
        'finish1' => '',
        'clarity1' => '',
        'diapcs' => 0,
        'diapcs3' => 0,
        'type' => $baseType,
        'format' => 0,
        'tot_stone' => cstone_post('stone_pcs'),
        'tri' => in_array('RI', $tests, true) ? 1 : 0,
        'tsg' => in_array('SG', $tests, true) ? 1 : 0,
        'tmag' => in_array('MAGNIFICATION', $tests, true) ? 1 : 0,
        'tuvf' => in_array('UV FLUORESCENCE', $tests, true) ? 1 : 0,
        'tabs' => in_array('ABS SPECTRUM', $tests, true) ? 1 : 0,
        'tirs' => in_array('IR SPECTRUM', $tests, true) ? 1 : 0,
        'tedxrf' => in_array('EDXRF', $tests, true) ? 1 : 0,
        'tlrs' => in_array('LRS', $tests, true) ? 1 : 0,
        'tuvnir' => in_array('UV-VIS-NIR', $tests, true) ? 1 : 0,
        'tlaicpms' => in_array('LA-ICPMS', $tests, true) ? 1 : 0,
        'txray' => in_array('X-RADIOGRAPHY', $tests, true) ? 1 : 0,
        'tuvimg' => in_array('UV-IMAGING', $tests, true) ? 1 : 0,
        'reptype' => 0,
        'category' => $selectedReportTypeName,
        'comment' => cstone_post('comments'),
        'title_rem1' => cstone_post('treatment_comment_title'),
        'title_rem2' => cstone_post('treatment_comment_title_2'),
        'prefix1' => '',
        'prefix2' => '',
        'stone_name' => $speciesGroupValue,
        'report_typ' => $selectedReportTypeId,
        'color' => cstone_post('colour'),
        'testd_pcs' => cstone_int('stone_pcs'),
        'gold_purit' => '',
        'clarity' => '',
        'cutgrade' => '',
        'productno' => cstone_int('ebay_prod_no'),
        'origin' => cstone_post('origin'),
        'pavi_depth' => '0.00',
        'ad_pavi' => '0.00',
        'ad_length' => '0.00',
        'ad_lwr_hf' => '0.00',
        'additional' => '0.00',
        'ref_dia_wt' => '',
        'non_dia_wt' => '',
        'nat_dia_pc' => 0,
        'syn_dia_pc' => 0,
        'ref_dia_pc' => 0,
        'non_dia_pc' => 0,
        'repsize' => '',
        'WS1' => '',
        'WS2' => '',
        'WS3' => '',
        'WS4' => '',
        'WS5' => '',
        'WS6' => '',
        'WS7' => '',
        'location' => $location,

        'stone_wt' => $stoneWeight1,
        'dimension' => cstone_post('measurement_1'),
        'spe_gravit' => cstone_post('specefic_grav'),
        'ref_index' => cstone_post('ri'),
        'magni' => cstone_post('magnification'),
        'spe_group' => $speciesGroupValue,
        'issued_to' => '',
        'hardness' => cstone_post('hardness'),
        'desc' => cstone_post('item_desc'),
        'rem1' => cstone_post('tested_pcs_remark'),
        'rem2' => $testText,
    ];

    if ($existingReport) {
        if (isset($columns['updated_at'])) {
            $values['updated_at'] = date('Y-m-d H:i:s');
        }
        cstone_update_dynamic($conn, $columns, $values, (int) $existingReport['id']);
    } else {
        cstone_insert_dynamic($conn, $columns, $values);
    }

    $statusStmt = $conn->prepare("UPDATE sm_form_masters SET status = 'generated', updated_at = NOW() WHERE {$scopeSql} AND agreement_no = ? AND certi_no = ?");
    if ($statusStmt) {
        $statusStmt->bind_param('ii', $agreementNo, $certiNo);
        $statusStmt->execute();
        $statusStmt->close();
    }

    $uploadToken = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['upload_token'] ?? ''));
    $uploadToken = $uploadToken !== '' ? substr($uploadToken, 0, 80) : '';
    $imageAttached = false;
    $newImage = atm_user_stone_dir() . '/' . $certiNo . '.jpg';
    if ($uploadToken !== '') {
        $pendingImage = atm_user_stone_dir() . '/_pending/' . $uploadToken . '.jpg';
        if (is_file($pendingImage)) {
            if (is_file($newImage)) {
                @unlink($newImage);
            }
            $imageAttached = @rename($pendingImage, $newImage);
            if (!$imageAttached && @copy($pendingImage, $newImage)) {
                @unlink($pendingImage);
                $imageAttached = true;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => $existingReport ? $typeLabel . ' report updated.' : $typeLabel . ' report saved.',
        'action' => $existingReport ? 'updated' : 'created',
        'certi_no' => $certiNo,
        'report_no' => $reportNo,
        'report_format' => $selectedReportFormat,
        'report_type_id' => $selectedReportTypeId,
        'image_attached' => $imageAttached,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save ' . strtolower($typeLabel) . ' report: ' . $e->getMessage()]);
} finally {
    $unlockStmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    $unlockStmt->bind_param('s', $lockName);
    $unlockStmt->execute();
    $unlockStmt->close();
    $conn->close();
}
