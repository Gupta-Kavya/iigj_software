<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';
auth_block_demo_action('Certificate image save', 'image_manager.php', true);

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

if (isset($_POST['croppedData'])) {
  $image_name = $_POST['certino'];
  $uploadToken = cstone_safe_upload_token($_POST['upload_token'] ?? '');
  $data = $_POST['croppedData'];
  $data = str_replace('data:image/jpeg;base64,', '', $data);
  $data = str_replace(' ', '+', $data);
  $imageData = base64_decode($data);
  if ($uploadToken !== '') {
    $filename = '_pending/' . $uploadToken . '.jpg';
    file_put_contents(cstone_pending_image_dir() . '/' . $uploadToken . '.jpg', $imageData);
  } else {
    $filename = $image_name . '.jpg';
    file_put_contents(atm_user_image_dir(cstone_image_folder()) . "/$filename", $imageData);
  }
  echo "<img src = '" . atm_user_image_relative($filename, cstone_image_folder()) . "?q=".time()."' style = 'width:100%; height:230px;' id = 'stone_image'>";
}

?>
