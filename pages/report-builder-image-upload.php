<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

header('Content-Type: application/json');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please select a JPG or PNG image.']);
    exit;
}

$info = @getimagesize($_FILES['image']['tmp_name']);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
if (!$info || !isset($extensions[$info['mime']])) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Only JPG and PNG images are allowed.']);
    exit;
}

$builder = preg_replace('/[^a-z]/', '', strtolower((string) ($_POST['builder'] ?? 'report')));
$builder = in_array($builder, ['atm', 'a4', 'postcard'], true) ? $builder : 'report';
$reportType = atm_report_type($_POST['report_type'] ?? 'S');
$safeType = strtolower(preg_replace('/[^A-Z0-9]/', '', $reportType));
$extension = $extensions[$info['mime']];
$name = $builder . '-extra-' . $safeType . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extension;
$relativePath = atm_user_asset_relative($name);
$targetPath = __DIR__ . '/' . $relativePath;

if (!is_dir(dirname($targetPath))) {
    @mkdir(dirname($targetPath), 0775, true);
}

if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save uploaded image.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'image' => [
        'id' => 'img_' . time() . '_' . random_int(100, 999),
        'label' => 'Extra Image',
        'src' => $relativePath,
        'url' => $relativePath . '?v=' . filemtime($targetPath),
    ],
]);
?>
