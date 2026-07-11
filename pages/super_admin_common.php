<?php
require_once 'auth.php';
require_once 'db_connect.php';
require_once 'smtp_mailer.php';
require_once 'user_branch_helper.php';
require_once 'waapi_helper.php';
user_branch_location_ready($conn);
waapi_settings_table_ready($conn);
if (auth_is_logged_in()) {
    $roleStmt = $conn->prepare("SELECT role FROM sm_users WHERE id = ? LIMIT 1");
    $currentUserId = auth_current_user_id();
    if ($roleStmt) {
        $roleStmt->bind_param('i', $currentUserId);
        $roleStmt->execute();
        $roleRow = $roleStmt->get_result()->fetch_assoc();
        $roleStmt->close();
        $_SESSION['user_role'] = $roleRow['role'] ?? 'user';
    }
}
auth_require_super_admin();

function super_admin_bind_params($stmt, $types, &$params)
{
    $references = [$types];
    foreach ($params as $key => &$value) $references[] = &$value;
    return call_user_func_array([$stmt, 'bind_param'], $references);
}

function super_admin_csrf()
{
    if (!isset($_SESSION['super_admin_csrf'])) {
        $_SESSION['super_admin_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['super_admin_csrf'];
}

function super_admin_verify_csrf()
{
    return hash_equals(super_admin_csrf(), (string) ($_POST['csrf_token'] ?? ''));
}

function super_admin_flash($message, $type = 'success')
{
    $_SESSION['super_admin_flash'] = ['message' => $message, 'type' => $type];
}

function super_admin_take_flash()
{
    $flash = $_SESSION['super_admin_flash'] ?? null;
    unset($_SESSION['super_admin_flash']);
    return $flash;
}

function super_admin_redirect($url, $message = '', $type = 'success')
{
    if ($message !== '') super_admin_flash($message, $type);
    header('Location: ' . $url);
    exit;
}

function super_admin_user_image_count($userId)
{
    $dir = __DIR__ . '/user_data/user_' . (int) $userId . '/st_images';
    if (!is_dir($dir)) return 0;
    $count = 0;
    foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $extension) {
        $files = glob($dir . '/*.' . $extension);
        $count += is_array($files) ? count($files) : 0;
    }
    return $count;
}

function super_admin_format_date($value, $withTime = true)
{
    if (!$value || $value === '0000-00-00 00:00:00') return 'Not available';
    $timestamp = strtotime($value);
    return $timestamp ? date($withTime ? 'd M Y, h:i A' : 'd M Y', $timestamp) : (string) $value;
}

function super_admin_report_type($type)
{
    return ['S' => 'Colour Stone', 'D' => 'Diamond', 'DS' => 'Diamond Screening', 'J' => 'Jewellery', 'R' => 'Rudraksha'][strtoupper((string) $type)] ?? 'Unknown';
}
