<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

header('Content-Type: application/json; charset=utf-8');

function symbol_image_slug($value)
{
    $value = trim((string) $value);
    $value = preg_replace('/[^A-Za-z0-9 _.-]/', '', $value);
    return $value !== '' ? substr($value, 0, 120) : '';
}

$symbols = [];
$raw = trim((string) ($_POST['symbols_json'] ?? $_GET['symbols_json'] ?? ''));
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $symbol) {
            $symbol = symbol_image_slug($symbol);
            if ($symbol !== '' && !in_array($symbol, $symbols, true)) {
                $symbols[] = $symbol;
            }
            if (count($symbols) >= 3) break;
        }
    }
}
if (!$symbols) {
    $single = symbol_image_slug($_POST['symbol'] ?? $_GET['symbol'] ?? '');
    if ($single !== '') {
        $symbols[] = $single;
    }
}

$extensions = ['png', 'jpg', 'jpeg', 'PNG', 'JPG', 'JPEG'];
$result = [];
foreach ($symbols as $symbol) {
    $path = atm_branch_image_path_for_user($conn, auth_current_user_id(), $symbol, 'symbol_images', $extensions);
    $url = '';
    if ($path !== '' && is_file($path)) {
        $url = str_replace('\\', '/', substr($path, strlen(__DIR__) + 1));
    }
    $result[] = [
        'symbol' => $symbol,
        'found' => $url !== '',
        'url' => $url,
    ];
}

echo json_encode(['status' => 'success', 'images' => $result]);
?>
