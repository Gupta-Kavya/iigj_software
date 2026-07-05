<?php
include("db_connect.php");
require_once 'toast_redirect.php';
require_once 'master_data_helper.php';
auth_block_demo_action('Master data deletion', 'add-master.php', true);

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function master_delete_respond($status, $message, $redirectTo = 'add-master.php', $type = 'success')
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

$id = (int)($_REQUEST['key'] ?? 0);
$table_name = $_REQUEST['table_name'] ?? '';
$redirect_to = $_REQUEST['redirect_to'] ?? 'add-master.php';
$allowed_tables = ['sm_master_stone_name', 'sm_master_shape_cut', 'sm_master_colour', 'sm_master_ri', 'sm_master_magnification'];

if ($id <= 0 || !in_array($table_name, $allowed_tables, true)) {
    master_delete_respond('error', 'Invalid master entry selected.', $redirect_to, 'error');
}

$user_id = auth_current_user_id();
$ownerStmt = $conn->prepare("SELECT user_id FROM `$table_name` WHERE id = ? LIMIT 1");
if (!$ownerStmt) {
    master_delete_respond('error', 'Unable to verify master entry owner.', $redirect_to, 'error');
}
$ownerStmt->bind_param('i', $id);
$ownerStmt->execute();
$ownerRow = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();

if (!$ownerRow) {
    master_delete_respond('error', 'Master entry not found.', $redirect_to, 'error');
}
if (!master_can_manage_owner($user_id, (int) $ownerRow['user_id'])) {
    master_delete_respond('error', 'Shared default master data cannot be deleted from this account.', $redirect_to, 'error');
}

$stmt = $conn->prepare("DELETE FROM `$table_name` WHERE user_id = ? AND id = ?");

if (!$stmt) {
    master_delete_respond('error', 'Unable to prepare delete request.', $redirect_to, 'error');
}

$stmt->bind_param('ii', $user_id, $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    master_delete_respond('success', 'Master entry deleted successfully.', $redirect_to, 'success');
}

master_delete_respond('error', 'Unable to delete master data.', $redirect_to, 'error');
