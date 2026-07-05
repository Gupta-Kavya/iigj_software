<?php
ini_set('log_errors', 'On');
ini_set('display_errors', 'Off');
ini_set('error_reporting', E_ALL);

include("db_connect.php");
require_once 'toast_redirect.php';
require_once 'master_data_helper.php';
auth_block_demo_action('Master data changes', 'add-master.php', true);

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function master_edit_respond($status, $message, $redirectTo = 'add-master.php', $type = 'success')
{
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
        ]);
        exit;
    }

    toast_redirect_page($message, $redirectTo, $type);
    exit;
}

$id = (int)($_GET['key'] ?? 0);
$user_id = auth_current_user_id();
$redirect_to = $_GET['redirect_to'] ?? 'add-master.php';

$table_name = $_POST['table_name'] ?? '';
$col_name = $_POST['col_name'] ?? '';

$allowed_columns = [
    'sm_master_stone_name' => ['stone_name', 'group'],
    'sm_master_shape_cut' => ['shape_cut'],
    'sm_master_colour' => ['colour'],
    'sm_master_ri' => ['ri'],
    'sm_master_magnification' => ['magni'],
];

if ($id <= 0 || !array_key_exists($table_name, $allowed_columns)) {
    master_edit_respond('error', 'Invalid master entry selected.', $redirect_to, 'error');
}

$ownerStmt = $conn->prepare("SELECT user_id FROM `$table_name` WHERE id = ? LIMIT 1");
if (!$ownerStmt) {
    master_edit_respond('error', 'Unable to verify master entry owner.', $redirect_to, 'error');
}
$ownerStmt->bind_param('i', $id);
$ownerStmt->execute();
$ownerRow = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();

if (!$ownerRow) {
    master_edit_respond('error', 'Master entry not found.', $redirect_to, 'error');
}
if (!master_can_manage_owner($user_id, (int) $ownerRow['user_id'])) {
    master_edit_respond('error', 'Shared default master data cannot be edited from this account.', $redirect_to, 'error');
}

if ($table_name === 'sm_master_stone_name') {
    $stone_name = trim($_POST['stone_name'] ?? '');
    $group = trim($_POST['group'] ?? '');

    if ($stone_name === '' || $group === '') {
        master_edit_respond('error', 'Please enter both stone name and species / group.', $redirect_to, 'error');
    }

    $stmt = $conn->prepare("UPDATE sm_master_stone_name SET stone_name = ?, `group` = ? WHERE user_id = ? AND id = ?");
    if (!$stmt) {
        master_edit_respond('error', 'Unable to prepare update request.', $redirect_to, 'error');
    }

    $stmt->bind_param('ssii', $stone_name, $group, $user_id, $id);
} else {
    if (!in_array($col_name, $allowed_columns[$table_name], true)) {
        master_edit_respond('error', 'Invalid master column selected.', $redirect_to, 'error');
    }

    $value = trim($_POST[$col_name] ?? '');
    if ($value === '') {
        master_edit_respond('error', 'Please enter a value before saving.', $redirect_to, 'error');
    }

    $stmt = $conn->prepare("UPDATE `$table_name` SET `$col_name` = ? WHERE user_id = ? AND id = ?");
    if (!$stmt) {
        master_edit_respond('error', 'Unable to prepare update request.', $redirect_to, 'error');
    }

    $stmt->bind_param('sii', $value, $user_id, $id);
}

if ($stmt->execute()) {
    if ($stmt->affected_rows >= 0) {
        master_edit_respond('success', 'Master entry updated successfully.', $redirect_to, 'success');
    }
}

master_edit_respond('error', 'Unable to update master data.', $redirect_to, 'error');
