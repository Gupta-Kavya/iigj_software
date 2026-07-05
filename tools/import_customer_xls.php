<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('AUTH_ALLOW_PUBLIC', true);
require_once __DIR__ . '/../pages/db_connect.php';
require_once __DIR__ . '/../pages/customer_helper.php';

$source = $argv[1] ?? 'C:\\Users\\kavya\\Downloads\\customer.xls';
if (!is_file($source)) {
    fwrite(STDERR, "File not found: {$source}\n");
    exit(1);
}

if (!customer_master_table_ready($conn)) {
    fwrite(STDERR, "Unable to prepare customer master table.\n");
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
        $text = substr($body, 8, $charCount);
        $cells[$info['row']][$info['col']] = trim($text);
    } elseif ($record['op'] === 3 && strlen($body) >= 15) {
        $info = unpack('vrow/vcol', substr($body, 0, 4));
        $number = unpack('dvalue', substr($body, 7, 8))['value'];
        if (is_finite($number)) {
            $cells[$info['row']][$info['col']] = abs($number - round($number)) < 0.0000001
                ? (string) (int) round($number)
                : (string) $number;
        }
    }
}

$headers = [];
for ($col = 0; $col <= 12; $col++) {
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

    $customerName = customer_master_clean($record['customer'] ?? '', 180);
    if ($customerName === '') {
        $skipped++;
        continue;
    }

    $addressParts = array_filter([
        customer_master_clean($record['add1'] ?? '', 500),
        customer_master_clean($record['add2'] ?? '', 500),
    ], 'strlen');
    $memberValue = trim((string) ($record['member'] ?? ''));
    $memberStatus = ($memberValue !== '' && $memberValue !== '0') ? 'Member' : 'Non Member';

    $ok = customer_master_upsert($conn, 0, [
        'customer_name' => $customerName,
        'depositor_name' => '',
        'address' => implode("\n", $addressParts),
        'mobile_no' => $record['phone'] ?? '',
        'email' => $record['email'] ?? '',
        'member_status' => $memberStatus,
        'mou_cdc' => $record['cdc'] ?? '',
        'id_no' => $record['idno'] ?? '',
        'gst_no' => $record['gstno'] ?? '',
        'source_key' => sha1('customer.xls|' . $row . '|' . $customerName . '|' . ($record['phone'] ?? '') . '|' . ($record['gstno'] ?? '')),
    ]);

    $ok ? $imported++ : $skipped++;
}

echo "Imported: {$imported}\nSkipped: {$skipped}\n";
?>
