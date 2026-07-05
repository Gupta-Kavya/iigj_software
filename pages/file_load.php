<?php
require_once 'auth.php';
auth_require_login();
require_once 'atm_config.php';
$upload_directory = atm_user_stone_dir() . '/';
$upload_url = 'user_data/user_' . auth_current_user_id() . '/st_images/';
$per_page = 50;
$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
$search = isset($_POST['search']) ? trim((string) $_POST['search']) : '';

$image_files = glob($upload_directory . '*.{jpg,jpeg,JPG}', GLOB_BRACE);
if (is_array($image_files)) {
	natcasesort($image_files);
	$image_files = array_values($image_files);
} else {
	$image_files = array();
}

if ($search !== '') {
	$image_files = array_values(array_filter($image_files, function ($image) use ($search) {
		return stripos(basename($image), $search) !== false;
	}));
}

function human_filesize($size) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
	for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
	  $size /= 1024;
	}
	return round($size, 2) . ' ' . $units[$i];
}

$total_images = count($image_files);
$total_pages = max(1, (int) ceil($total_images / $per_page));
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $per_page;
$paged_images = array_slice($image_files, $offset, $per_page);
$from = $total_images > 0 ? $offset + 1 : 0;
$to = min($offset + count($paged_images), $total_images);

echo '<div id="image_pagination_meta" data-total="' . $total_images . '" data-page="' . $page . '" data-total-pages="' . $total_pages . '" data-from="' . $from . '" data-to="' . $to . '"></div>';

if (empty($paged_images) == false) {
	$index = 0;
	echo '<div class="image-list-head"><span>File name</span><span>Size</span></div>';
	foreach ($paged_images as $image) {
		$image_name = basename($image);
		$safe_name = htmlspecialchars($image_name, ENT_QUOTES, 'UTF-8');
		$safe_url = htmlspecialchars($upload_url . rawurlencode($image_name), ENT_QUOTES, 'UTF-8');
		$input_id = 'image_item_' . $index;
		$size = filesize($upload_directory . $image_name);

		echo '<div class="image-list-item image-item" data-name="' . $safe_name . '" data-url="' . $safe_url . '">'
			. '<input type="checkbox" id="' . $input_id . '" value="' . $safe_name . '" class="image-item-checkbox" />'
			. '<label for="' . $input_id . '" class="image-list-check" title="Select for bulk delete"><span class="image-list-check-box"></span></label>'
			. '<span class="file_name" title="Double-click to preview">' . $safe_name . '</span>'
			. '<span class="file_size">' . human_filesize($size) . '</span>'
			. '</div>';
		$index++;
	}

	if ($total_pages > 1) {
		echo '<div class="image-pagination">';
		echo '<button type="button" class="image-page-btn" data-page="' . ($page - 1) . '"' . ($page <= 1 ? ' disabled' : '') . '><i class="fa fa-angle-left"></i> Prev</button>';

		$start_page = max(1, $page - 2);
		$end_page = min($total_pages, $page + 2);

		if ($start_page > 1) {
			echo '<button type="button" class="image-page-btn" data-page="1">1</button>';
			if ($start_page > 2) {
				echo '<span class="image-page-ellipsis">...</span>';
			}
		}

		for ($i = $start_page; $i <= $end_page; $i++) {
			echo '<button type="button" class="image-page-btn' . ($i === $page ? ' is-active' : '') . '" data-page="' . $i . '">' . $i . '</button>';
		}

		if ($end_page < $total_pages) {
			if ($end_page < $total_pages - 1) {
				echo '<span class="image-page-ellipsis">...</span>';
			}
			echo '<button type="button" class="image-page-btn" data-page="' . $total_pages . '">' . $total_pages . '</button>';
		}

		echo '<button type="button" class="image-page-btn" data-page="' . ($page + 1) . '"' . ($page >= $total_pages ? ' disabled' : '') . '>Next <i class="fa fa-angle-right"></i></button>';
		echo '</div>';
	}
} else {
	$empty_message = $search !== '' ? 'No images found for your search.' : 'Upload images to get started.';
	echo '<div class="image-empty">' . htmlspecialchars($empty_message, ENT_QUOTES, 'UTF-8') . '</div>';
}
