<?php
require_once 'a4_config.php';

function a4_load_image_resource($filename)
{
    if (!function_exists('imagecreatefromstring')) return false;
    if (!is_file($filename)) return false;
    $contents = @file_get_contents($filename);
    return $contents === false ? false : @imagecreatefromstring($contents);
}

function a4_font_files($family)
{
    $bundledFontsDir = __DIR__ . '/assets/vendor/mpdf/mpdf/ttfonts';
    $arialNovaCondLight = __DIR__ . '/assets/fonts/ArialNovaCondLight.ttf';
    $customFont = a4_custom_font_path($family);
    if ($customFont !== '') {
        return [$customFont, $customFont];
    }
    $fonts = [
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

    $choice = isset($fonts[$family]) ? $family : 'Arial';
    $regular = '';
    foreach ($fonts[$choice]['regular'] as $candidate) {
        if (is_file($candidate)) {
            $regular = $candidate;
            break;
        }
    }
    if ($regular === '') {
        $regular = $bundledFontsDir . '/DejaVuSans.ttf';
    }

    $bold = '';
    foreach ($fonts[$choice]['bold'] as $candidate) {
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

function a4_text_width($fontSize, $fontFile, $text)
{
    if (!is_file($fontFile)) return strlen((string) $text) * $fontSize * 0.55;
    if (!function_exists('imagettfbbox')) return strlen((string) $text) * $fontSize * 0.55;
    $box = imagettfbbox($fontSize, 0, $fontFile, (string) $text);
    return abs($box[2] - $box[0]);
}

function a4_fit_text($text, $fontSize, $fontFile, $maxWidth)
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '' || a4_text_width($fontSize, $fontFile, $text) <= $maxWidth) return $text;

    while (strlen($text) > 1 && a4_text_width($fontSize, $fontFile, $text . '...') > $maxWidth) {
        $text = substr($text, 0, -1);
    }

    return rtrim($text) . '...';
}

function a4_wrap_text_lines($text, $fontSize, $fontFile, $maxWidth, $maxLines)
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '') return [];
    $maxLines = max(1, (int) $maxLines);
    $words = preg_split('/\s+/', $text);
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (a4_text_width($fontSize, $fontFile, $candidate) <= $maxWidth) {
            $line = $candidate;
            continue;
        }
        if ($line !== '') {
            $lines[] = $line;
            if (count($lines) >= $maxLines) return $lines;
            $line = '';
        }
        while ($word !== '' && a4_text_width($fontSize, $fontFile, $word) > $maxWidth) {
            $chunk = '';
            while ($word !== '') {
                $next = $chunk . substr($word, 0, 1);
                if ($chunk !== '' && a4_text_width($fontSize, $fontFile, $next) > $maxWidth) break;
                $chunk = $next;
                $word = substr($word, 1);
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

function a4_align_offset($align, $textWidth, $boxWidth)
{
    $align = $align === 'center' || $align === 'right' ? $align : 'left';
    if ($align === 'right') return max(0, $boxWidth - $textWidth);
    if ($align === 'center') return max(0, ($boxWidth - $textWidth) / 2);
    return 0;
}

function a4_draw_ttf_text($canvas, $fontSize, $x, $y, $color, $fontFile, $text, $bold = false)
{
    imagettftext($canvas, $fontSize, 0, $x, $y, $color, $fontFile, $text);
    if ($bold) {
        $offset = max(1, (int) round($fontSize * 0.04));
        imagettftext($canvas, $fontSize, 0, $x + $offset, $y, $color, $fontFile, $text);
    }
}

function a4_hex_color($canvas, $hex)
{
    $hex = is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '#000000';
    return imagecolorallocate(
        $canvas,
        hexdec(substr($hex, 1, 2)),
        hexdec(substr($hex, 3, 2)),
        hexdec(substr($hex, 5, 2))
    );
}

function a4_truthy_tick($value)
{
    if (function_exists('atm_truthy_tick_value')) {
        return atm_truthy_tick_value($value);
    }
    if (is_numeric($value)) return (float) $value > 0;
    return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on', 'checked', 'tick'], true);
}

function a4_symbol_font_file()
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

function a4_pick_stone_file($record)
{
    $recordUserId = isset($record['user_id']) ? (int) $record['user_id'] : auth_current_user_id();
    $certiNo = isset($record['certi_no']) ? (string) $record['certi_no'] : '';
    if (isset($GLOBALS['conn'])) {
        return atm_branch_stone_path_for_user($GLOBALS['conn'], $recordUserId, $certiNo);
    }
    $legacy = __DIR__ . '/user_data/user_' . $recordUserId . '/st_images/' . $certiNo . '.jpg';
    return is_file($legacy) ? $legacy : '';
}

function a4_draw_text_field($canvas, $field, $record, $settings, $scaleX, $scaleY, $fontFile, $boldFont, $textColor)
{
    if (!isset($field['display']) || $field['display'] === 'none') return;

    $x = (int) round(((float) ($field['x'] ?? 0)) * $scaleX);
    $y = (int) round(((float) ($field['y'] ?? 0)) * $scaleY);
    $w = (int) round(((float) ($field['w'] ?? 250)) * $scaleX);
    $h = (int) round(((float) ($field['h'] ?? 34)) * $scaleY);
    if ($w <= 0 || $h <= 0) return;

    $fieldFontSize = isset($field['fontSize']) && $field['fontSize'] !== null && $field['fontSize'] !== ''
        ? (float) $field['fontSize']
        : (float) ($settings['fontSize'] ?? 15);
    $fontSize = max(8, (int) round($fieldFontSize * $scaleY));
    $showLabel = !isset($field['showLabel']) || $field['showLabel'] !== 'none';
    $labelWidth = $showLabel ? max(0, (int) round(((float) ($field['labelWidth'] ?? $settings['labelWidth'] ?? 140)) * $scaleX)) : 0;
    $gap = $showLabel ? max(6, (int) round(12 * $scaleX)) : 0;
    $valueWidth = max(10, $w - $labelWidth - $gap);
    $baseline = $y + min($h - 4, max($fontSize + 2, (int) round($fontSize * 1.25)));

    $label = isset($field['label']) ? (string) $field['label'] : '';
    $column = isset($field['column']) ? (string) $field['column'] : '';
    $value = isset($record[$column]) ? (string) $record[$column] : '';
    $isTickField = (($field['valueType'] ?? '') === 'tick');
    if ($isTickField) {
        if (!a4_truthy_tick($value)) return;
        $value = '✓';
        $value = html_entity_decode('&#10003;', ENT_QUOTES, 'UTF-8');
        $manualTickSize = isset($field['fontSize']) ? (int) round((float) $field['fontSize']) : 0;
        $tickFitSize = max(6, (int) round(min($w, $h) * 0.85));
        $fontSize = $manualTickSize > 0 ? min($manualTickSize, $tickFitSize) : $tickFitSize;
        $baseline = $y + min($h, max($fontSize, (int) round(($h + $fontSize) / 2)));
        $showLabel = false;
        $labelWidth = 0;
        $gap = 0;
        $valueWidth = max(10, $w);
    }
    if (trim($value) === '') return;
    $labelAlign = isset($field['labelAlign']) ? (string) $field['labelAlign'] : 'left';
    $valueAlign = isset($field['valueAlign']) ? (string) $field['valueAlign'] : 'left';
    $showColon = $showLabel && (!isset($field['showColon']) || $field['showColon'] !== 'none');

    $fieldTextColor = isset($field['fontColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $field['fontColor'])
        ? a4_hex_color($canvas, $field['fontColor'])
        : $textColor;
    $fieldFamily = isset($field['fontFamily']) ? trim((string) $field['fontFamily']) : '';
    if ($fieldFamily !== '' && a4_font_family_allowed($fieldFamily)) {
        [$fontFile, $boldFont] = a4_font_files($fieldFamily);
    }

    if (!function_exists('imagettftext')) {
        $gdFontSize = max(2, min(5, (int) round($fontSize / 4)));
        if ($showLabel) {
            $fallbackLabel = substr($label, 0, 35);
            $labelOffset = a4_align_offset($labelAlign, imagefontwidth($gdFontSize) * strlen($fallbackLabel), max(10, $labelWidth - 4));
            imagestring($canvas, $gdFontSize, $x + (int) round($labelOffset), max(0, $y + 2), $fallbackLabel, $fieldTextColor);
        }
        $fallbackValue = substr(($showColon ? ': ' : '') . $value, 0, 60);
        $valueOffset = a4_align_offset($valueAlign, imagefontwidth($gdFontSize) * strlen($fallbackValue), $valueWidth);
        imagestring($canvas, $gdFontSize, $x + $labelWidth + $gap + (int) round($valueOffset), max(0, $y + 2), $fallbackValue, $fieldTextColor);
        return;
    }

    $labelIsBold = isset($field['labelFontWeight']) && $field['labelFontWeight'] === 'bold';
    $labelFont = $labelIsBold ? $boldFont : $fontFile;
    if ($showLabel && is_file($labelFont) && function_exists('imagettftext')) {
        $label = a4_fit_text($label, $fontSize, $labelFont, max(10, $labelWidth - 4));
        $labelOffset = a4_align_offset($labelAlign, a4_text_width($fontSize, $labelFont, $label), max(10, $labelWidth - 4));
        a4_draw_ttf_text($canvas, $fontSize, $x + (int) round($labelOffset), $baseline, $fieldTextColor, $labelFont, $label, $labelIsBold);
    }
    $valueIsBold = isset($field['fontWeight']) && $field['fontWeight'] === 'bold';
    $valueFont = $isTickField ? a4_symbol_font_file() : ($valueIsBold ? $boldFont : $fontFile);
    if ($valueFont === '') {
        $valueFont = $fontFile;
    }
    if (is_file($valueFont) && function_exists('imagettftext')) {
        $value = ($showColon ? ': ' : '') . $value;
        $lineHeight = max($fontSize + 2, (int) round($fontSize * 1.25));
        $maxLines = $isTickField ? 1 : max(1, (int) floor((($y + $h - 2) - $baseline) / $lineHeight) + 1);
        $lines = $isTickField ? [$value] : a4_wrap_text_lines($value, $fontSize, $valueFont, $valueWidth, $maxLines);
        foreach ($lines as $lineIndex => $lineText) {
            $lineBaseline = $baseline + ($lineIndex * $lineHeight);
            if ($lineBaseline > $y + $h) break;
            $valueOffset = a4_align_offset($valueAlign, a4_text_width($fontSize, $valueFont, $lineText), $valueWidth);
            a4_draw_ttf_text($canvas, $fontSize, $x + $labelWidth + $gap + (int) round($valueOffset), $lineBaseline, $fieldTextColor, $valueFont, $lineText, !$isTickField && $valueIsBold);
        }
    }
}

function a4_copy_box($canvas, $source, $box, $scaleX, $scaleY)
{
    if (!$source || !isset($box['display']) || $box['display'] === 'none') return;

    $x = (int) round(((float) ($box['x'] ?? 0)) * $scaleX);
    $y = (int) round(((float) ($box['y'] ?? 0)) * $scaleY);
    $w = (int) round(((float) ($box['w'] ?? 100)) * $scaleX);
    $h = (int) round(((float) ($box['h'] ?? 100)) * $scaleY);
    if ($w <= 0 || $h <= 0) return;

    imagecopyresampled($canvas, $source, $x, $y, 0, 0, $w, $h, imagesx($source), imagesy($source));
}

function a4_draw_additional_images($canvas, $settings, $scaleX, $scaleY)
{
    if (empty($settings['additionalImages']) || !is_array($settings['additionalImages'])) {
        return;
    }
    foreach ($settings['additionalImages'] as $image) {
        if (!is_array($image) || ($image['display'] ?? 'block') === 'none') {
            continue;
        }
        $path = atm_builder_asset_path($image['src'] ?? '');
        $source = $path ? a4_load_image_resource($path) : false;
        if (!$source) {
            continue;
        }
        a4_copy_box($canvas, $source, $image, $scaleX, $scaleY);
        imagedestroy($source);
    }
}

function a4_build_report_canvas($record, $settings = null)
{
    $settings = is_array($settings) ? $settings : a4_read_settings();
    $canvas = a4_load_image_resource(a4_background_path($settings));
    if (!$canvas) return false;

    $width = imagesx($canvas);
    $height = imagesy($canvas);
    $orientation = isset($settings['orientation']) && $settings['orientation'] === 'portrait' ? 'portrait' : 'landscape';
    $baseWidth = isset($settings['pageBaseWidth']) ? max(1, (float) $settings['pageBaseWidth']) : ($orientation === 'portrait' ? 794 : 1122);
    $baseHeight = isset($settings['pageBaseHeight']) ? max(1, (float) $settings['pageBaseHeight']) : ($orientation === 'portrait' ? 1122 : 794);
    $scaleX = $width / $baseWidth;
    $scaleY = $height / $baseHeight;

    [$fontFile, $boldFont] = a4_font_files($settings['fontFamily'] ?? 'Arial');
    $textColor = a4_hex_color($canvas, $settings['fontColor'] ?? '#000000');

    if (isset($settings['fields']) && is_array($settings['fields'])) {
        foreach ($settings['fields'] as $field) {
            if (is_array($field)) {
                a4_draw_text_field($canvas, $field, $record, $settings, $scaleX, $scaleY, $fontFile, $boldFont, $textColor);
            }
        }
    }

    if (isset($settings['stoneImage']) && is_array($settings['stoneImage'])) {
        $stoneFile = a4_pick_stone_file($record);
        $stone = $stoneFile ? a4_load_image_resource($stoneFile) : false;
        if ($stone) {
            a4_copy_box($canvas, $stone, $settings['stoneImage'], $scaleX, $scaleY);
            imagedestroy($stone);
        }
    }

    a4_draw_additional_images($canvas, $settings, $scaleX, $scaleY);

    if (isset($settings['qrCode']) && is_array($settings['qrCode']) && $settings['qrCode']['display'] !== 'none') {
        $qrSettings = isset($settings['qrSettings']) && is_array($settings['qrSettings']) ? $settings['qrSettings'] : atm_default_qr_settings();
        $verifyUrl = atm_build_qr_url($qrSettings['urlPattern'] ?? '', $record);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($verifyUrl);
        $qrData = @file_get_contents($qrUrl);
        $qr = $qrData ? @imagecreatefromstring($qrData) : false;
        if ($qr) {
            a4_copy_box($canvas, $qr, $settings['qrCode'], $scaleX, $scaleY);
            imagedestroy($qr);
        }
    }

    return $canvas;
}

function a4_render_report_page_file($record, $settings = null, $targetPath = null)
{
    $canvas = a4_build_report_canvas($record, $settings);
    if (!$canvas) return '';

    $targetPath = $targetPath ?: atm_user_file('report-a4-rendered.jpg');
    imagejpeg($canvas, $targetPath, 92);
    imagedestroy($canvas);

    return $targetPath;
}

function a4_render_report_page($record, $settings = null)
{
    $canvas = a4_build_report_canvas($record, $settings);
    if (!$canvas) return '';

    ob_start();
    imagejpeg($canvas, null, 92);
    $jpeg = ob_get_clean();
    imagedestroy($canvas);
    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
}
?>
