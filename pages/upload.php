<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

function cstone_pending_image_dir()
{
    $dir = atm_user_image_dir(cstone_image_folder()) . '/_pending';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function cstone_image_folder()
{
    $type = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['image_type'] ?? 'stone'));
    return $type === 'proportion' ? 'proportion_images' : ($type === 'clarity' ? 'clarity_images' : 'st_images');
}

function cstone_safe_upload_token($token)
{
    $token = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $token);
    return $token !== '' ? substr($token, 0, 80) : '';
}

function cstone_preview_html($relativePath)
{
    return "<img src = '" . $relativePath . "?q=" . time() . "' style = 'width:100%; height:230px;' id = 'stone_image'>";
}

function cstone_save_image_resource($image, $imageName, $uploadToken)
{
    if (!$image) {
        return '';
    }

    $compressed_width = 800;
    $compressed_height = 600;
    $compressed_image = imagecreatetruecolor($compressed_width, $compressed_height);
    imagecopyresampled($compressed_image, $image, 0, 0, 0, 0, $compressed_width, $compressed_height, imagesx($image), imagesy($image));

    if ($uploadToken !== '') {
        $filename = '_pending/' . $uploadToken . '.jpg';
        imagejpeg($compressed_image, cstone_pending_image_dir() . '/' . $uploadToken . '.jpg', 82);
    } else {
        $filename = preg_replace('/[^0-9]/', '', (string) $imageName) . '.jpg';
        imagejpeg($compressed_image, atm_user_image_dir(cstone_image_folder()) . '/' . $filename, 82);
    }

    imagedestroy($compressed_image);
    return $filename;
}

if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $uploadToken = cstone_safe_upload_token($_POST['upload_token'] ?? '');
    $imageName = $_POST['certino'] ?? '';
    $imageInfo = @getimagesize($_FILES['image_file']['tmp_name']);
    if (!$imageInfo || !in_array($imageInfo['mime'], ['image/jpeg', 'image/png'], true)) {
        http_response_code(400);
        echo 'Please upload a valid JPG or PNG image.';
        exit;
    }

    $original_image = @imagecreatefromstring(file_get_contents($_FILES['image_file']['tmp_name']));
    $filename = cstone_save_image_resource($original_image, $imageName, $uploadToken);
    if ($original_image) {
        imagedestroy($original_image);
    }

    if ($filename === '') {
        http_response_code(400);
        echo 'Unable to read uploaded image.';
        exit;
    }

    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo cstone_preview_html(atm_user_image_relative($filename, cstone_image_folder()));
    exit;
}

if (isset($_POST['image'])) {
    $data = $_POST['image'];
    $image_name = $_POST['certino'];
    $uploadToken = cstone_safe_upload_token($_POST['upload_token'] ?? '');
    $image_array_1 = explode(";", $data);

    $image_array_2 = explode(",", $image_array_1[1]);

    $decoded_image  = base64_decode($image_array_2[1]);
    $original_image = imagecreatefromstring($decoded_image);

    $filename = cstone_save_image_resource($original_image, $image_name, $uploadToken);
    if ($original_image) {
        imagedestroy($original_image);
    }

    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');


    echo cstone_preview_html(atm_user_image_relative($filename, cstone_image_folder()));
}
