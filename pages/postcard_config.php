<?php
require_once 'a4_config.php';

function postcard_default_fields($type = 'S')
{
    $type = atm_report_type($type);
    $fields = a4_default_fields($type);
    $layout = [
        'reportNo' => ['x' => 70, 'y' => 90, 'w' => 610, 'h' => 38],
        'date' => ['x' => 70, 'y' => 136, 'w' => 610, 'h' => 38],
        'stoneName' => ['x' => 70, 'y' => 208, 'w' => 600, 'h' => 40],
        'description' => ['x' => 70, 'y' => 208, 'w' => 600, 'h' => 82],
        'weight' => ['x' => 70, 'y' => 258, 'w' => 600, 'h' => 40],
        'diamondWeight' => ['x' => 70, 'y' => 308, 'w' => 600, 'h' => 40],
        'shapeCut' => ['x' => 70, 'y' => 358, 'w' => 600, 'h' => 40],
        'dimension' => ['x' => 70, 'y' => 408, 'w' => 600, 'h' => 40],
        'colour' => ['x' => 70, 'y' => 458, 'w' => 600, 'h' => 40],
        'opticCharacter' => ['x' => 70, 'y' => 508, 'w' => 600, 'h' => 40],
        'refractiveIndex' => ['x' => 70, 'y' => 558, 'w' => 600, 'h' => 40],
        'specificGravity' => ['x' => 70, 'y' => 608, 'w' => 600, 'h' => 40],
        'magnification' => ['x' => 70, 'y' => 658, 'w' => 600, 'h' => 40],
        'speciesGroup' => ['x' => 70, 'y' => 708, 'w' => 600, 'h' => 40],
        'origin' => ['x' => 70, 'y' => 758, 'w' => 600, 'h' => 40],
        'hardness' => ['x' => 70, 'y' => 808, 'w' => 600, 'h' => 40],
        'remarks' => ['x' => 70, 'y' => 858, 'w' => 760, 'h' => 82],
        'specificComments' => ['x' => 70, 'y' => 858, 'w' => 760, 'h' => 82],
        'testCarriedOut' => ['x' => 70, 'y' => 955, 'w' => 760, 'h' => 82],
        'rudrakshaRemarks' => ['x' => 70, 'y' => 1048, 'w' => 760, 'h' => 82],
        'issuedTo' => ['x' => 70, 'y' => 1160, 'w' => 760, 'h' => 48],
    ];

    $tickY = 1040;
    foreach ($fields as $key => &$field) {
        if (isset($layout[$key])) {
            $field = array_replace($field, $layout[$key]);
        } elseif (($field['valueType'] ?? '') === 'tick') {
            $field['x'] = 710;
            $field['y'] = $tickY;
            $field['w'] = 42;
            $field['h'] = 36;
            $tickY += 42;
        } elseif (($field['display'] ?? 'none') !== 'none') {
            $field['x'] = 70;
            $field['w'] = 760;
            $field['h'] = 40;
        }
    }
    unset($field);

    return $fields;
}

function postcard_default_settings($type = 'S')
{
    $type = atm_report_type($type);
    return [
        'orientation' => 'portrait',
        'backgroundImage' => '',
        'fontFamily' => 'Arial',
        'fontSize' => 22,
        'fontColor' => '#000000',
        'labelWidth' => 260,
        'pageBaseWidth' => 1000,
        'pageBaseHeight' => 1500,
        'pageWidthMm' => 100,
        'pageHeightMm' => 150,
        'fields' => postcard_default_fields($type),
        'stoneImage' => ['display' => 'block', 'x' => 700, 'y' => 180, 'w' => 230, 'h' => 230],
        'qrCode' => ['display' => 'block', 'x' => 785, 'y' => 1280, 'w' => 130, 'h' => 130],
        'additionalImages' => [],
    ];
}

function postcard_page_dimensions($orientation)
{
    $orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';
    return $orientation === 'landscape'
        ? ['baseWidth' => 1500, 'baseHeight' => 1000, 'widthMm' => 150, 'heightMm' => 100]
        : ['baseWidth' => 1000, 'baseHeight' => 1500, 'widthMm' => 100, 'heightMm' => 150];
}

function postcard_settings_file($type = 'S')
{
    $type = atm_report_type($type);
    if (preg_match('/^CS([0-9]+)$/', $type, $match)) {
        return atm_user_file('postcard-report-settings-colour-stone-type-' . (int) $match[1] . '.json');
    }
    if (preg_match('/^PR([0-9]+)$/', $type, $match)) {
        return atm_user_file('postcard-report-settings-pearl-type-' . (int) $match[1] . '.json');
    }
    if ($type === 'D') return atm_user_file('postcard-report-settings-diamond.json');
    if ($type === 'J') return atm_user_file('postcard-report-settings-jewellery.json');
    if ($type === 'P') return atm_user_file('postcard-report-settings-pearl.json');
    if ($type === 'R') return atm_user_file('postcard-report-settings-rudraksha.json');
    return atm_user_file('postcard-report-settings.json');
}

function postcard_read_settings($type = 'S')
{
    $type = atm_report_type($type);
    $defaults = postcard_default_settings($type);
    $saved = atm_read_json(postcard_settings_file($type), []);
    if (!is_array($saved)) {
        return $defaults;
    }

    $settings = array_replace_recursive($defaults, $saved);
    $settings['orientation'] = isset($settings['orientation']) && $settings['orientation'] === 'landscape' ? 'landscape' : 'portrait';
    $dimensions = postcard_page_dimensions($settings['orientation']);
    $settings['pageBaseWidth'] = $dimensions['baseWidth'];
    $settings['pageBaseHeight'] = $dimensions['baseHeight'];
    $settings['pageWidthMm'] = $dimensions['widthMm'];
    $settings['pageHeightMm'] = $dimensions['heightMm'];

    $normalizedFields = [];
    $savedFieldKeys = isset($saved['fields']) && is_array($saved['fields']) ? array_keys($saved['fields']) : [];
    $defaultFieldKeys = array_keys($defaults['fields']);
    $isLegacyFieldLayout = count(array_intersect($savedFieldKeys, $defaultFieldKeys)) === 0;

    foreach ($defaults['fields'] as $key => $fallback) {
        $savedField = isset($saved['fields'][$key]) && is_array($saved['fields'][$key]) ? $saved['fields'][$key] : [];
        $normalizedFields[$key] = $isLegacyFieldLayout ? $fallback : array_replace($fallback, array_intersect_key($savedField, [
            'label' => true,
            'valueType' => true,
            'display' => true,
            'showLabel' => true,
            'showColon' => true,
            'labelFontWeight' => true,
            'fontWeight' => true,
            'fontSize' => true,
            'fontColor' => true,
            'labelFontColor' => true,
            'valueFontColor' => true,
            'fontFamily' => true,
            'labelWidth' => true,
            'labelAlign' => true,
            'valueAlign' => true,
            'x' => true,
            'y' => true,
            'w' => true,
            'h' => true,
        ]));
        $normalizedFields[$key]['column'] = $fallback['column'];
        $normalizedFields[$key]['valueType'] = $fallback['valueType'] ?? '';
        if (atm_base_report_type($type) === 'S' && in_array($key, ['speciesGroup', 'speciesMode'], true)) {
            $normalizedFields[$key]['display'] = 'none';
        }
    }
    $settings['fields'] = $normalizedFields;
    if ($isLegacyFieldLayout) {
        $settings['stoneImage'] = $defaults['stoneImage'];
        $settings['qrCode'] = $defaults['qrCode'];
    }
    $settings['additionalImages'] = atm_normalize_additional_images($settings['additionalImages'] ?? [], $dimensions['baseWidth'], $dimensions['baseHeight'], 150, 100);

    return $settings;
}

function postcard_background_path($settings)
{
    return a4_background_path($settings);
}

function postcard_background_url($settings)
{
    return a4_background_url($settings);
}
?>
