<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'master_data_helper.php';

header('Content-Type: application/json');
auth_block_demo_action('Master data changes', 'add-master.php', true);

function master_bind_params($stmt, $types, $params)
{
    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

$user_id = auth_current_user_id();
$stone_name = trim($_POST['stone_name'] ?? '');
$group = trim($_POST['group'] ?? '');
$shape_cut = trim($_POST['shape_cutt'] ?? '');
$colour = trim($_POST['colour'] ?? '');
$ri = trim($_POST['ri'] ?? '');
$magni = trim($_POST['magni'] ?? '');

$table_name = null;
$checkSql = null;
$insertSql = null;
$checkTypes = null;
$checkParams = [];
$insertTypes = null;
$insertParams = [];

if ($stone_name !== '' && $group !== '') {
    $table_name = 'sm_master_stone_name';
    $checkSql = 'SELECT id FROM sm_master_stone_name WHERE ' . master_scope_sql($user_id) . ' AND stone_name = ? AND `group` = ? LIMIT 1';
    $insertSql = 'INSERT INTO sm_master_stone_name (user_id, stone_name, `group`) VALUES (?, ?, ?)';
    $checkTypes = 'ss';
    $checkParams = [$stone_name, $group];
    $insertTypes = 'iss';
    $insertParams = [$user_id, $stone_name, $group];
} elseif ($shape_cut !== '') {
    $table_name = 'sm_master_shape_cut';
    $checkSql = 'SELECT id FROM sm_master_shape_cut WHERE ' . master_scope_sql($user_id) . ' AND shape_cut = ? LIMIT 1';
    $insertSql = 'INSERT INTO sm_master_shape_cut (user_id, shape_cut) VALUES (?, ?)';
    $checkTypes = 's';
    $checkParams = [$shape_cut];
    $insertTypes = 'is';
    $insertParams = [$user_id, $shape_cut];
} elseif ($colour !== '') {
    $table_name = 'sm_master_colour';
    $checkSql = 'SELECT id FROM sm_master_colour WHERE ' . master_scope_sql($user_id) . ' AND colour = ? LIMIT 1';
    $insertSql = 'INSERT INTO sm_master_colour (user_id, colour) VALUES (?, ?)';
    $checkTypes = 's';
    $checkParams = [$colour];
    $insertTypes = 'is';
    $insertParams = [$user_id, $colour];
} elseif ($ri !== '') {
    $table_name = 'sm_master_ri';
    $checkSql = 'SELECT id FROM sm_master_ri WHERE ' . master_scope_sql($user_id) . ' AND ri = ? LIMIT 1';
    $insertSql = 'INSERT INTO sm_master_ri (user_id, ri) VALUES (?, ?)';
    $checkTypes = 's';
    $checkParams = [$ri];
    $insertTypes = 'is';
    $insertParams = [$user_id, $ri];
} elseif ($magni !== '') {
    $table_name = 'sm_master_magnification';
    $checkSql = 'SELECT id FROM sm_master_magnification WHERE ' . master_scope_sql($user_id) . ' AND magni = ? LIMIT 1';
    $insertSql = 'INSERT INTO sm_master_magnification (user_id, magni) VALUES (?, ?)';
    $checkTypes = 's';
    $checkParams = [$magni];
    $insertTypes = 'is';
    $insertParams = [$user_id, $magni];
} else {
    echo json_encode(['status' => 'error', 'message' => 'Please fill the required field before saving.']);
    exit;
}

$check = $conn->prepare($checkSql);
if (!$check) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to validate master data.']);
    exit;
}

master_bind_params($check, $checkTypes, $checkParams);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if ($exists) {
    echo json_encode(['status' => 'error', 'message' => 'This value already exists in the master menu.']);
    exit;
}

$insert = $conn->prepare($insertSql);
if (!$insert) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to save master data.']);
    exit;
}

master_bind_params($insert, $insertTypes, $insertParams);
if ($insert->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Master data saved successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unable to save master data.']);
}
$insert->close();
$conn->close();
