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

function a4_pick_record_image_file($record, $folder = 'st_images')
{
    $recordUserId = isset($record['user_id']) ? (int) $record['user_id'] : auth_current_user_id();
    $certiNo = isset($record['certi_no']) ? (string) $record['certi_no'] : '';
    $folder = trim((string) $folder, '/\\');
    if ($folder === '') {
        $folder = 'st_images';
    }
    if (isset($GLOBALS['conn'])) {
        return atm_branch_image_path_for_user($GLOBALS['conn'], $recordUserId, $certiNo, $folder);
    }
    $legacy = __DIR__ . '/user_data/user_' . $recordUserId . '/' . $folder . '/' . $certiNo . '.jpg';
    return is_file($legacy) ? $legacy : '';
}

function a4_pick_stone_file($record)
{
    return a4_pick_record_image_file($record, 'st_images');
}

function a4_record_value($record, $key)
{
    if (isset($record[$key])) {
        return $record[$key];
    }
    $upper = strtoupper($key);
    if (isset($record[$upper])) {
        return $record[$upper];
    }
    return '';
}

function a4_compare_condition_value($left, $operator, $right)
{
    $left = trim((string) $left);
    $right = trim((string) $right);
    $right = preg_replace('/^([\'"])(.*)\1$/', '$2', $right);
    if (is_numeric($left) && is_numeric($right)) {
        $leftValue = (float) $left;
        $rightValue = (float) $right;
    } else {
        $leftValue = strtolower($left);
        $rightValue = strtolower($right);
    }
    switch ($operator) {
        case '=':
        case '==':
            return $leftValue == $rightValue;
        case '!=':
        case '<>':
            return $leftValue != $rightValue;
        case '>':
            return $leftValue > $rightValue;
        case '<':
            return $leftValue < $rightValue;
        case '>=':
            return $leftValue >= $rightValue;
        case '<=':
            return $leftValue <= $rightValue;
    }
    return false;
}

function a4_condition_matches($condition, $record)
{
    $condition = trim((string) $condition);
    if ($condition === '') {
        return true;
    }
    $orParts = preg_split('/\s+or\s+/i', $condition);
    foreach ($orParts as $orPart) {
        $andParts = preg_split('/\s+and\s+/i', trim($orPart));
        $allMatched = true;
        foreach ($andParts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*(==|=|!=|<>|>=|<=|>|<)\s*(.+)$/', $part, $match)) {
                $allMatched = false;
                break;
            }
            if (!a4_compare_condition_value(a4_record_value($record, $match[1]), $match[2], $match[3])) {
                $allMatched = false;
                break;
            }
        }
        if ($allMatched) {
            return true;
        }
    }
    return false;
}

function a4_record_symbols($record)
{
    $symbols = [];
    $rawJson = trim((string) a4_record_value($record, 'diamond_symbols_json'));
    if ($rawJson !== '') {
        $decoded = json_decode($rawJson, true);
        if (is_array($decoded)) {
            foreach ($decoded as $symbol) {
                $symbol = trim((string) $symbol);
                if ($symbol !== '' && !in_array($symbol, $symbols, true)) {
                    $symbols[] = $symbol;
                }
                if (count($symbols) >= 3) {
                    break;
                }
            }
        }
    }
    foreach (['ws1', 'ws2', 'ws3'] as $column) {
        if (count($symbols) >= 3) {
            break;
        }
        $symbol = trim((string) a4_record_value($record, $column));
        if ($symbol !== '' && !in_array($symbol, $symbols, true)) {
            $symbols[] = $symbol;
        }
    }
    return array_slice($symbols, 0, 3);
}

function a4_pick_symbol_file($record, $symbol)
{
    $symbol = trim((string) $symbol);
    if ($symbol === '') {
        return '';
    }
    $recordUserId = isset($record['user_id']) ? (int) $record['user_id'] : auth_current_user_id();
    if (isset($GLOBALS['conn'])) {
        return atm_branch_image_path_for_user($GLOBALS['conn'], $recordUserId, $symbol, 'symbol_images', ['png', 'jpg', 'jpeg', 'PNG', 'JPG', 'JPEG']);
    }
    $safe = preg_replace('/[^0-9A-Za-z _.-]/', '', $symbol);
    foreach (['png', 'jpg', 'jpeg', 'PNG', 'JPG', 'JPEG'] as $ext) {
        $legacy = __DIR__ . '/user_data/user_' . $recordUserId . '/symbol_images/' . $safe . '.' . $ext;
        if (is_file($legacy)) {
            return $legacy;
        }
        $asset = __DIR__ . '/assets/symbol_images/' . $safe . '.' . $ext;
        if (is_file($asset)) {
            return $asset;
        }
    }
    return '';
}

function a4_draw_text_field($canvas, $field, $record, $settings, $scaleX, $scaleY, $fontFile, $boldFont, $textColor)
{
    if (!isset($field['display']) || $field['display'] === 'none') return;
    if (!a4_condition_matches($field['condition'] ?? '', $record)) return;

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

    $fieldColorHex = isset($field['fontColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $field['fontColor'])
        ? $field['fontColor']
        : '';
    $labelColorHex = isset($field['labelFontColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $field['labelFontColor'])
        ? $field['labelFontColor']
        : $fieldColorHex;
    $valueColorHex = isset($field['valueFontColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $field['valueFontColor'])
        ? $field['valueFontColor']
        : $fieldColorHex;
    $labelTextColor = $labelColorHex !== '' ? a4_hex_color($canvas, $labelColorHex) : $textColor;
    $valueTextColor = $valueColorHex !== '' ? a4_hex_color($canvas, $valueColorHex) : $textColor;
    $fieldFamily = isset($field['fontFamily']) ? trim((string) $field['fontFamily']) : '';
    if ($fieldFamily !== '' && a4_font_family_allowed($fieldFamily)) {
        [$fontFile, $boldFont] = a4_font_files($fieldFamily);
    }
    $labelFamily = isset($field['labelFontFamily']) ? trim((string) $field['labelFontFamily']) : '';
    $valueFamily = isset($field['valueFontFamily']) ? trim((string) $field['valueFontFamily']) : '';
    $labelFontFile = $fontFile;
    $labelBoldFont = $boldFont;
    $valueFontFile = $fontFile;
    $valueBoldFont = $boldFont;
    if ($labelFamily !== '' && a4_font_family_allowed($labelFamily)) {
        [$labelFontFile, $labelBoldFont] = a4_font_files($labelFamily);
    }
    if ($valueFamily !== '' && a4_font_family_allowed($valueFamily)) {
        [$valueFontFile, $valueBoldFont] = a4_font_files($valueFamily);
    }

    if (!function_exists('imagettftext')) {
        $gdFontSize = max(2, min(5, (int) round($fontSize / 4)));
        if ($showLabel) {
            $fallbackLabel = substr($label, 0, 35);
            $labelOffset = a4_align_offset($labelAlign, imagefontwidth($gdFontSize) * strlen($fallbackLabel), max(10, $labelWidth - 4));
            imagestring($canvas, $gdFontSize, $x + (int) round($labelOffset), max(0, $y + 2), $fallbackLabel, $labelTextColor);
        }
        $fallbackValue = substr(($showColon ? ': ' : '') . $value, 0, 60);
        $valueOffset = a4_align_offset($valueAlign, imagefontwidth($gdFontSize) * strlen($fallbackValue), $valueWidth);
        imagestring($canvas, $gdFontSize, $x + $labelWidth + $gap + (int) round($valueOffset), max(0, $y + 2), $fallbackValue, $valueTextColor);
        return;
    }

    $labelIsBold = isset($field['labelFontWeight']) && $field['labelFontWeight'] === 'bold';
    $labelFont = $labelIsBold ? $labelBoldFont : $labelFontFile;
    if ($showLabel && is_file($labelFont) && function_exists('imagettftext')) {
        $label = a4_fit_text($label, $fontSize, $labelFont, max(10, $labelWidth - 4));
        $labelOffset = a4_align_offset($labelAlign, a4_text_width($fontSize, $labelFont, $label), max(10, $labelWidth - 4));
        a4_draw_ttf_text($canvas, $fontSize, $x + (int) round($labelOffset), $baseline, $labelTextColor, $labelFont, $label, $labelIsBold);
    }
    $valueIsBold = isset($field['fontWeight']) && $field['fontWeight'] === 'bold';
    $valueFont = $isTickField ? a4_symbol_font_file() : ($valueIsBold ? $valueBoldFont : $valueFontFile);
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
            a4_draw_ttf_text($canvas, $fontSize, $x + $labelWidth + $gap + (int) round($valueOffset), $lineBaseline, $valueTextColor, $valueFont, $lineText, !$isTickField && $valueIsBold);
        }
    }
}

function a4_draw_additional_texts($canvas, $record, $settings, $scaleX, $scaleY, $fontFile, $boldFont)
{
    if (empty($settings['additionalTexts']) || !is_array($settings['additionalTexts'])) {
        return;
    }
    foreach ($settings['additionalTexts'] as $item) {
        if (!is_array($item) || ($item['display'] ?? 'block') === 'none') {
            continue;
        }
        if (!a4_condition_matches($item['condition'] ?? '', $record)) {
            continue;
        }
        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $x = (int) round(((float) ($item['x'] ?? 0)) * $scaleX);
        $y = (int) round(((float) ($item['y'] ?? 0)) * $scaleY);
        $w = (int) round(((float) ($item['w'] ?? 180)) * $scaleX);
        $h = (int) round(((float) ($item['h'] ?? 40)) * $scaleY);
        if ($w <= 0 || $h <= 0) {
            continue;
        }
        $fontSize = max(6, (int) round(((float) ($item['fontSize'] ?? 12)) * $scaleY));
        $family = trim((string) ($item['fontFamily'] ?? ''));
        $regular = $fontFile;
        $bold = $boldFont;
        if ($family !== '' && a4_font_family_allowed($family)) {
            [$regular, $bold] = a4_font_files($family);
        }
        $useFont = (($item['fontWeight'] ?? '') === 'bold') ? $bold : $regular;
        $color = a4_hex_color($canvas, $item['fontColor'] ?? '#000000');
        $lineHeight = max($fontSize + 2, (int) round($fontSize * 1.25));
        $maxLines = max(1, (int) floor($h / $lineHeight));
        $lines = a4_wrap_text_lines($text, $fontSize, $useFont, $w, $maxLines);
        $align = in_array(($item['align'] ?? 'left'), ['left', 'center', 'right'], true) ? $item['align'] : 'left';
        foreach ($lines as $lineIndex => $lineText) {
            $baseline = $y + $fontSize + ($lineIndex * $lineHeight);
            if ($baseline > $y + $h) break;
            $offset = a4_align_offset($align, a4_text_width($fontSize, $useFont, $lineText), $w);
            if (is_file($useFont) && function_exists('imagettftext')) {
                a4_draw_ttf_text($canvas, $fontSize, $x + (int) round($offset), $baseline, $color, $useFont, $lineText, ($item['fontWeight'] ?? '') === 'bold');
            } else {
                imagestring($canvas, 2, $x + (int) round($offset), $y + ($lineIndex * $lineHeight), $lineText, $color);
            }
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

function a4_draw_symbol_key($canvas, $record, $settings, $scaleX, $scaleY, $fontFile, $textColor)
{
    $box = isset($settings['symbolKey']) && is_array($settings['symbolKey']) ? $settings['symbolKey'] : [];
    if (($box['display'] ?? 'none') === 'none') {
        return;
    }
    $symbols = a4_record_symbols($record);
    if (!$symbols) {
        return;
    }

    $x = (int) round(((float) ($box['x'] ?? 0)) * $scaleX);
    $y = (int) round(((float) ($box['y'] ?? 0)) * $scaleY);
    $w = (int) round(((float) ($box['w'] ?? 220)) * $scaleX);
    $h = (int) round(((float) ($box['h'] ?? 120)) * $scaleY);
    if ($w <= 20 || $h <= 20) {
        return;
    }

    $fontSize = max(6, (int) round(((float) ($box['fontSize'] ?? 10)) * $scaleY));
    $lineHeight = max($fontSize + 10, (int) round($fontSize * 2.1));
    $paddingX = max(4, (int) round(8 * $scaleX));
    $paddingY = max(4, (int) round(8 * $scaleY));

    $rowY = $y + $paddingY;
    foreach ($symbols as $index => $symbol) {
        if ($rowY > $y + $h - 3) {
            break;
        }
        $number = ($index + 1) . '.';
        $numberX = $x + $paddingX + (int) round(2 * $scaleX);
        $imageX = $x + $paddingX + (int) round(28 * $scaleX);
        $nameX = $x + $paddingX + (int) round(70 * $scaleX);
        $baseline = $rowY + $fontSize;

        if (is_file($fontFile) && function_exists('imagettftext')) {
            a4_draw_ttf_text($canvas, $fontSize, $numberX, $baseline, $textColor, $fontFile, $number, false);
        } else {
            imagestring($canvas, 1, $numberX, $rowY, $number, $textColor);
        }

        $symbolFile = a4_pick_symbol_file($record, $symbol);
        $symbolImage = $symbolFile !== '' ? a4_load_image_resource($symbolFile) : false;
        if ($symbolImage) {
            $imageSize = max(10, min((int) round(24 * $scaleY), $lineHeight - 2));
            imagecopyresampled($canvas, $symbolImage, $imageX, $rowY + 2, 0, 0, $imageSize, $imageSize, imagesx($symbolImage), imagesy($symbolImage));
            imagedestroy($symbolImage);
        }

        $nameMaxWidth = max(20, $x + $w - $nameX - $paddingX);
        if (is_file($fontFile) && function_exists('imagettftext')) {
            $name = a4_fit_text((string) $symbol, $fontSize, $fontFile, $nameMaxWidth);
            a4_draw_ttf_text($canvas, $fontSize, $nameX, $baseline, $textColor, $fontFile, $name, false);
        } else {
            imagestring($canvas, 1, $nameX, $rowY, substr((string) $symbol, 0, 28), $textColor);
        }
        $rowY += $lineHeight;
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

    $recordImageBoxes = [
        'stoneImage' => 'st_images',
        'proportionImage' => 'proportion_images',
        'clarityImage' => 'clarity_images',
    ];
    foreach ($recordImageBoxes as $boxKey => $folder) {
        if (!isset($settings[$boxKey]) || !is_array($settings[$boxKey])) {
            continue;
        }
        $imageFile = a4_pick_record_image_file($record, $folder);
        $image = $imageFile ? a4_load_image_resource($imageFile) : false;
        if ($image) {
            a4_copy_box($canvas, $image, $settings[$boxKey], $scaleX, $scaleY);
            imagedestroy($image);
        }
    }

    a4_draw_additional_images($canvas, $settings, $scaleX, $scaleY);
    a4_draw_symbol_key($canvas, $record, $settings, $scaleX, $scaleY, $fontFile, $textColor);
    a4_draw_additional_texts($canvas, $record, $settings, $scaleX, $scaleY, $fontFile, $boldFont);

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
