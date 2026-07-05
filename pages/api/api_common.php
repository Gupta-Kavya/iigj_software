<?php

function api_certificate_field_definitions()
{
    return [
        'certi_no' => ['label' => 'Certificate No', 'column' => 'certi_no'],
        'report_no' => ['label' => 'Report No', 'column' => 'report_no'],
        'stone_image_url' => ['label' => 'Stone Image URL', 'column' => null],
        'date' => ['label' => 'Date', 'column' => 'date'],
        'stone_name' => ['label' => 'Stone Name', 'column' => 'stone_name'],
        'stone_wt' => ['label' => 'Weight', 'column' => 'stone_wt'],
        'shape_cut' => ['label' => 'Shape / Cut', 'column' => 'shape_cut'],
        'dimension' => ['label' => 'Dimension', 'column' => 'dimension'],
        'color' => ['label' => 'Colour', 'column' => 'color'],
        'optic_char' => ['label' => 'Optic Character', 'column' => 'optic_char'],
        'ref_index' => ['label' => 'Refractive Index', 'column' => 'ref_index'],
        'spe_gravit' => ['label' => 'Specific Gravity', 'column' => 'spe_gravit'],
        'magni' => ['label' => 'Magnification', 'column' => 'magni'],
        'spe_group' => ['label' => 'Species / Group', 'column' => 'spe_group'],
        'origin' => ['label' => 'Origin', 'column' => 'origin'],
        'hardness' => ['label' => 'Hardness', 'column' => 'hardness'],
        'comment' => ['label' => 'Remarks', 'column' => 'comment'],
        'issued_to' => ['label' => 'Issued To', 'column' => 'issued_to'],
        'dia_wt' => ['label' => 'Diamond Weight', 'column' => 'dia_wt'],
        'stone_pcs' => ['label' => 'Diamond Pcs', 'column' => 'stone_pcs'],
        'clarity' => ['label' => 'Clarity', 'column' => 'clarity'],
        'finish' => ['label' => 'Finish', 'column' => 'finish'],
        'cut' => ['label' => 'Cut', 'column' => 'cut'],
        'table' => ['label' => 'Table', 'column' => 'table'],
        'crown' => ['label' => 'Crown Height', 'column' => 'crown'],
        'girdle' => ['label' => 'Girdle', 'column' => 'girdle'],
        'pav_depth' => ['label' => 'Pavilion Depth', 'column' => 'pav_depth'],
        'tab_depth' => ['label' => 'Table Depth', 'column' => 'tab_depth'],
        'flurance' => ['label' => 'Fluorescence', 'column' => 'flurance'],
        'desc' => ['label' => 'Description', 'column' => 'desc'],
        'faces' => ['label' => 'Face', 'column' => 'faces'],
        'rem1' => ['label' => 'Rudraksha Remarks', 'column' => 'rem1'],
        'rem2' => ['label' => 'Test Carried Out', 'column' => 'rem2'],
    ];
}

function api_default_public_fields()
{
    return [
        'certi_no',
        'report_no',
        'stone_image_url',
        'date',
        'desc',
        'stone_name',
        'stone_wt',
        'dia_wt',
        'stone_pcs',
        'shape_cut',
        'dimension',
        'color',
        'clarity',
        'cut',
        'spe_group',
        'comment',
        'issued_to',
    ];
}

function api_normalize_public_fields($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        $value = [];
    }
    return array_values(array_intersect(array_keys(api_certificate_field_definitions()), array_map('strval', $value)));
}

function api_get_request_key()
{
    $key = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($key !== '') {
        return $key;
    }
    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = trim((string) ($headers['Authorization'] ?? ''));
    }
    return stripos($authorization, 'Bearer ') === 0 ? trim(substr($authorization, 7)) : '';
}

function api_find_settings_by_key($conn, $plainKey)
{
    if ($plainKey === '' || strpos($plainKey, 'sl_live_') !== 0) {
        return null;
    }
    $hash = hash('sha256', $plainKey);
    $stmt = $conn->prepare('SELECT s.user_id, s.verification_url, s.public_fields FROM sm_api_settings s INNER JOIN sm_users u ON u.id = s.user_id WHERE s.api_key_hash = ? AND s.key_revoked_at IS NULL AND u.status = "active" LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function api_fetch_certificate($conn, $userId, $reportNo, $publicFields)
{
    $stmt = $conn->prepare('SELECT * FROM sm_form_data WHERE user_id = ? AND report_no = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $userId, $reportNo);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$record) {
        return null;
    }
    $definitions = api_certificate_field_definitions();
    $data = [];
    foreach (api_normalize_public_fields($publicFields) as $field) {
        if ($field === 'stone_image_url') {
            $data[$field] = api_stone_image_url($userId, $record['certi_no'] ?? null);
            continue;
        }
        $column = $definitions[$field]['column'];
        $data[$field] = $record[$column] ?? null;
    }
    if (!array_key_exists('stone_image_url', $data)) {
        $data['stone_image_url'] = api_stone_image_url($userId, $record['certi_no'] ?? null);
    }
    return $data;
}

function api_stone_image_url($userId, $certiNo)
{
    $certiNo = preg_replace('/[^0-9]/', '', (string) $certiNo);
    if ($userId <= 0 || $certiNo === '') {
        return null;
    }

    $relativeBase = 'user_data/user_' . (int) $userId . '/st_images/';
    foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $ext) {
        $relativePath = $relativeBase . $certiNo . '.' . $ext;
        $absolutePath = dirname(__DIR__) . '/' . $relativePath;
        if (!is_file($absolutePath)) {
            continue;
        }

        $pagesBase = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/pages/api/v1/certificate.php')))), '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '/' . trim($pagesBase . '/' . $relativePath, '/');
        }
        return $scheme . '://' . $host . '/' . trim($pagesBase . '/' . $relativePath, '/');
    }

    return null;
}

function api_log_usage($conn, $userId, $endpoint, $method, $reportNo, $statusCode)
{
    if ($userId <= 0) {
        return false;
    }
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stmt = $conn->prepare('INSERT INTO sm_api_usage_logs (user_id, endpoint, request_method, report_no, status_code, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) {
        error_log('API usage log prepare failed: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('isssiss', $userId, $endpoint, $method, $reportNo, $statusCode, $ip, $agent);
    $saved = $stmt->execute();
    if (!$saved) {
        error_log('API usage log insert failed: ' . $stmt->error);
    }
    $stmt->close();
    return $saved;
}

function api_rate_limit_rules()
{
    return [
        'ip_per_minute' => 60,
        'user_per_hour' => 300,
    ];
}

function api_rate_limit_check($conn, $userId, $endpoint, $method)
{
    $rules = api_rate_limit_rules();
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    if ($ip !== '') {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM sm_api_usage_logs WHERE endpoint = ? AND request_method = ? AND ip_address = ? AND created_at >= (NOW() - INTERVAL 1 MINUTE)');
        if ($stmt) {
            $stmt->bind_param('sss', $endpoint, $method, $ip);
            $stmt->execute();
            $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();
            if ($count >= $rules['ip_per_minute']) {
                return [
                    'allowed' => false,
                    'status_code' => 429,
                    'message' => 'Too many API requests from this IP. Please retry shortly.',
                    'retry_after' => 60,
                    'limit_scope' => 'ip_per_minute',
                    'limit' => $rules['ip_per_minute'],
                    'remaining' => 0,
                ];
            }
        }
    }

    if ($userId > 0) {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM sm_api_usage_logs WHERE user_id = ? AND endpoint = ? AND request_method = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)');
        if ($stmt) {
            $stmt->bind_param('iss', $userId, $endpoint, $method);
            $stmt->execute();
            $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();
            if ($count >= $rules['user_per_hour']) {
                return [
                    'allowed' => false,
                    'status_code' => 429,
                    'message' => 'Hourly API request limit reached for this API key. Please retry later.',
                    'retry_after' => 3600,
                    'limit_scope' => 'user_per_hour',
                    'limit' => $rules['user_per_hour'],
                    'remaining' => 0,
                ];
            }
        }
    }

    return [
        'allowed' => true,
        'status_code' => 200,
        'message' => '',
        'retry_after' => 0,
        'limit_scope' => 'ok',
        'limit' => 0,
        'remaining' => null,
    ];
}

function api_json_response($statusCode, $payload)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
