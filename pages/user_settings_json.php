<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

header('Content-Type: application/json');

$file = isset($_GET['file']) ? $_GET['file'] : '';
$reportType = atm_report_type($_GET['report_type'] ?? 'S');
if ($file === 'positions') {
    echo json_encode(atm_read_positions($reportType));
    exit;
}

if ($file === 'settings') {
    $defaults = atm_default_positions($reportType);
    $displayDefaults = [];
    foreach ($defaults['fields'] as $key => $field) $displayDefaults[$key] = $field['display'];
    echo json_encode(atm_read_json(atm_layout_file($reportType, 'settings'), $displayDefaults));
    exit;
}

if ($file === 'print') {
    echo json_encode(atm_read_json(atm_user_file('atm-print-settings.json'), atm_default_print_settings()));
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Unknown settings file.']);
?>
