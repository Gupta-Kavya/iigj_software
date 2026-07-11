<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'a4_config.php';
require_once 'postcard_config.php';

function builder_backup_kind($value)
{
    $kind = strtolower(trim((string) $value));
    return in_array($kind, ['atm', 'a4', 'postcard'], true) ? $kind : '';
}

function builder_backup_settings_file($kind, $type)
{
    if ($kind === 'a4') {
        return a4_settings_file($type);
    }
    if ($kind === 'postcard') {
        return postcard_settings_file($type);
    }
    return atm_layout_file($type);
}

function builder_backup_return_url($kind, $type, $status = '')
{
    $page = $kind === 'a4' ? 'a4Settings.php' : ($kind === 'postcard' ? 'postcardSettings.php' : 'settings.php');
    $url = $page . '?type=' . rawurlencode($type);
    if ($status !== '') {
        $url .= '&builder_backup=' . rawurlencode($status);
    }
    return $url;
}

function builder_backup_collect_asset($relativePath)
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if ($relativePath === '') {
        return null;
    }
    $path = atm_builder_asset_path($relativePath);
    if ($path === '') {
        return null;
    }
    $data = @file_get_contents($path);
    if ($data === false) {
        return null;
    }
    return [
        'path' => $relativePath,
        'name' => basename($relativePath),
        'mime' => function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream',
        'data' => base64_encode($data),
    ];
}

function builder_backup_collect_assets_from_settings(array $settings)
{
    $assets = [];
    $add = function ($path) use (&$assets) {
        $asset = builder_backup_collect_asset($path);
        if ($asset) {
            $assets[$asset['path']] = $asset;
        }
    };
    $add($settings['backgroundImage'] ?? '');
    foreach (($settings['additionalImages'] ?? []) as $image) {
        if (is_array($image)) {
            $add($image['src'] ?? '');
        }
    }
    return array_values($assets);
}

function builder_backup_restore_asset(array $asset)
{
    $name = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) ($asset['name'] ?? basename((string) ($asset['path'] ?? ''))));
    if ($name === '' || strpos($name, '.') === false) {
        return '';
    }
    $raw = base64_decode((string) ($asset['data'] ?? ''), true);
    if ($raw === false || $raw === '') {
        return '';
    }
    $relative = atm_user_asset_relative($name);
    $target = __DIR__ . '/' . $relative;
    if (!is_dir(dirname($target))) {
        @mkdir(dirname($target), 0775, true);
    }
    return @file_put_contents($target, $raw, LOCK_EX) === false ? '' : $relative;
}

function builder_backup_restore_asset_paths(array &$settings, array $assets)
{
    $map = [];
    foreach ($assets as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $oldPath = str_replace('\\', '/', trim((string) ($asset['path'] ?? '')));
        $newPath = builder_backup_restore_asset($asset);
        if ($oldPath !== '' && $newPath !== '') {
            $map[$oldPath] = $newPath;
        }
    }
    if (!$map) {
        return;
    }
    if (isset($settings['backgroundImage'], $map[$settings['backgroundImage']])) {
        $settings['backgroundImage'] = $map[$settings['backgroundImage']];
    }
    foreach (($settings['additionalImages'] ?? []) as $index => $image) {
        if (is_array($image) && isset($image['src'], $map[$image['src']])) {
            $settings['additionalImages'][$index]['src'] = $map[$image['src']];
        }
    }
}

$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));
$kind = builder_backup_kind($_GET['kind'] ?? $_POST['kind'] ?? '');
$type = atm_report_type($_GET['type'] ?? $_POST['type'] ?? 'S');

if ($kind === '' || !in_array($action, ['export', 'import'], true)) {
    http_response_code(400);
    echo 'Invalid builder backup request.';
    exit;
}

if ($action === 'export') {
    if ($kind === 'atm') {
        $settings = [
            'positions' => atm_read_json(atm_layout_file($type), atm_default_positions($type)),
            'fieldSettings' => atm_read_json(atm_layout_file($type, 'settings'), atm_default_fields()),
        ];
        $assets = builder_backup_collect_assets_from_settings($settings['positions']);
    } elseif ($kind === 'a4') {
        $settings = a4_read_settings($type);
        $assets = builder_backup_collect_assets_from_settings($settings);
    } else {
        $settings = postcard_read_settings($type);
        $assets = builder_backup_collect_assets_from_settings($settings);
    }

    $payload = [
        'backupType' => 'iigj_builder_settings',
        'version' => 1,
        'builder' => $kind,
        'reportType' => $type,
        'createdAt' => date('c'),
        'settings' => $settings,
        'assets' => $assets,
    ];

    $fileName = 'iigj-' . $kind . '-builder-' . preg_replace('/[^A-Za-z0-9_-]/', '', $type) . '-' . date('Ymd-His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

auth_block_demo_action('Builder settings import', builder_backup_return_url($kind, $type), true);

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . builder_backup_return_url($kind, $type, 'missing'));
    exit;
}

$raw = @file_get_contents($_FILES['backup_file']['tmp_name']);
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload) || ($payload['backupType'] ?? '') !== 'iigj_builder_settings' || !isset($payload['settings']) || !is_array($payload['settings'])) {
    header('Location: ' . builder_backup_return_url($kind, $type, 'invalid'));
    exit;
}

$sourceKind = builder_backup_kind($payload['builder'] ?? '');
if ($sourceKind !== $kind) {
    header('Location: ' . builder_backup_return_url($kind, $type, 'mismatch'));
    exit;
}

$settings = $payload['settings'];
$assets = isset($payload['assets']) && is_array($payload['assets']) ? $payload['assets'] : [];

if ($kind === 'atm') {
    $positions = isset($settings['positions']) && is_array($settings['positions']) ? $settings['positions'] : [];
    $fieldSettings = isset($settings['fieldSettings']) && is_array($settings['fieldSettings']) ? $settings['fieldSettings'] : [];
    builder_backup_restore_asset_paths($positions, $assets);
    $ok = file_put_contents(atm_layout_file($type), json_encode($positions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    $ok = $ok && file_put_contents(atm_layout_file($type, 'settings'), json_encode($fieldSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
} else {
    builder_backup_restore_asset_paths($settings, $assets);
    $ok = file_put_contents(builder_backup_settings_file($kind, $type), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

header('Location: ' . builder_backup_return_url($kind, $type, $ok ? 'imported' : 'failed'));
exit;
