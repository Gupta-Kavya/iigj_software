<?php
require_once 'auth.php';
auth_require_login();
require_once 'atm_config.php';
auth_block_demo_action('Image deletion', 'image_manager.php', true);
$upload_directory = atm_user_stone_dir() . '/';

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
