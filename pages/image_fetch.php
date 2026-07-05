<?php
require_once 'auth.php';
auth_require_login();
require_once 'atm_config.php';
$input = preg_replace('/[^0-9]/', '', (string) ($_POST["input"] ?? ''));
$uploadToken = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['upload_token'] ?? ''));
// process the input data

$pendingFile = $uploadToken !== '' ? atm_user_stone_dir() . "/_pending/$uploadToken.jpg" : '';
$pendingUrl = $uploadToken !== '' ? atm_user_stone_relative("_pending/$uploadToken.jpg") : '';
$file = atm_user_stone_dir() . "/$input.jpg";
$url = atm_user_stone_relative("$input.jpg");
$fallbackFile = isset($GLOBALS['conn']) ? atm_branch_stone_path_for_user($GLOBALS['conn'], auth_current_user_id(), $input) : '';
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
