<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'waapi_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$settings = waapi_get_settings($conn);
if ($settings['instance_id'] === '' || $settings['api_key'] === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'WhatsApp API settings are missing. Add Instance ID and API key in Super Admin.']);
    exit;
}

$id = max(0, (int) ($_POST['id'] ?? 0));
define('AGREEMENT_PRINT_EXACT_LIBRARY', true);
require_once __DIR__ . '/agreement-print-exact.php';

$agreement = agreement_exact_load($conn, $id, auth_current_user_id(), auth_is_super_admin());
if (!$agreement) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Agreement not found.']);
    exit;
}

$chatIds = waapi_chat_ids_from_mobile_field($agreement['mobile_no'] ?? '');
if (!$chatIds) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Customer mobile number is missing.']);
    exit;
}

$items = $agreement['_items'];
unset($agreement['_items']);
$html = agreement_exact_html($agreement, $items, false, true);

function agreement_whatsapp_chrome_path()
{
    foreach ([
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return '';
}

function agreement_whatsapp_file_url($path)
{
    return 'file:///' . str_replace(' ', '%20', str_replace('\\', '/', $path));
}

function agreement_whatsapp_browser_pdf($html)
{
    if (!function_exists('exec')) {
        return [false, 'PHP exec function is disabled.', ''];
    }

    $browser = agreement_whatsapp_chrome_path();
    if ($browser === '') {
        return [false, 'Chrome or Edge browser was not found on the server.', ''];
    }

    $dir = dirname(__DIR__) . '/tmp/waapi-agreement';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return [false, 'Unable to create temporary PDF folder.', ''];
    }

    $token = bin2hex(random_bytes(8));
    $htmlPath = $dir . '/agreement-' . $token . '.html';
    $pdfPath = $dir . '/agreement-' . $token . '.pdf';
    $profileDir = $dir . '/chrome-profile-' . $token;
    @mkdir($profileDir, 0777, true);

    $base = '<base href="' . htmlspecialchars(agreement_whatsapp_file_url(__DIR__ . '/'), ENT_QUOTES, 'UTF-8') . '">';
    $html = preg_replace('/<head(\s*)>/i', '<head$1>' . $base, $html, 1);
    if (@file_put_contents($htmlPath, $html) === false) {
        return [false, 'Unable to prepare agreement HTML for printing.', ''];
    }

    $command = escapeshellarg($browser)
        . ' --headless=new --disable-gpu --disable-extensions --no-first-run --no-default-browser-check'
        . ' --user-data-dir=' . escapeshellarg($profileDir)
        . ' --print-to-pdf=' . escapeshellarg($pdfPath)
        . ' --no-pdf-header-footer --print-to-pdf-no-header '
        . escapeshellarg(agreement_whatsapp_file_url($htmlPath));
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !is_file($pdfPath) || filesize($pdfPath) <= 0) {
        @unlink($htmlPath);
        return [false, 'Unable to generate exact browser PDF. ' . trim(implode(' ', $output)), ''];
    }

    $pdf = file_get_contents($pdfPath);
    @unlink($htmlPath);
    @unlink($pdfPath);
    return [$pdf !== false, $pdf !== false ? '' : 'Unable to read generated PDF.', $pdf !== false ? $pdf : ''];
}

function agreement_whatsapp_caption($agreement, $items)
{
    $customerName = trim((string) ($agreement['customer_name'] ?? 'Customer'));
    if ($customerName === '') {
        $customerName = 'Customer';
    }
    $agreementNo = agreement_exact_no($agreement);
    $dateText = agreement_exact_date($agreement['agreement_date'] ?? '');
    $timeText = trim((string) ($agreement['agreement_time'] ?? ''));
    $dateLine = trim($dateText.' /' . ($timeText !== '' ? ' ' . $timeText : ''));
    $pcsTotal = (int) ($agreement['pcs_total'] ?? 0);
    if ($pcsTotal <= 0) {
        foreach ($items as $item) {
            $pcsTotal += max(0, (int) ($item['pcs'] ?? 0));
        }
    }
    $amount = agreement_money($agreement['testing_charges'] ?? 0);

    $lines = [
        'Dear ' . $customerName . ',',
        'Your stone testing agreement has been created.',
        'Agreement No: ' . $agreementNo,
    ];
    if ($dateLine !== '') {
        $lines[] = 'Date/Time: ' . $dateLine;
    }
    $lines[] = 'Total Stones: ' . $pcsTotal;
    $lines[] = 'Estimated Charges: Rs. ' . $amount;
    $lines[] = 'Please find the attached agreement copy.';
    $lines[] = 'IIGJ RLC';

    return implode("\n", $lines);
}

[$pdfOk, $pdfError, $pdf] = agreement_whatsapp_browser_pdf($html);
if (!$pdfOk) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $pdfError]);
    exit;
}

$agreementNo = agreement_exact_no($agreement);
if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'PHP cURL extension is required to send WhatsApp messages.']);
    exit;
}

$url = 'https://waapi.app/api/v1/instances/' . rawurlencode($settings['instance_id']) . '/client/action/send-media';
$basePayload = [
    'mediaBase64' => base64_encode($pdf),
    'mediaName' => 'agreement-' . (int) $agreement['agreement_no'] . '.pdf',
    'mediaCaption' => agreement_whatsapp_caption($agreement, $items),
    'asDocument' => true,
];
$sent = 0;
$failed = [];
foreach ($chatIds as $chatId) {
    $payload = $basePayload;
    $payload['chatId'] = $chatId;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $settings['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
        $sent++;
    } else {
        $failed[] = [
            'chat_id' => $chatId,
            'status' => $statusCode,
            'message' => $curlError !== '' ? $curlError : 'WaAPI status: ' . $statusCode,
            'response' => $response,
        ];
    }
}

if ($sent === 0) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'WhatsApp send failed for all mobile numbers.',
        'failed' => $failed,
    ]);
    exit;
}

echo json_encode([
    'status' => $failed ? 'partial' : 'success',
    'message' => $failed
        ? 'Agreement sent to ' . $sent . ' number(s). Failed: ' . count($failed) . '.'
        : 'Agreement sent to ' . $sent . ' number(s) on WhatsApp.',
    'sent' => $sent,
    'failed' => $failed,
]);
?>
