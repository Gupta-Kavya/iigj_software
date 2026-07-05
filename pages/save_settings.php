<?php
require_once 'auth.php';
auth_require_login();
header('Content-Type: application/json');
require_once 'atm_config.php';
auth_block_demo_action('Builder settings changes', 'certificate-builder.php', true);

$submitted = isset($_POST['settings']) ? json_decode($_POST['settings'], true) : null;
if (!is_array($submitted)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid settings data.']);
    exit;
}

$settings = atm_default_fields();
foreach ($settings as $key => $default) {
    if (array_key_exists($key, $submitted)) {
        $settings[$key] = atm_display_value($submitted[$key]);
    }
}

$saved = file_put_contents(atm_user_file('settings.json'), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
if ($saved === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save settings.']);
    exit;
}

echo json_encode(['status' => 'success']);
