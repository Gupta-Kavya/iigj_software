<?php
require_once 'auth.php';
auth_require_login();
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'atm_config.php';

$submitted = isset($_POST['positions']) ? json_decode($_POST['positions'], true) : null;
if (!is_array($submitted)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid position data.']);
    exit;
}

$reportType = atm_report_type($_POST['report_type'] ?? 'S');
$defaults = atm_default_positions($reportType);
$positions = [];
foreach ($defaults as $name => $fallback) {
    if ($name === 'fields' || $name === 'additionalImages') {
        continue;
    }
    $item = isset($submitted[$name]) && is_array($submitted[$name]) ? $submitted[$name] : [];
    $positions[$name] = [
        'top' => atm_clamp(isset($item['top']) ? $item['top'] : $fallback['top'], 0, 204),
        'left' => atm_clamp(isset($item['left']) ? $item['left'] : $fallback['left'], 0, 321),
        'width' => atm_clamp(isset($item['width']) ? $item['width'] : $fallback['width'], 10, 321),
        'height' => atm_clamp(isset($item['height']) ? $item['height'] : $fallback['height'], 10, 204),
        'display' => atm_display_value(isset($item['display']) ? $item['display'] : $fallback['display']),
    ];
    if ($name === 'table') {
        $positions[$name]['fontSize'] = atm_clamp(isset($item['fontSize']) ? $item['fontSize'] : $fallback['fontSize'], 4, 18);
        $positions[$name]['rowSpacing'] = atm_clamp(isset($item['rowSpacing']) ? $item['rowSpacing'] : $fallback['rowSpacing'], 0, 12);
        $positions[$name]['labelWidth'] = atm_clamp(isset($item['labelWidth']) ? $item['labelWidth'] : $fallback['labelWidth'], 20, 180);
        $allowedFonts = ['Arial', 'Arial Nova Cond Light', 'Calibri', 'Times New Roman', 'Verdana'];
        $fontFamily = isset($item['fontFamily']) ? $item['fontFamily'] : $fallback['fontFamily'];
        $positions[$name]['fontFamily'] = in_array($fontFamily, $allowedFonts, true) ? $fontFamily : $fallback['fontFamily'];
        $fontColor = isset($item['fontColor']) ? trim($item['fontColor']) : $fallback['fontColor'];
        $positions[$name]['fontColor'] = preg_match('/^#[0-9a-fA-F]{6}$/', $fontColor) ? strtoupper($fontColor) : $fallback['fontColor'];
    }
}

$positions['fields'] = [];
$displaySettings = [];
$alignValue = function ($value) {
    return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
};
foreach ($defaults['fields'] as $key => $fallback) {
    $item = isset($submitted['fields'][$key]) && is_array($submitted['fields'][$key]) ? $submitted['fields'][$key] : [];
    $display = atm_display_value(isset($item['display']) ? $item['display'] : $fallback['display']);
    $label = trim((string) ($item['label'] ?? $fallback['label']));
    $showLabel = atm_display_value(isset($item['showLabel']) ? $item['showLabel'] : $fallback['showLabel']);
    $fontColor = isset($item['fontColor']) ? trim((string) $item['fontColor']) : '';
    $labelFontColor = isset($item['labelFontColor']) ? trim((string) $item['labelFontColor']) : '';
    $valueFontColor = isset($item['valueFontColor']) ? trim((string) $item['valueFontColor']) : '';
    $positions['fields'][$key] = [
        'label' => $label !== '' ? $label : $fallback['label'],
        'column' => $fallback['column'],
        'valueType' => $fallback['valueType'] ?? '',
        'display' => $display,
        'showLabel' => $showLabel,
        'showColon' => isset($item['showColon']) && $item['showColon'] === 'none' ? 'none' : 'block',
        'labelFontWeight' => isset($item['labelFontWeight']) && $item['labelFontWeight'] === 'bold' ? 'bold' : 'normal',
        'fontWeight' => isset($item['fontWeight']) && $item['fontWeight'] === 'bold' ? 'bold' : 'normal',
        'fontSize' => isset($item['fontSize']) && $item['fontSize'] !== '' ? atm_clamp($item['fontSize'], 4, 18) : null,
        'fontColor' => preg_match('/^#[0-9a-fA-F]{6}$/', $fontColor) ? strtoupper($fontColor) : '',
        'labelFontColor' => preg_match('/^#[0-9a-fA-F]{6}$/', $labelFontColor) ? strtoupper($labelFontColor) : '',
        'valueFontColor' => preg_match('/^#[0-9a-fA-F]{6}$/', $valueFontColor) ? strtoupper($valueFontColor) : '',
        'labelWidth' => isset($item['labelWidth']) && $item['labelWidth'] !== '' ? atm_clamp($item['labelWidth'], 0, 180) : null,
        'labelAlign' => $alignValue($item['labelAlign'] ?? ($fallback['labelAlign'] ?? 'left')),
        'valueAlign' => $alignValue($item['valueAlign'] ?? ($fallback['valueAlign'] ?? 'left')),
        'x' => atm_clamp(isset($item['x']) ? $item['x'] : $fallback['x'], 0, 321),
        'y' => atm_clamp(isset($item['y']) ? $item['y'] : $fallback['y'], 0, 204),
        'w' => atm_clamp(isset($item['w']) ? $item['w'] : $fallback['w'], 20, 321),
        'h' => atm_clamp(isset($item['h']) ? $item['h'] : $fallback['h'], 6, 204),
    ];
    $displaySettings[$key] = $display;
}

$positions['additionalImages'] = atm_normalize_additional_images($submitted['additionalImages'] ?? [], 321, 204, 40, 40);

$saved = file_put_contents(atm_layout_file($reportType), json_encode($positions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
if ($saved === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save positions.']);
    exit;
}

file_put_contents(atm_layout_file($reportType, 'settings'), json_encode($displaySettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

echo json_encode(['status' => 'success']);
