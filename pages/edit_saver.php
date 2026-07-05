<?php
require_once 'auth.php';
auth_require_login();
include("db_connect.php");
require_once 'atm_config.php';

header('Content-Type: application/json');
auth_block_demo_action('Report editing', 'edit-report.php', true);

$certi_no = isset($_POST['certi_no']) ? (int) $_POST['certi_no'] : 0;
$date = $_POST['date'] ?? '';
$weight = $_POST['stone_wt'] ?? '';
$shape_cut = $_POST['shape_cut'] ?? '';
$dimension = $_POST['dimension'] ?? '';
$colour = $_POST['color'] ?? '';
$optic_char = $_POST['optic_char'] ?? '';
$hardness = $_POST['hardness'] ?? '';
$specefic_grav = $_POST['spe_gravit'] ?? '';
$ri = $_POST['ref_index'] ?? '';
$magnification = $_POST['magni'] ?? '';
$species_grp = $_POST['spe_group'] ?? '';
$stone_name = $_POST['stone_name'] ?? '';
$origin = $_POST['origin'] ?? '';
$comments = $_POST['comment'] ?? '';
$issued_to = $_POST['issued_to'] ?? '';
$dia_wt = $_POST['dia_wt'] ?? '';
$clarity = $_POST['clarity'] ?? '';
$finish = $_POST['finish'] ?? '';
$cut = $_POST['cut'] ?? '';
$tableValue = $_POST['table_value'] ?? '';
$crown = $_POST['crown'] ?? '';
$girdle = $_POST['girdle'] ?? '';
$pav_depth = $_POST['pav_depth'] ?? '';
$tab_depth = $_POST['tab_depth'] ?? '';
$flurance = $_POST['flurance'] ?? '';
$description = $_POST['description'] ?? '';
$faces = $_POST['faces'] ?? '';
$stone_pcs = $_POST['stone_pcs'] ?? '';
$remarks = $_POST['remarks'] ?? '';
$test_carried_out = $_POST['test_carried_out'] ?? '';
$user_id = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');

if ($certi_no <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid certificate number.']);
    exit;
}

$sql = "UPDATE sm_form_data
        SET `date` = ?, shape_cut = ?, dimension = ?, color = ?, optic_char = ?, spe_gravit = ?, ref_index = ?, magni = ?, spe_group = ?, `comment` = ?, stone_name = ?, issued_to = ?, stone_wt = ?, origin = ?, hardness = ?,
            dia_wt = ?, clarity = ?, finish = ?, cut = ?, `table` = ?, crown = ?, girdle = ?, pav_depth = ?, tab_depth = ?, flurance = ?,
            `desc` = ?, faces = ?, stone_pcs = ?, rem1 = ?, rem2 = ?
        WHERE {$scopeSql} AND certi_no = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare update: ' . $conn->error]);
    exit;
}
$stmt->bind_param(
    'ssssssssssssssssssssssssssssssi',
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
    $origin,
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
    $test_carried_out,
    $certi_no
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to update report: ' . $stmt->error]);
    exit;
}

if ($stmt->affected_rows < 0) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to update report.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Certificate No: ' . $certi_no . ' updated successfully.'
]);
$stmt->close();
$conn->close();
?>
