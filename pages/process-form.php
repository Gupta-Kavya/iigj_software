<?php
require_once 'auth.php';
auth_require_login();
include("db_connect.php");
require_once 'atm_config.php';

header('Content-Type: application/json');

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
$report_type = in_array($report_type, ['D', 'J', 'R'], true) ? $report_type : 'S';
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

  $imageAttached = false;
  $newImage = atm_user_stone_dir() . '/' . $certi_no . '.jpg';
  if ($upload_token !== '') {
    $pendingImage = atm_user_stone_dir() . '/_pending/' . $upload_token . '.jpg';
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
    'message' => 'New record created successfully',
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
