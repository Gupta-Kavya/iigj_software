<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'a4_config.php';

header('Content-Type: application/json');

if (!isset($_FILES['font']) || $_FILES['font']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please select a TTF font file.']);
    exit;
}

$originalName = (string) ($_FILES['font']['name'] ?? 'font.ttf');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($extension !== 'ttf') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Only .ttf font files are allowed.']);
    exit;
}

$size = (int) ($_FILES['font']['size'] ?? 0);
if ($size <= 0 || $size > 8 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Font file must be below 8 MB.']);
    exit;
}

$label = trim((string) ($_POST['label'] ?? pathinfo($originalName, PATHINFO_FILENAME)));
$label = preg_replace('/[^A-Za-z0-9 _.-]/', '', $label);
if ($label === '') {
    $label = 'Custom Font';
}

$safeBase = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $label));
$safeBase = trim($safeBase, '-');
if ($safeBase === '') {
    $safeBase = 'custom-font';
}

$name = 'font-' . $safeBase . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.ttf';
$relativePath = atm_user_asset_relative($name);
$targetPath = __DIR__ . '/' . $relativePath;

if (!is_dir(dirname($targetPath))) {
    @mkdir(dirname($targetPath), 0775, true);
}

if (!move_uploaded_file($_FILES['font']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save uploaded font.']);
    exit;
}

$family = 'Custom ' . $label . ' ' . substr(md5($relativePath), 0, 8);
$fonts = a4_custom_fonts();
$fonts[] = [
    'family' => $family,
    'label' => $label,
    'src' => $relativePath,
];

file_put_contents(a4_custom_fonts_file(), json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

echo json_encode([
    'status' => 'success',
    'font' => [
        'family' => $family,
        'label' => $label,
        'src' => $relativePath,
    ],
]);
?>
