<?php
include("db_connect.php");
require_once 'master_data_helper.php';

$master_stone_name = $_POST['master_stone_name'] ?? '';

$user_id = auth_current_user_id();
header('Content-Type: application/json');

$stmt = $conn->prepare("SELECT * FROM sm_master_stone_name WHERE " . master_scope_sql($user_id) . " AND stone_name = ? ORDER BY CASE WHEN user_id = ? THEN 0 ELSE 1 END, id ASC LIMIT 1");
$stmt->bind_param('si', $master_stone_name, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode($row);
} else {
  echo json_encode(['status' => 'not_found']);
}
$stmt->close();
$conn->close();
?>
