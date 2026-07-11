<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';
auth_block_demo_action('Image deletion', 'image_manager.php', true);

function image_manager_folder()
{
	$folder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['folder'] ?? 'st_images'));
	return in_array($folder, ['st_images', 'symbol_images', 'clarity_images', 'proportion_images'], true) ? $folder : 'st_images';
}

$upload_directory = atm_user_image_dir(image_manager_folder()) . '/';

if(isset($_POST['images'])){
	foreach($_POST['images'] as $image){
		$file_path = $upload_directory . basename($image);
		if(file_exists($file_path)){
			unlink($file_path);
		}
	}
	echo "Images deleted successfully";
}
?>
