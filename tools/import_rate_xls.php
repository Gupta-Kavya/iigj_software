<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('AUTH_ALLOW_PUBLIC', true);
require_once __DIR__ . '/../pages/db_connect.php';
require_once __DIR__ . '/../pages/rate_helper.php';

$source = $argv[1] ?? 'C:\\Users\\kavya\\Downloads\\ratelist.xls';
if (!is_file($source)) {
    fwrite(STDERR, "File not found: {$source}\n");
    exit(1);
}

if (!rate_master_table_ready($conn)) {
    fwrite(STDERR, "Unable to prepare rate master table.\n");
    exit(1);
}

$data = file_get_contents($source);
$pos = 0;
$len = strlen($data);
$cells = [];

while ($pos + 4 <= $len) {
    $record = unpack('vop/vlength', substr($data, $pos, 4));
    $pos += 4;
    $body = substr($data, $pos, $record['length']);
    $pos += $record['length'];

    if ($record['op'] === 4 && strlen($body) >= 8) {
        $info = unpack('vrow/vcol', substr($body, 0, 4));
        $charCount = ord($body[7]);
        $cells[$info['row']][$info['col']] = trim(substr($body, 8, $charCount));
    } elseif ($record['op'] === 3 && strlen($body) >= 15) {
        $info = unpack('vrow/vcol', substr($body, 0, 4));
        $number = unpack('dvalue', substr($body, 7, 8))['value'];
        $cells[$info['row']][$info['col']] = abs($number - round($number)) < 0.0000001
            ? (string) (int) round($number)
            : (string) $number;
    }
}

$headers = [];
for ($col = 0; $col <= 8; $col++) {
    $headers[$col] = strtolower(trim((string) ($cells[0][$col] ?? 'col' . $col)));
}

$imported = 0;
$skipped = 0;
$maxRow = $cells ? max(array_keys($cells)) : 0;
for ($row = 1; $row <= $maxRow; $row++) {
    $record = [];
    foreach ($headers as $col => $header) {
        $record[$header] = trim((string) ($cells[$row][$col] ?? ''));
    }

    $description = rate_master_clean($record['desc'] ?? '', 255);
    if ($description === '') {
        $skipped++;
        continue;
    }

    $ok = rate_master_upsert($conn, [
        'category' => $record['category'] ?? '',
        'rate_code' => $record['ratecode'] ?? '',
        'range_from' => $record['from'] ?? '',
        'range_to' => $record['to'] ?? '',
        'rate_member' => $record['rate_memb'] ?? '',
        'rate_non_member' => $record['rate_nonme'] ?? '',
        'remark' => $record['remark'] ?? '',
        'description' => $description,
        'cdc' => $record['cdc'] ?? '',
        'source_key' => sha1('ratelist.xls|' . $row . '|' . ($record['ratecode'] ?? '') . '|' . $description),
    ]);

    $ok ? $imported++ : $skipped++;
}

echo "Imported: {$imported}\nSkipped: {$skipped}\n";
?>
