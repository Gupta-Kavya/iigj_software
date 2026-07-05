<?php
define('AUTH_ALLOW_PUBLIC', true);
require_once dirname(__DIR__, 2) . '/db_connect.php';
require_once dirname(__DIR__) . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    api_json_response(405, ['success' => false, 'message' => 'Only GET requests are allowed.']);
}

$settings = api_find_settings_by_key($conn, api_get_request_key());
if (!$settings) {
    api_json_response(401, ['success' => false, 'message' => 'Invalid or revoked API key.']);
}

$userId = (int) $settings['user_id'];
$rateLimit = api_rate_limit_check($conn, $userId, '/api/v1/certificate', 'GET');
if (!$rateLimit['allowed']) {
    api_log_usage($conn, $userId, '/api/v1/certificate', 'GET', null, 429);
    header('Retry-After: ' . (int) $rateLimit['retry_after']);
    api_json_response(429, [
        'success' => false,
        'message' => $rateLimit['message'],
        'rate_limit' => [
            'scope' => $rateLimit['limit_scope'],
            'limit' => $rateLimit['limit'],
            'retry_after_seconds' => $rateLimit['retry_after'],
        ],
    ]);
}

$reportNo = trim((string) ($_GET['report_no'] ?? ''));
if ($reportNo === '' || strlen($reportNo) > 100) {
    api_log_usage($conn, $userId, '/api/v1/certificate', 'GET', null, 400);
    api_json_response(400, ['success' => false, 'message' => 'A valid report_no is required.']);
}

$data = api_fetch_certificate($conn, $userId, $reportNo, $settings['public_fields']);
if ($data === null) {
    api_log_usage($conn, $userId, '/api/v1/certificate', 'GET', $reportNo, 404);
    api_json_response(404, ['success' => false, 'message' => 'Report not found.']);
}

api_log_usage($conn, $userId, '/api/v1/certificate', 'GET', $reportNo, 200);
api_json_response(200, ['success' => true, 'certificate' => $data]);
