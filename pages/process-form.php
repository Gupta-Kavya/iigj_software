<?php
require_once 'auth.php';
auth_require_login();
include("db_connect.php");
require_once 'atm_config.php';
require_once 'agreement_helper.php';

header('Content-Type: application/json');
agreement_form_data_type_ready($conn);

function process_form_columns($conn)
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

function process_form_insert_dynamic($conn, array $columns, array $values)
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
  $refs = [$types];
  foreach ($bindValues as $index => $value) {
    $refs[] = &$bindValues[$index];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
  if (!$stmt->execute()) {
    throw new Exception($stmt->error);
  }
  $stmt->close();
}

function process_form_update_dynamic($conn, array $columns, array $values, $id)
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
  $refs = [$types];
  foreach ($bindValues as $index => $value) {
    $refs[] = &$bindValues[$index];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
  if (!$stmt->execute()) {
    throw new Exception($stmt->error);
  }
  $stmt->close();
}

function process_form_post($key, $default = '')
{
  return trim((string) ($_POST[$key] ?? $default));
}

function process_form_int($key, $default = 0)
{
  $value = preg_replace('/[^0-9-]/', '', (string) ($_POST[$key] ?? ''));
  return $value === '' ? (int) $default : (int) $value;
}

function process_form_symbols()
{
  $symbols = [];
  $json = trim((string) ($_POST['diamond_symbols_json'] ?? ''));
  if ($json !== '') {
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
      foreach ($decoded as $symbol) {
        $symbol = trim((string) $symbol);
        if ($symbol !== '' && !in_array($symbol, $symbols, true)) {
          $symbols[] = substr($symbol, 0, 120);
        }
        if (count($symbols) >= 3) break;
      }
    }
  }
  if (!$symbols) {
    for ($i = 1; $i <= 3; $i++) {
      $symbol = process_form_post('symbol' . $i);
      if ($symbol !== '' && !in_array($symbol, $symbols, true)) {
        $symbols[] = substr($symbol, 0, 120);
      }
      if (count($symbols) >= 3) break;
    }
  }
  return $symbols;
}

$date = $_POST['date'] ?? '';
$weight = $_POST['weight'] ?? '';
$shape_cut = $_POST['shape_cut'] ?? '';
$dimension = $_POST['dimension'] ?? '';
$colour = $_POST['colour'] ?? '';
$optic_char = $_POST['optic_char'] ?? '';
$hardness = $_POST['hardness'] ?? '';
$specefic_grav = $_POST['specefic_grav'] ?? '';
$ri = $_POST['ri'] ?? '';
$magnification = $_POST['magnification'] ?? '';
$species_grp = $_POST['species_grp'] ?? '';
$stone_name = $_POST['stone_name'] ?? '';
$origin = $_POST['origin'] ?? '';
$comments = $_POST['comments'] ?? '';
$issued_to = $_POST['issued_to'] ?? '';
$report_type = $_POST['report_type'] ?? 'S';
$report_type = in_array($report_type, ['D', 'J', 'DS', 'R'], true) ? $report_type : 'S';
$description = $_POST['description'] ?? '';
$faces = $_POST['faces'] ?? '';
$stone_pcs = $_POST['stone_pcs'] ?? '';
$test_carried_out = $_POST['test_carried_out'] ?? '';
$remarks = $_POST['remarks'] ?? '';
$dia_wt = $_POST['dia_wt'] ?? $_POST['dia_weight'] ?? '';
$clarity = $_POST['clarity'] ?? '';
$finish = $_POST['finish'] ?? '';
$cut = $_POST['cut'] ?? '';
$tableValue = $_POST['table_value'] ?? $_POST['table'] ?? '';
$crown = $_POST['crown'] ?? $_POST['croen_height'] ?? '';
$girdle = $_POST['girdle'] ?? '';
$pav_depth = $_POST['pav_depth'] ?? '';
$tab_depth = $_POST['tab_depth'] ?? $_POST['table_depth'] ?? '';
$flurance = $_POST['flurance'] ?? $_POST['florosence'] ?? '';
if ($report_type === 'D' && $weight === '') {
  $weight = $dia_wt;
}
if ($report_type === 'D' && $stone_name === '') {
  $stone_name = 'Diamond';
}
if ($report_type === 'R' && $stone_name === '') {
  $stone_name = 'Rudraksha';
}
$upload_token = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['upload_token'] ?? ''));
$upload_token = $upload_token !== '' ? substr($upload_token, 0, 80) : '';
$user_id = auth_current_user_id();

$lockName = 'sm_form_data_certi_no_user_' . $user_id;
$lockStmt = $conn->prepare('SELECT GET_LOCK(?, 10) AS lock_status');
$lockStmt->bind_param('s', $lockName);
$lockStmt->execute();
$lockResult = $lockStmt->get_result()->fetch_assoc();
$lockStmt->close();

if (!$lockResult || (int) $lockResult['lock_status'] !== 1) {
  http_response_code(409);
  echo json_encode([
    'status' => 'error',
    'message' => 'Another entry is being saved for this account. Please try again in a few seconds.'
  ]);
  exit;
}

try {
  $agreementNo = process_form_int('agreement_no');
  $requestedCertiNo = process_form_int('certificate_no');
  if ($requestedCertiNo <= 0) {
    $requestedCertiNo = process_form_int('certi_no');
  }

  if (in_array($report_type, ['D', 'J', 'DS'], true)) {
    $baseType = $report_type;
    $typeLabel = $baseType === 'DS' ? 'diamond screening' : ($baseType === 'D' ? 'diamond grading' : 'jewellery');
    $columns = process_form_columns($conn);
    $editExisting = process_form_post('edit_existing_report') === '1';
    $editExistingId = process_form_int('edit_existing_report_id');
    $selectedReportTypeId = max(0, process_form_int('report_type_id'));
    $selectedReportTypeName = process_form_post('report_type_text', $baseType === 'DS' ? 'Diamond Screening' : ($baseType === 'D' ? 'Diamond Grading' : 'Diamond Jewellery'));
    $selectedReportFormat = strtolower(process_form_post('report_format', 'a4'));
    $selectedReportFormat = in_array($selectedReportFormat, ['a4', 'atm', 'postcard'], true) ? $selectedReportFormat : 'a4';
    if ($selectedReportTypeId > 0) {
      $scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');
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

    if ($agreementNo <= 0 || $requestedCertiNo <= 0) {
      http_response_code(422);
      echo json_encode(['status' => 'error', 'message' => 'Enter a booked agreement no and certificate no.']);
      exit;
    }

    form_master_table_ready($conn);
    $booking = agreement_ensure_form_master_for_certificate($conn, $user_id, $agreementNo, $requestedCertiNo);
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
    $certi_no = $requestedCertiNo;
    $report_no = (string) ($booking['report_no'] ?: $booking['ref_no']);

    $existingReport = null;
    if (isset($columns['user_id'])) {
      $scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');
      $existsSql = "SELECT id FROM sm_form_data WHERE {$scopeSql} AND ag_no = ? AND certi_no = ? AND `type` = ? LIMIT 1";
      $existsStmt = $conn->prepare($existsSql);
      if ($existsStmt) {
        $existsStmt->bind_param('iis', $agreementNo, $certi_no, $baseType);
        $existsStmt->execute();
        $existingReport = $existsStmt->get_result()->fetch_assoc();
        $existsStmt->close();
      }
    } else {
      $existsStmt = $conn->prepare("SELECT id FROM sm_form_data WHERE ag_no = ? AND certi_no = ? AND `type` = ? LIMIT 1");
      if ($existsStmt) {
        $existsStmt->bind_param('iis', $agreementNo, $certi_no, $baseType);
        $existsStmt->execute();
        $existingReport = $existsStmt->get_result()->fetch_assoc();
        $existsStmt->close();
      }
    }
    if ($existingReport && (!$editExisting || (int) $existingReport['id'] !== $editExistingId)) {
      http_response_code(422);
      echo json_encode(['status' => 'error', 'message' => 'Certificate no ' . $certi_no . ' is already generated.']);
      exit;
    }
    if (!$existingReport && $editExisting) {
      http_response_code(422);
      echo json_encode(['status' => 'error', 'message' => 'This saved ' . $typeLabel . ' certificate could not be found for editing. Please reload it.']);
      exit;
    }

    $values = [
      'user_id' => $user_id,
      'ag_no' => $agreementNo,
      'certi_no' => $certi_no,
      'report_no' => $report_no,
      'date' => $date,
      'stone_wt' => $weight,
      'gross_wt' => $weight,
      'dia_wt' => $dia_wt,
      'shape_cut' => $shape_cut,
      'color' => $colour,
      'clarity' => $clarity,
      'finish' => $finish,
      'stone_name' => $stone_name,
      'comment' => $comments,
      'issued_to' => $issued_to,
      'category' => $selectedReportTypeName,
      'report_typ' => $selectedReportTypeId,
      'repsize' => $selectedReportFormat,
      'type' => $baseType,
      'location' => function_exists('user_branch_location_for_user') ? user_branch_location_for_user($conn, $user_id) : '',
    ];
    if ($baseType === 'D') {
      $values['desc'] = $description;
      $values['desc1'] = $description;
      $values['dia_wt'] = $dia_wt;
      $values['stone_wt'] = $dia_wt;
      $values['diawt1'] = $dia_wt;
      $values['dime1'] = $dimension;
      $values['cut'] = process_form_post('cut');
      $values['cutgrade'] = process_form_post('cut');
      $values['symmetry'] = process_form_post('symmetry');
      $values['table_size'] = process_form_post('table_value');
      $values['table'] = process_form_post('table_value');
      $values['pavi_depth'] = process_form_post('pav_depth');
      $values['flurance'] = process_form_post('flurance');
      $values['flurence'] = process_form_post('flurance');
      $values['culet'] = process_form_post('culet');
      $symbols = process_form_symbols();
      $values['diamond_symbols_json'] = json_encode(array_values($symbols), JSON_UNESCAPED_UNICODE);
      for ($i = 1; $i <= 3; $i++) {
        $values['WS' . $i] = $symbols[$i - 1] ?? '';
      }
      foreach (['tri', 'tsg', 'tmag', 'tuvf', 'tabs', 'tirs', 'tedxrf', 'tlrs', 'tuvnir', 'tlaicpms', 'txray', 'tuvimg'] as $testColumn) {
        $values[$testColumn] = isset($_POST[$testColumn]) ? 1 : 0;
      }
    } elseif ($baseType === 'J') {
      $values['desc'] = $description;
      $values['desc1'] = $description;
      $values['gold_purit'] = process_form_post('metal_type');
      for ($i = 1; $i <= 7; $i++) {
        $values['cr' . $i] = process_form_post('cr' . $i);
        $values['cs' . $i] = process_form_post('cs' . $i);
        $values['stone_wt' . $i] = process_form_post('stone_wt' . $i);
      }
    } else {
      $totalPcs = process_form_int('total_pcs');
      $values['stone_wt'] = process_form_post('total_weight', $weight);
      $values['gross_wt'] = process_form_post('total_weight', $weight);
      $values['dia_wt'] = process_form_post('total_weight', $dia_wt);
      $values['pcs'] = $totalPcs;
      $values['testd_pcs'] = $totalPcs;
      $values['stone_pcs'] = $totalPcs;
      $values['faces'] = (string) $totalPcs;
      $values['nat_dia_wt'] = process_form_post('nat_dia_wt');
      $values['syn_dia_wt'] = process_form_post('syn_dia_wt');
      $values['ref_dia_wt'] = process_form_post('ref_dia_wt');
      $values['non_dia_wt'] = process_form_post('non_dia_wt');
      $values['nat_dia_pc'] = process_form_int('nat_dia_pc');
      $values['syn_dia_pc'] = process_form_int('syn_dia_pc');
      $values['ref_dia_pc'] = process_form_int('ref_dia_pc');
      $values['non_dia_pc'] = process_form_int('non_dia_pc');
    }
    if ($existingReport) {
      if (isset($columns['updated_at'])) {
        $values['updated_at'] = date('Y-m-d H:i:s');
      }
      process_form_update_dynamic($conn, $columns, $values, (int) $existingReport['id']);
    } else {
      process_form_insert_dynamic($conn, $columns, $values);
    }

    if ($agreementNo > 0) {
      $scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');
      $statusStmt = $conn->prepare("UPDATE sm_form_masters SET status = 'generated', updated_at = NOW() WHERE {$scopeSql} AND agreement_no = ? AND certi_no = ?");
      if ($statusStmt) {
        $statusStmt->bind_param('ii', $agreementNo, $certi_no);
        $statusStmt->execute();
        $statusStmt->close();
      }
    }
  } else {
    $numbering = atm_next_certificate_number($conn, $user_id);
    $certi_no = (int) $numbering['certi_no'];
    $report_no = (string) $numbering['report_no'];

    $sql = "INSERT INTO `sm_form_data`
    (`user_id`, `certi_no`, `date`, `shape_cut`, `dimension`, `color`, `optic_char`, `spe_gravit`, `ref_index`, `magni`, `spe_group`, `comment`, `stone_name`, `issued_to`, `stone_wt`, `type`, `origin`, `report_no`, `hardness`,
     `dia_wt`, `clarity`, `finish`, `cut`, `table`, `crown`, `girdle`, `pav_depth`, `tab_depth`, `flurance`,
     `desc`, `faces`, `stone_pcs`, `rem1`, `rem2`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param(
      'iissssssssssssssssssssssssssssssss',
      $user_id,
      $certi_no,
      $date,
      $shape_cut,
      $dimension,
      $colour,
      $optic_char,
      $specefic_grav,
      $ri,
      $magnification,
      $species_grp,
      $comments,
      $stone_name,
      $issued_to,
      $weight,
      $report_type,
      $origin,
      $report_no,
      $hardness,
      $dia_wt,
      $clarity,
      $finish,
      $cut,
      $tableValue,
      $crown,
      $girdle,
      $pav_depth,
      $tab_depth,
      $flurance,
      $description,
      $faces,
      $stone_pcs,
      $remarks,
      $test_carried_out
    );

    if (!$stmt->execute()) {
      throw new Exception($stmt->error);
    }
    $stmt->close();
  }

  $imageAttached = false;
  $imageMoves = [
    ['token' => $upload_token, 'folder' => 'st_images'],
    ['token' => process_form_post('upload_token_proportion'), 'folder' => 'proportion_images'],
    ['token' => process_form_post('upload_token_clarity'), 'folder' => 'clarity_images'],
  ];
  foreach ($imageMoves as $imageMove) {
    $token = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $imageMove['token']);
    if ($token === '') {
      continue;
    }
    $folder = $imageMove['folder'];
    $newImage = atm_user_image_dir($folder) . '/' . $certi_no . '.jpg';
    $pendingImage = atm_user_image_dir($folder) . '/_pending/' . $token . '.jpg';
    if (is_file($pendingImage)) {
      if (is_file($newImage)) {
        @unlink($newImage);
      }
      $moved = @rename($pendingImage, $newImage);
      if (!$moved && @copy($pendingImage, $newImage)) {
        @unlink($pendingImage);
        $moved = true;
      }
      $imageAttached = $imageAttached || $moved;
    }
  }

  echo json_encode([
    'status' => 'success',
    'message' => isset($existingReport) && $existingReport ? 'Record updated successfully' : 'New record created successfully',
    'action' => isset($existingReport) && $existingReport ? 'updated' : 'created',
    'certi_no' => $certi_no,
    'report_no' => $report_no,
    'image_attached' => $imageAttached
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Unable to save record: ' . $e->getMessage()
  ]);
} finally {
  $unlockStmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
  $unlockStmt->bind_param('s', $lockName);
  $unlockStmt->execute();
  $unlockStmt->close();
  $conn->close();
}
