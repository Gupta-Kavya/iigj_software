<?php
require_once 'auth.php';
auth_require_login();

include("db_connect.php");
require_once 'atm_config.php';
$certi_no = $_POST['form_certi_no'];
$user_id = auth_current_user_id();
$hasFormUserId = atm_table_has_column($conn, 'sm_form_data', 'user_id');
$scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');
$stmt = $hasFormUserId
    ? $conn->prepare("SELECT certi_no FROM sm_form_data WHERE {$scopeSql} AND certi_no = ?")
    : $conn->prepare("SELECT certi_no FROM sm_form_data WHERE certi_no = ?");
if ($hasFormUserId) {
    $stmt->bind_param('i', $certi_no);
} else {
    $stmt->bind_param('i', $certi_no);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    echo "null";

} else {
    $numbering = atm_next_certificate_number($conn, $user_id);
    echo (int) $numbering['certi_no'];

}
$conn->close();
?>
