<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';
auth_block_demo_action('Image upload', 'image_manager.php', true);

//upload.php
function image_manager_folder()
{
	$folder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['folder'] ?? 'st_images'));
	return in_array($folder, ['st_images', 'symbol_images', 'clarity_images', 'proportion_images'], true) ? $folder : 'st_images';
}

if(isset($_FILES['images']))
{
	$upload_directory = atm_user_image_dir(image_manager_folder());
	for($count = 0; $count < count($_FILES['images']['name']); $count++)
	{
		if ($_FILES['images']['error'][$count] !== UPLOAD_ERR_OK) {
			continue;
		}

		$extension = strtolower(pathinfo($_FILES['images']['name'][$count], PATHINFO_EXTENSION));
		if (!in_array($extension, ['jpg', 'jpeg'], true)) {
			continue;
		}

		$imageInfo = @getimagesize($_FILES['images']['tmp_name'][$count]);
		if (!$imageInfo || $imageInfo['mime'] !== 'image/jpeg') {
			continue;
		}

		$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['images']['name'][$count]));
		move_uploaded_file($_FILES['images']['tmp_name'][$count], $upload_directory . '/' . $filename);

	}

	echo 'success';
}
