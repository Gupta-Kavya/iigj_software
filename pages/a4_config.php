<?php
require_once 'atm_config.php';

function a4_default_fields($type = 'S')
{
    $type = atm_report_type($type);
    $definitions = atm_field_definitions($type);
    $fields = [
        'reportNo' => ['label' => 'Report No', 'column' => 'report_no', 'display' => 'block', 'x' => 90, 'y' => 132, 'w' => 520, 'h' => 30],
        'date' => ['label' => 'Date', 'column' => 'date', 'display' => 'block', 'x' => 90, 'y' => 164, 'w' => 520, 'h' => 30],
        'stoneName' => ['label' => 'Stone Name', 'column' => 'stone_name', 'display' => 'block', 'x' => 90, 'y' => 196, 'w' => 520, 'h' => 30],
        'weight' => ['label' => 'Weight', 'column' => 'stone_wt', 'display' => 'block', 'x' => 90, 'y' => 228, 'w' => 520, 'h' => 30],
        'shapeCut' => ['label' => 'Shape / Cut', 'column' => 'shape_cut', 'display' => 'block', 'x' => 90, 'y' => 260, 'w' => 520, 'h' => 30],
        'dimension' => ['label' => 'Dimension', 'column' => 'dimension', 'display' => 'block', 'x' => 90, 'y' => 292, 'w' => 520, 'h' => 30],
        'colour' => ['label' => 'Colour', 'column' => 'color', 'display' => 'block', 'x' => 90, 'y' => 324, 'w' => 520, 'h' => 30],
        'opticCharacter' => ['label' => 'Optic Character', 'column' => 'optic_char', 'display' => 'block', 'x' => 90, 'y' => 356, 'w' => 520, 'h' => 30],
        'refractiveIndex' => ['label' => 'Refractive Index', 'column' => 'ref_index', 'display' => 'block', 'x' => 90, 'y' => 388, 'w' => 520, 'h' => 30],
        'specificGravity' => ['label' => 'Specific Gravity', 'column' => 'spe_gravit', 'display' => 'block', 'x' => 90, 'y' => 420, 'w' => 520, 'h' => 30],
        'magnification' => ['label' => 'Magnification', 'column' => 'magni', 'display' => 'block', 'x' => 90, 'y' => 452, 'w' => 520, 'h' => 30],
        'speciesGroup' => ['label' => 'Species / Group', 'column' => 'spe_group', 'display' => 'block', 'x' => 90, 'y' => 484, 'w' => 520, 'h' => 30],
        'origin' => ['label' => 'Origin', 'column' => 'origin', 'display' => 'block', 'x' => 90, 'y' => 516, 'w' => 520, 'h' => 30],
        'hardness' => ['label' => 'Hardness', 'column' => 'hardness', 'display' => 'block', 'x' => 90, 'y' => 548, 'w' => 520, 'h' => 30],
        'remarks' => ['label' => 'Remarks', 'column' => 'comment', 'display' => 'block', 'x' => 90, 'y' => 580, 'w' => 560, 'h' => 34],
        'issuedTo' => ['label' => 'Issued To', 'column' => 'issued_to', 'display' => 'block', 'x' => 90, 'y' => 616, 'w' => 560, 'h' => 34],
        'diamondWeight' => ['label' => 'Diamond Weight', 'column' => 'dia_wt', 'display' => 'none', 'x' => 660, 'y' => 132, 'w' => 350, 'h' => 30],
        'clarity' => ['label' => 'Clarity', 'column' => 'clarity', 'display' => 'none', 'x' => 660, 'y' => 164, 'w' => 350, 'h' => 30],
        'finish' => ['label' => 'Finish', 'column' => 'finish', 'display' => 'none', 'x' => 660, 'y' => 196, 'w' => 350, 'h' => 30],
        'cutGrade' => ['label' => 'Cut', 'column' => 'cut', 'display' => 'none', 'x' => 660, 'y' => 228, 'w' => 350, 'h' => 30],
        'tableValue' => ['label' => 'Table', 'column' => 'table', 'display' => 'none', 'x' => 660, 'y' => 260, 'w' => 350, 'h' => 30],
        'crown' => ['label' => 'Crown Height', 'column' => 'crown', 'display' => 'none', 'x' => 660, 'y' => 292, 'w' => 350, 'h' => 30],
        'girdle' => ['label' => 'Girdle', 'column' => 'girdle', 'display' => 'none', 'x' => 660, 'y' => 324, 'w' => 350, 'h' => 30],
        'pavilionDepth' => ['label' => 'Pavilion Depth', 'column' => 'pav_depth', 'display' => 'none', 'x' => 660, 'y' => 356, 'w' => 350, 'h' => 30],
        'tableDepth' => ['label' => 'Table Depth', 'column' => 'tab_depth', 'display' => 'none', 'x' => 660, 'y' => 388, 'w' => 350, 'h' => 30],
        'fluorescence' => ['label' => 'Fluorescence', 'column' => 'flurance', 'display' => 'none', 'x' => 660, 'y' => 420, 'w' => 350, 'h' => 30],
        'description' => ['label' => 'Description', 'column' => 'desc', 'display' => 'none', 'x' => 90, 'y' => 196, 'w' => 560, 'h' => 34],
        'face' => ['label' => 'Face', 'column' => 'faces', 'display' => 'none', 'x' => 90, 'y' => 356, 'w' => 520, 'h' => 30],
        'specificComments' => ['label' => 'Specific Comments', 'column' => 'comment', 'display' => 'none', 'x' => 90, 'y' => 388, 'w' => 560, 'h' => 42],
        'testCarriedOut' => ['label' => 'Test Carried Out', 'column' => 'rem2', 'display' => 'none', 'x' => 90, 'y' => 434, 'w' => 560, 'h' => 42],
        'rudrakshaRemarks' => ['label' => 'Remarks', 'column' => 'rem1', 'display' => 'none', 'x' => 90, 'y' => 480, 'w' => 560, 'h' => 42],
    ];

    foreach ($fields as $key => &$field) {
        if (isset($definitions[$key]['label'])) {
            $field['label'] = $definitions[$key]['label'];
        }
        if (isset($definitions[$key]['column'])) {
            $field['column'] = $definitions[$key]['column'];
        }
        $field['valueType'] = $definitions[$key]['valueType'] ?? '';
    }
    unset($field);

    $extraY = 650;
    foreach ($definitions as $key => $definition) {
        if (isset($fields[$key])) {
            continue;
        }
        $isTickField = isset($definition['valueType']) && $definition['valueType'] === 'tick';
        $fields[$key] = [
            'label' => $definition['label'],
            'column' => $definition['column'],
            'valueType' => $definition['valueType'] ?? '',
            'display' => atm_default_fields()[$key] ?? 'none',
            'x' => $isTickField ? 790 : 90,
            'y' => $isTickField ? 365 + ((count($fields) % 12) * 18) : $extraY,
            'w' => $isTickField ? 32 : 520,
            'h' => $isTickField ? 24 : 30,
        ];
        if (!$isTickField) {
            $extraY += 32;
        }
    }

    foreach ($fields as &$field) {
        $field['showLabel'] = ($field['valueType'] ?? '') === 'tick' ? 'none' : 'block';
        $field['showColon'] = 'block';
        $field['labelFontWeight'] = 'normal';
        $field['fontWeight'] = 'normal';
        $field['fontSize'] = null;
        $field['fontColor'] = '';
        $field['fontFamily'] = '';
        $field['labelWidth'] = null;
        $field['labelAlign'] = 'left';
        $field['valueAlign'] = 'left';
    }
    unset($field);

    $diamondKeys = atm_diamond_field_keys();
    $jewelleryKeys = atm_jewellery_field_keys();
    $rudrakshaKeys = atm_rudraksha_field_keys();
    foreach ($fields as $key => &$field) {
        if ($type === 'D') {
            $field['display'] = in_array($key, array_merge(atm_common_field_keys(), $diamondKeys), true) ? 'block' : 'none';
            if ($key === 'stoneName') $field['display'] = 'none';
        } elseif ($type === 'J') {
            $field['display'] = in_array($key, atm_builder_field_keys('J'), true) ? 'block' : 'none';
        } elseif ($type === 'R') {
            $field['display'] = in_array($key, array_merge(['reportNo', 'date', 'weight', 'shapeCut', 'dimension', 'colour', 'issuedTo'], $rudrakshaKeys), true) ? 'block' : 'none';
        } elseif (in_array($key, array_merge($diamondKeys, $jewelleryKeys, $rudrakshaKeys), true)) {
            $field['display'] = 'none';
        }
    }
    unset($field);

    if ($type === 'J') {
        $layout = [
            'reportNo' => ['x' => 90, 'y' => 132, 'w' => 520, 'h' => 30],
            'date' => ['x' => 90, 'y' => 164, 'w' => 520, 'h' => 30],
            'description' => ['x' => 90, 'y' => 196, 'w' => 560, 'h' => 36],
            'weight' => ['x' => 90, 'y' => 236, 'w' => 520, 'h' => 30],
            'diamondWeight' => ['x' => 90, 'y' => 268, 'w' => 520, 'h' => 30],
            'face' => ['x' => 90, 'y' => 300, 'w' => 520, 'h' => 30],
            'shapeCut' => ['x' => 90, 'y' => 332, 'w' => 520, 'h' => 30],
            'colour' => ['x' => 90, 'y' => 364, 'w' => 520, 'h' => 30],
            'clarity' => ['x' => 90, 'y' => 396, 'w' => 520, 'h' => 30],
            'cutGrade' => ['x' => 90, 'y' => 428, 'w' => 520, 'h' => 30],
            'stoneName' => ['x' => 90, 'y' => 460, 'w' => 520, 'h' => 30],
            'remarks' => ['x' => 90, 'y' => 492, 'w' => 560, 'h' => 52],
            'issuedTo' => ['x' => 90, 'y' => 548, 'w' => 560, 'h' => 34],
        ];
        foreach ($layout as $key => $position) {
            if (!isset($fields[$key])) continue;
            $fields[$key] = array_replace($fields[$key], $position);
        }
    }

    return $fields;
}

function a4_default_settings($type = 'S')
{
    $type = atm_report_type($type);
    return [
        'orientation' => 'landscape',
        'backgroundImage' => '',
        'fontFamily' => 'Arial',
        'fontSize' => 14,
        'fontColor' => '#000000',
        'labelWidth' => 170,
        'fields' => a4_default_fields($type),
        'stoneImage' => ['display' => 'block', 'x' => 770, 'y' => 245, 'w' => 185, 'h' => 140],
        'qrCode' => ['display' => 'block', 'x' => 965, 'y' => 582, 'w' => 72, 'h' => 72],
        'additionalImages' => [],
    ];
}

function a4_builtin_fonts()
{
    return ['Arial', 'Arial Nova Cond Light', 'Calibri', 'Times New Roman', 'Verdana'];
}

function a4_custom_fonts_file()
{
    return atm_user_file('report-fonts.json');
}

function a4_custom_fonts()
{
    $fonts = atm_read_json(a4_custom_fonts_file(), []);
    return is_array($fonts) ? array_values(array_filter($fonts, function ($font) {
        return is_array($font) && !empty($font['family']) && !empty($font['src']) && atm_builder_asset_path($font['src']) !== '';
    })) : [];
}

function a4_font_options()
{
    $options = [];
    foreach (a4_builtin_fonts() as $font) {
        $options[] = ['family' => $font, 'label' => $font, 'custom' => false, 'src' => ''];
    }
    foreach (a4_custom_fonts() as $font) {
        $options[] = [
            'family' => (string) $font['family'],
            'label' => (string) ($font['label'] ?? $font['family']),
            'custom' => true,
            'src' => (string) $font['src'],
        ];
    }
    return $options;
}

function a4_font_family_allowed($family)
{
    $family = trim((string) $family);
    if (in_array($family, a4_builtin_fonts(), true)) {
        return true;
    }
    foreach (a4_custom_fonts() as $font) {
        if (($font['family'] ?? '') === $family) {
            return true;
        }
    }
    return false;
}

function a4_custom_font_path($family)
{
    foreach (a4_custom_fonts() as $font) {
        if (($font['family'] ?? '') === $family) {
            return atm_builder_asset_path($font['src'] ?? '');
        }
    }
    return '';
}

function a4_font_face_css()
{
    $css = '';
    foreach (a4_custom_fonts() as $font) {
        $family = preg_replace('/[^A-Za-z0-9 _.-]/', '', (string) $font['family']);
        $src = (string) ($font['src'] ?? '');
        $path = atm_builder_asset_path($src);
        if ($family === '' || $path === '') {
            continue;
        }
        $css .= "@font-face{font-family:'" . str_replace("'", "\\'", $family) . "';src:url('" . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . "?v=" . filemtime($path) . "') format('truetype');font-weight:300 700;font-style:normal;font-display:swap}\n";
    }
    return $css;
}

function a4_settings_file($type = 'S')
{
    $type = atm_report_type($type);
    if (preg_match('/^CS([0-9]+)$/', $type, $match)) {
        return atm_user_file('a4-report-settings-colour-stone-type-' . (int) $match[1] . '.json');
    }
    if ($type === 'D') return atm_user_file('a4-report-settings-diamond.json');
    if ($type === 'J') return atm_user_file('a4-report-settings-jewellery.json');
    if ($type === 'R') return atm_user_file('a4-report-settings-rudraksha.json');
    return atm_user_file('a4-report-settings.json');
}

function a4_read_settings($type = 'S')
{
    $type = atm_report_type($type);
    $defaults = a4_default_settings($type);
    $saved = atm_read_json(a4_settings_file($type), []);
    if (!is_array($saved)) {
        return $defaults;
    }

    $settings = array_replace_recursive($defaults, $saved);
    $settings['orientation'] = isset($settings['orientation']) && $settings['orientation'] === 'portrait' ? 'portrait' : 'landscape';
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
            'fontFamily' => true,
            'labelWidth' => true,
            'labelAlign' => true,
            'valueAlign' => true,
            'x' => true,
            'y' => true,
            'w' => true,
            'h' => true,
        ]));
        $normalizedFields[$key]['valueType'] = $fallback['valueType'] ?? '';
    }
    $settings['fields'] = $normalizedFields;
    if ($isLegacyFieldLayout) {
        $settings['stoneImage'] = $defaults['stoneImage'];
        $settings['qrCode'] = $defaults['qrCode'];
    }
    $baseWidth = $settings['orientation'] === 'portrait' ? 794 : 1122;
    $baseHeight = $settings['orientation'] === 'portrait' ? 1122 : 794;
    $settings['additionalImages'] = atm_normalize_additional_images($settings['additionalImages'] ?? [], $baseWidth, $baseHeight, 120, 80);

    return $settings;
}

function a4_background_path($settings)
{
    $image = isset($settings['backgroundImage']) ? trim((string) $settings['backgroundImage']) : '';
    if ($image === '') {
        return '';
    }
    $path = __DIR__ . '/' . $image;
    return is_file($path) ? $path : '';
}

function a4_background_url($settings)
{
    $image = isset($settings['backgroundImage']) ? trim((string) $settings['backgroundImage']) : '';
    if ($image === '') {
        return '';
    }
    $path = __DIR__ . '/' . $image;
    if (!is_file($path)) {
        return '';
    }
    return htmlspecialchars($image) . '?v=' . (is_file($path) ? filemtime($path) : time());
}
?>
