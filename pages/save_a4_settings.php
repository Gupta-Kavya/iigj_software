<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'a4_config.php';

header('Content-Type: application/json');

$submitted = isset($_POST['settings']) ? json_decode($_POST['settings'], true) : null;
if (!is_array($submitted)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid A4 settings data.']);
    exit;
}

$reportType = atm_report_type($_POST['report_type'] ?? 'S');
$defaults = a4_default_settings($reportType);
$settings = $defaults;
$orientation = isset($submitted['orientation']) && $submitted['orientation'] === 'portrait' ? 'portrait' : 'landscape';
$settings['orientation'] = $orientation;
$maxX = $orientation === 'portrait' ? 794 : 1122;
$maxY = $orientation === 'portrait' ? 1122 : 794;

$submittedBackground = isset($submitted['backgroundImage']) ? trim((string) $submitted['backgroundImage']) : '';
if ($submittedBackground === '') {
    $settings['backgroundImage'] = '';
} elseif (strpos($submittedBackground, 'user_data/') === 0) {
    $settings['backgroundImage'] = $submittedBackground;
} else {
    $settings['backgroundImage'] = $defaults['backgroundImage'];
}

$fontFamily = isset($submitted['fontFamily']) ? $submitted['fontFamily'] : $defaults['fontFamily'];
$settings['fontFamily'] = a4_font_family_allowed($fontFamily) ? $fontFamily : $defaults['fontFamily'];
$settings['fontSize'] = atm_clamp(isset($submitted['fontSize']) ? $submitted['fontSize'] : $defaults['fontSize'], 8, 40);
$settings['fontColor'] = isset($submitted['fontColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $submitted['fontColor']) ? strtoupper($submitted['fontColor']) : $defaults['fontColor'];
$settings['labelWidth'] = atm_clamp(isset($submitted['labelWidth']) ? $submitted['labelWidth'] : $defaults['labelWidth'], 50, 350);

$alignValue = function ($value) {
    return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
};

foreach ($defaults['fields'] as $key => $fallback) {
    $item = isset($submitted['fields'][$key]) && is_array($submitted['fields'][$key]) ? $submitted['fields'][$key] : [];
    $label = trim((string) ($item['label'] ?? $fallback['label']));
    $fieldColor = isset($item['fontColor']) ? trim((string) $item['fontColor']) : '';
    $fieldFontFamily = isset($item['fontFamily']) ? trim((string) $item['fontFamily']) : '';
    $settings['fields'][$key] = [
        'label' => $label !== '' ? $label : $fallback['label'],
        'column' => $fallback['column'],
        'valueType' => $fallback['valueType'] ?? '',
        'display' => atm_display_value(isset($item['display']) ? $item['display'] : $fallback['display']),
        'showLabel' => atm_display_value(isset($item['showLabel']) ? $item['showLabel'] : $fallback['showLabel']),
        'showColon' => isset($item['showColon']) && $item['showColon'] === 'none' ? 'none' : 'block',
        'labelFontWeight' => isset($item['labelFontWeight']) && $item['labelFontWeight'] === 'bold' ? 'bold' : 'normal',
        'fontWeight' => isset($item['fontWeight']) && $item['fontWeight'] === 'bold' ? 'bold' : 'normal',
        'fontSize' => isset($item['fontSize']) && $item['fontSize'] !== '' ? atm_clamp($item['fontSize'], 8, 40) : null,
        'fontColor' => preg_match('/^#[0-9a-fA-F]{6}$/', $fieldColor) ? strtoupper($fieldColor) : '',
        'fontFamily' => $fieldFontFamily !== '' && a4_font_family_allowed($fieldFontFamily) ? $fieldFontFamily : '',
        'labelWidth' => isset($item['labelWidth']) && $item['labelWidth'] !== '' ? atm_clamp($item['labelWidth'], 0, 350) : null,
        'labelAlign' => $alignValue($item['labelAlign'] ?? ($fallback['labelAlign'] ?? 'left')),
        'valueAlign' => $alignValue($item['valueAlign'] ?? ($fallback['valueAlign'] ?? 'left')),
        'x' => atm_clamp(isset($item['x']) ? $item['x'] : $fallback['x'], 0, $maxX),
        'y' => atm_clamp(isset($item['y']) ? $item['y'] : $fallback['y'], 0, $maxY),
        'w' => atm_clamp(isset($item['w']) ? $item['w'] : $fallback['w'], 30, $maxX),
        'h' => atm_clamp(isset($item['h']) ? $item['h'] : $fallback['h'], 20, $maxY),
    ];
}

foreach (['stoneImage', 'qrCode'] as $key) {
    $fallback = $defaults[$key];
    $item = isset($submitted[$key]) && is_array($submitted[$key]) ? $submitted[$key] : [];
    $settings[$key] = [
        'display' => atm_display_value(isset($item['display']) ? $item['display'] : $fallback['display']),
        'x' => atm_clamp(isset($item['x']) ? $item['x'] : $fallback['x'], 0, $maxX),
        'y' => atm_clamp(isset($item['y']) ? $item['y'] : $fallback['y'], 0, $maxY),
        'w' => atm_clamp(isset($item['w']) ? $item['w'] : $fallback['w'], 20, $maxX),
        'h' => atm_clamp(isset($item['h']) ? $item['h'] : $fallback['h'], 20, $maxY),
    ];
}

$settings['additionalImages'] = atm_normalize_additional_images($submitted['additionalImages'] ?? [], $maxX, $maxY, 120, 80);

$saved = file_put_contents(a4_settings_file($reportType), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
if ($saved === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save A4 settings.']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'A4 settings saved.']);
?>
