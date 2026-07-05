<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'master_data_helper.php';

header('Content-Type: application/json; charset=utf-8');

$userId = auth_current_user_id();
$rows = master_fetch_rows($conn, 'sm_master_colour', ['colour'], $userId, ['colour'], 'colour');
$colours = [];
foreach ($rows as $row) {
    $colour = trim((string) ($row['colour'] ?? ''));
    if ($colour !== '') {
        $colours[] = $colour;
    }
}

echo json_encode(['status' => 'success', 'colours' => $colours]);
?>
