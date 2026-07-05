<?php

function atm_load_image_resource($filename)
{
    if (!function_exists('imagecreatefromstring')) return false;
    if (!is_file($filename)) return false;
    $contents = @file_get_contents($filename);
    return $contents === false ? false : @imagecreatefromstring($contents);
}

function atm_text_width($fontSize, $fontFile, $text)
{
    if (!is_file($fontFile)) return strlen((string) $text) * $fontSize * 0.55;
    if (!function_exists('imagettfbbox')) return strlen((string) $text) * $fontSize * 0.55;
    $box = imagettfbbox($fontSize, 0, $fontFile, $text);
    return abs($box[2] - $box[0]);
}

function atm_draw_ttf_text($canvas, $fontSize, $x, $y, $color, $fontFile, $text, $bold = false)
{
    imagettftext($canvas, $fontSize, 0, $x, $y, $color, $fontFile, $text);
    if ($bold) {
        $offset = max(1, (int) round($fontSize * 0.04));
        imagettftext($canvas, $fontSize, 0, $x + $offset, $y, $color, $fontFile, $text);
    }
}

function atm_card_font_files($family)
{
    $bundledFontsDir = __DIR__ . '/assets/vendor/mpdf/mpdf/ttfonts';
    $arialNovaCondLight = __DIR__ . '/assets/fonts/ArialNovaCondLight.ttf';
    $fontFiles = [
        'Arial' => [
            'regular' => ['C:/Windows/Fonts/arial.ttf', $bundledFontsDir . '/DejaVuSans.ttf'],
            'bold' => ['C:/Windows/Fonts/arialbd.ttf', $bundledFontsDir . '/DejaVuSans-Bold.ttf'],
        ],
        'Arial Nova Cond Light' => [
            'regular' => [
                $arialNovaCondLight,
                'C:/Windows/Fonts/ARIALN.TTF',
                'C:/Windows/Fonts/arial.ttf',
                $bundledFontsDir . '/DejaVuSansCondensed.ttf'
            ],
            'bold' => [
                $arialNovaCondLight,
                'C:/Windows/Fonts/ARIALN.TTF',
                'C:/Windows/Fonts/ARIALNB.TTF',
                'C:/Windows/Fonts/arialbd.ttf',
                $bundledFontsDir . '/DejaVuSansCondensed-Bold.ttf'
            ],
        ],
        'Calibri' => [
            'regular' => ['C:/Windows/Fonts/calibri.ttf', $bundledFontsDir . '/DejaVuSans.ttf'],
            'bold' => ['C:/Windows/Fonts/calibrib.ttf', $bundledFontsDir . '/DejaVuSans-Bold.ttf'],
        ],
        'Times New Roman' => [
            'regular' => ['C:/Windows/Fonts/times.ttf', $bundledFontsDir . '/DejaVuSerif.ttf'],
            'bold' => ['C:/Windows/Fonts/timesbd.ttf', $bundledFontsDir . '/DejaVuSerif-Bold.ttf'],
        ],
        'Verdana' => [
            'regular' => ['C:/Windows/Fonts/verdana.ttf', $bundledFontsDir . '/DejaVuSansCondensed.ttf'],
            'bold' => ['C:/Windows/Fonts/verdanab.ttf', $bundledFontsDir . '/DejaVuSansCondensed-Bold.ttf'],
        ],
    ];

    $fontChoice = isset($fontFiles[$family]) ? $family : 'Arial';
    $regular = '';
    foreach ($fontFiles[$fontChoice]['regular'] as $candidate) {
        if (is_file($candidate)) {
            $regular = $candidate;
            break;
        }
    }
    if ($regular === '') {
        $regular = $bundledFontsDir . '/DejaVuSans.ttf';
    }

    $bold = '';
    foreach ($fontFiles[$fontChoice]['bold'] as $candidate) {
        if (is_file($candidate)) {
            $bold = $candidate;
            break;
        }
    }
    if ($bold === '' || !is_file($bold)) {
        $bold = $regular;
    }

    return [$regular, $bold];
}

function atm_card_symbol_font_file()
{
    $bundledFontsDir = __DIR__ . '/assets/vendor/mpdf/mpdf/ttfonts';
    $candidates = [
        'C:/Windows/Fonts/seguisym.ttf',
        'C:/Windows/Fonts/ARIALUNI.TTF',
        $bundledFontsDir . '/DejaVuSans.ttf',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return '';
}

function atm_fit_text($text, $fontSize, $fontFile, $maxWidth)
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if (atm_text_width($fontSize, $fontFile, $text) <= $maxWidth) return $text;
    while (mb_strlen($text) > 1 && atm_text_width($fontSize, $fontFile, $text . '…') > $maxWidth) {
        $text = mb_substr($text, 0, -1);
    }
    return rtrim($text) . '…';
}

function atm_wrap_text_lines($text, $fontSize, $fontFile, $maxWidth, $maxLines)
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '') return [];
    $maxLines = max(1, (int) $maxLines);
    $words = preg_split('/\s+/', $text);
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (atm_text_width($fontSize, $fontFile, $candidate) <= $maxWidth) {
            $line = $candidate;
            continue;
        }
        if ($line !== '') {
            $lines[] = $line;
            if (count($lines) >= $maxLines) return $lines;
            $line = '';
        }
        while ($word !== '' && atm_text_width($fontSize, $fontFile, $word) > $maxWidth) {
            $chunk = '';
            while ($word !== '') {
                $next = $chunk . mb_substr($word, 0, 1);
                if ($chunk !== '' && atm_text_width($fontSize, $fontFile, $next) > $maxWidth) break;
                $chunk = $next;
                $word = mb_substr($word, 1);
            }
            if ($chunk === '') break;
            $lines[] = $chunk;
            if (count($lines) >= $maxLines) return $lines;
        }
        $line = $word;
    }
    if ($line !== '' && count($lines) < $maxLines) {
        $lines[] = $line;
    }
    return $lines;
}

function atm_align_offset($align, $textWidth, $boxWidth)
{
    $align = $align === 'center' || $align === 'right' ? $align : 'left';
    if ($align === 'right') return max(0, $boxWidth - $textWidth);
    if ($align === 'center') return max(0, ($boxWidth - $textWidth) / 2);
    return 0;
}

function atm_card_truthy_tick($value)
{
    if (function_exists('atm_truthy_tick_value')) {
        return atm_truthy_tick_value($value);
    }
    if (is_numeric($value)) return (float) $value > 0;
    return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on', 'checked', 'tick'], true);
}

function atm_render_front_card($record, $positions, $fieldSettings, $fieldMap, $frontFile = null, $qrSettings = null)
{
    $baseWidth = 321.25984252;
    $baseHeight = 204.09448819;
    $frontFile = $frontFile && is_file($frontFile) ? $frontFile : '';
    if ($frontFile === '') return '';
    $canvas = atm_load_image_resource($frontFile);
    if (!$canvas) return '';

    $width = imagesx($canvas);
    $height = imagesy($canvas);
    $scaleX = $width / $baseWidth;
    $scaleY = $height / $baseHeight;
    $fontChoice = isset($positions['table']['fontFamily']) ? $positions['table']['fontFamily'] : 'Arial';
    [$fontFile, $boldFont] = atm_card_font_files($fontChoice);
    $fontColor = isset($positions['table']['fontColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $positions['table']['fontColor']) ? $positions['table']['fontColor'] : '#000000';
    $textColor = imagecolorallocate($canvas, hexdec(substr($fontColor, 1, 2)), hexdec(substr($fontColor, 3, 2)), hexdec(substr($fontColor, 5, 2)));

    if ($positions['table']['display'] !== 'none' && is_file($fontFile)) {
        foreach ($fieldMap as $settingKey => $definition) {
            $field = isset($positions['fields'][$settingKey]) && is_array($positions['fields'][$settingKey])
                ? $positions['fields'][$settingKey]
                : null;
            if (!$field || (isset($field['display']) && $field['display'] === 'none')) continue;

            $columnName = isset($field['column']) && is_string($field['column']) && $field['column'] !== ''
                ? $field['column']
                : $definition[1];
            $rawValue = isset($record[$columnName]) ? trim((string) $record[$columnName]) : '';
            $valueType = $field['valueType'] ?? ($definition[2] ?? '');
            if ($valueType === 'tick') {
                if (!atm_card_truthy_tick($rawValue)) continue;
                $rawValue = '✓';
                $rawValue = html_entity_decode('&#10003;', ENT_QUOTES, 'UTF-8');
                $field['showLabel'] = 'none';
            }
            if ($rawValue === '') continue;

            $fieldFontSize = isset($field['fontSize']) && $field['fontSize'] !== null && $field['fontSize'] !== ''
                ? (float) $field['fontSize']
                : (float) ($positions['table']['fontSize'] ?? 6);
            $fontSize = max(7, (int) round($fieldFontSize * $scaleY));
            $fieldColor = isset($field['fontColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $field['fontColor'])
                ? $field['fontColor']
                : $fontColor;
            $fieldTextColor = imagecolorallocate($canvas, hexdec(substr($fieldColor, 1, 2)), hexdec(substr($fieldColor, 3, 2)), hexdec(substr($fieldColor, 5, 2)));
            $left = (int) round((float) $field['x'] * $scaleX);
            $top = (int) round((float) $field['y'] * $scaleY);
            $areaWidth = (int) round((float) $field['w'] * $scaleX);
            $areaHeight = (int) round((float) $field['h'] * $scaleY);
            $showLabel = !isset($field['showLabel']) || $field['showLabel'] !== 'none';
            $fieldLabelWidth = $showLabel
                ? min((int) round(((float) ($field['labelWidth'] ?? $positions['table']['labelWidth'] ?? 75)) * $scaleX), max(10, $areaWidth - 10))
                : 0;
            $gap = $showLabel ? (int) round(4 * $scaleX) : 0;
            $valueWidth = max(10, $areaWidth - $fieldLabelWidth - $gap);
            $baseline = $top + min($areaHeight, $fontSize + 2);
            $labelAlign = isset($field['labelAlign']) ? (string) $field['labelAlign'] : 'left';
            $valueAlign = isset($field['valueAlign']) ? (string) $field['valueAlign'] : 'left';
            $showColon = $showLabel && (!isset($field['showColon']) || $field['showColon'] !== 'none');

            if (!function_exists('imagettftext')) {
                $gdFontSize = max(1, min(5, (int) round($fontSize / 4)));
                if ($showLabel) {
                    $fallbackLabel = substr((string) ($field['label'] ?? $definition[0]), 0, 22);
                    $labelOffset = atm_align_offset($labelAlign, imagefontwidth($gdFontSize) * strlen($fallbackLabel), max(10, $fieldLabelWidth - 4));
                    imagestring($canvas, $gdFontSize, $left + (int) round($labelOffset), max(0, $top + 1), $fallbackLabel, $fieldTextColor);
                }
                $fallbackValue = substr(($showColon ? ': ' : '') . $rawValue, 0, 35);
                $valueOffset = atm_align_offset($valueAlign, imagefontwidth($gdFontSize) * strlen($fallbackValue), $valueWidth);
                imagestring($canvas, $gdFontSize, $left + $fieldLabelWidth + $gap + (int) round($valueOffset), max(0, $top + 1), $fallbackValue, $fieldTextColor);
                continue;
            }

            if ($showLabel) {
                $labelText = trim((string) ($field['label'] ?? $definition[0]));
                $labelIsBold = isset($field['labelFontWeight']) && $field['labelFontWeight'] === 'bold';
                $labelFont = $labelIsBold ? $boldFont : $fontFile;
                $label = atm_fit_text($labelText, $fontSize, $labelFont, $fieldLabelWidth - 4);
                $labelOffset = atm_align_offset($labelAlign, atm_text_width($fontSize, $labelFont, $label), max(10, $fieldLabelWidth - 4));
                atm_draw_ttf_text($canvas, $fontSize, $left + (int) round($labelOffset), $baseline, $fieldTextColor, $labelFont, $label, $labelIsBold);
            }
            $valuePrefix = $showColon ? ': ' : '';
            $valueIsBold = isset($field['fontWeight']) && $field['fontWeight'] === 'bold';
            $valueFont = $valueType === 'tick' ? atm_card_symbol_font_file() : ($valueIsBold ? $boldFont : $fontFile);
            if ($valueFont === '') {
                $valueFont = $fontFile;
            }
            $value = $valuePrefix . $rawValue;
            $lineHeight = max($fontSize + 1, (int) round($fontSize * 1.2));
            $maxLines = $valueType === 'tick' ? 1 : max(1, (int) floor((($top + $areaHeight - 1) - $baseline) / $lineHeight) + 1);
            $lines = $valueType === 'tick' ? [$value] : atm_wrap_text_lines($value, $fontSize, $valueFont, $valueWidth, $maxLines);
            foreach ($lines as $lineIndex => $lineText) {
                $lineBaseline = $baseline + ($lineIndex * $lineHeight);
                if ($lineBaseline > $top + $areaHeight) break;
                $valueOffset = atm_align_offset($valueAlign, atm_text_width($fontSize, $valueFont, $lineText), $valueWidth);
                atm_draw_ttf_text($canvas, $fontSize, $left + $fieldLabelWidth + $gap + (int) round($valueOffset), $lineBaseline, $fieldTextColor, $valueFont, $lineText, $valueType !== 'tick' && $valueIsBold);
            }
        }
    }

    if ($positions['gemstone']['display'] !== 'none') {
        $recordUserId = isset($record['user_id']) ? (int) $record['user_id'] : auth_current_user_id();
        $stoneFile = isset($GLOBALS['conn'])
            ? atm_branch_stone_path_for_user($GLOBALS['conn'], $recordUserId, $record['certi_no'] ?? '')
            : (__DIR__ . '/user_data/user_' . $recordUserId . '/st_images/' . ($record['certi_no'] ?? '') . '.jpg');
        $stone = atm_load_image_resource($stoneFile);
        if ($stone) {
            $x = (int) round($positions['gemstone']['left'] * $scaleX);
            $y = (int) round($positions['gemstone']['top'] * $scaleY);
            $w = (int) round($positions['gemstone']['width'] * $scaleX);
            $h = (int) round($positions['gemstone']['height'] * $scaleY);
            imagecopyresampled($canvas, $stone, $x, $y, 0, 0, $w, $h, imagesx($stone), imagesy($stone));
            imagedestroy($stone);
        }
    }

    if (!empty($positions['additionalImages']) && is_array($positions['additionalImages'])) {
        foreach ($positions['additionalImages'] as $imageBox) {
            if (!is_array($imageBox) || ($imageBox['display'] ?? 'block') === 'none') {
                continue;
            }
            $imagePath = atm_builder_asset_path($imageBox['src'] ?? '');
            $extra = $imagePath ? atm_load_image_resource($imagePath) : false;
            if (!$extra) {
                continue;
            }
            $x = (int) round(((float) ($imageBox['x'] ?? 0)) * $scaleX);
            $y = (int) round(((float) ($imageBox['y'] ?? 0)) * $scaleY);
            $w = (int) round(((float) ($imageBox['w'] ?? 40)) * $scaleX);
            $h = (int) round(((float) ($imageBox['h'] ?? 40)) * $scaleY);
            if ($w > 0 && $h > 0) {
                imagecopyresampled($canvas, $extra, $x, $y, 0, 0, $w, $h, imagesx($extra), imagesy($extra));
            }
            imagedestroy($extra);
        }
    }

    if ($positions['qrcode']['display'] !== 'none') {
        $qrSettings = is_array($qrSettings) ? $qrSettings : atm_default_qr_settings();
        $verifyUrl = atm_build_qr_url($qrSettings['urlPattern'] ?? '', $record);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($verifyUrl);
        $qrData = @file_get_contents($qrUrl);
        $qr = $qrData ? @imagecreatefromstring($qrData) : false;
        if ($qr) {
            $x = (int) round($positions['qrcode']['left'] * $scaleX);
            $y = (int) round($positions['qrcode']['top'] * $scaleY);
            $w = (int) round($positions['qrcode']['width'] * $scaleX);
            $h = (int) round($positions['qrcode']['height'] * $scaleY);
            imagecopyresampled($canvas, $qr, $x, $y, 0, 0, $w, $h, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }
    }

    ob_start();
    imagejpeg($canvas, null, 90);
    $jpeg = ob_get_clean();
    imagedestroy($canvas);
    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
}
