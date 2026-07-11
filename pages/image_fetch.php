<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';
$input = preg_replace('/[^0-9]/', '', (string) ($_POST["input"] ?? ''));
$uploadToken = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['upload_token'] ?? ''));
$imageType = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['image_type'] ?? 'stone'));
$folder = $imageType === 'proportion' ? 'proportion_images' : ($imageType === 'clarity' ? 'clarity_images' : 'st_images');
// process the input data

$pendingFile = $uploadToken !== '' ? atm_user_image_dir($folder) . "/_pending/$uploadToken.jpg" : '';
$pendingUrl = $uploadToken !== '' ? atm_user_image_relative("_pending/$uploadToken.jpg", $folder) : '';
$file = atm_user_image_dir($folder) . "/$input.jpg";
$url = atm_user_image_relative("$input.jpg", $folder);
$fallbackFile = isset($GLOBALS['conn']) ? atm_branch_image_path_for_user($GLOBALS['conn'], auth_current_user_id(), $input, $folder) : '';
$fallbackUrl = $fallbackFile !== ''
    ? str_replace('\\', '/', substr($fallbackFile, strlen(__DIR__) + 1))
    : '';
if($pendingFile && file_exists($pendingFile)){

    echo "<img src = '$pendingUrl?q=".time()."' style = 'width:100%; height:230px;' id = 'stone_image'>";

}elseif(file_exists($file)){

    echo "<img src = '$url?q=".time()."' style = 'width:100%; height:230px;' id = 'stone_image'>";

}elseif($fallbackFile !== '' && file_exists($fallbackFile)){

    echo "<img src = '$fallbackUrl?q=".time()."' style = 'width:100%; height:230px;' id = 'stone_image'>";

}else{

    echo "<img src = 'assets/no_image_found.png' style = 'width:100%; height:230px;' id = 'stone_image'>";

}
clearstatcache();
