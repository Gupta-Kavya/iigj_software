<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

header('Content-Type: application/json');

$user_id = auth_current_user_id();
$numbering = atm_next_certificate_number($conn, $user_id);
$last = !empty($numbering['settings']['locked']) ? max(0, (int) $numbering['certi_no'] - 1) : 0;

echo json_encode([
    'certi_no' => $last,
    'next_certi_no' => (int) $numbering['certi_no'],
    'next_report_no' => (string) $numbering['report_no'],
    'report_prefix' => (string) ($numbering['settings']['report_prefix'] ?? 'R'),
    'start_number' => (int) ($numbering['settings']['start_number'] ?? 1),
    'number_locked' => !empty($numbering['settings']['locked']),
]);
?>
