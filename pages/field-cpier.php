<?php
require_once 'auth.php';
auth_require_login();
include("db_connect.php");
require_once 'atm_config.php';
// Perform a SELECT query on a table called "sm_form_data"
$field_no = mysqli_real_escape_string($conn , $_POST["field_no"]);
$report_type = isset($_POST['report_type']) && in_array($_POST['report_type'], ['S', 'D', 'J', 'R'], true) ? $_POST['report_type'] : '';
$user_id = auth_current_user_id();
$scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');
$hasUserId = false;
$columnResult = @$conn->query("SHOW COLUMNS FROM sm_form_data LIKE 'user_id'");
if ($columnResult && $columnResult->num_rows > 0) {
  $hasUserId = true;
}
if ($hasUserId && $report_type !== '') {
  $stmt = $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND certi_no = ? AND `type` = ?");
  $stmt->bind_param('is', $field_no, $report_type);
} elseif ($hasUserId) {
  $stmt = $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND certi_no = ?");
  $stmt->bind_param('i', $field_no);
} elseif ($report_type !== '') {
  $stmt = $conn->prepare("SELECT * FROM sm_form_data WHERE certi_no = ? AND `type` = ?");
  $stmt->bind_param('is', $field_no, $report_type);
} else {
  $stmt = $conn->prepare("SELECT * FROM sm_form_data WHERE certi_no = ?");
  $stmt->bind_param('i', $field_no);
}
$stmt->execute();
$result = $stmt->get_result();

// Check if any rows were returned
if ($result->num_rows > 0) {
  // Output data of each row
  while($row = $result->fetch_assoc()) {

    echo json_encode($row);

  }
} else {
  echo json_encode(false);
}

// Close the database connection
mysqli_close($conn);
?>
