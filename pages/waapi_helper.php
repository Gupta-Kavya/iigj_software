<?php
function waapi_settings_table_ready($conn)
{
    return (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_waapi_settings` (
        `id` tinyint(1) NOT NULL DEFAULT 1,
        `instance_id` varchar(40) DEFAULT NULL,
        `api_key` text,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function waapi_get_settings($conn)
{
    waapi_settings_table_ready($conn);
    $settings = ['instance_id' => '', 'api_key' => ''];
    $result = $conn->query('SELECT instance_id, api_key FROM sm_waapi_settings WHERE id = 1 LIMIT 1');
    if ($result && ($row = $result->fetch_assoc())) {
        $settings['instance_id'] = trim((string) ($row['instance_id'] ?? ''));
        $settings['api_key'] = trim((string) ($row['api_key'] ?? ''));
    }
    return $settings;
}

function waapi_save_settings($conn, $instanceId, $apiKey)
{
    waapi_settings_table_ready($conn);
    $instanceId = preg_replace('/[^0-9]/', '', (string) $instanceId);
    $apiKey = trim((string) $apiKey);
    $stmt = $conn->prepare('INSERT INTO sm_waapi_settings (id, instance_id, api_key, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE instance_id = VALUES(instance_id), api_key = VALUES(api_key), updated_at = NOW()');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $instanceId, $apiKey);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function waapi_chat_id_from_phone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    if ($digits === '') {
        return '';
    }
    return $digits . '@c.us';
}

function waapi_chat_ids_from_mobile_field($value)
{
    $parts = preg_split('/[,;|]+/', (string) $value);
    $chatIds = [];
    foreach ($parts as $part) {
        $chatId = waapi_chat_id_from_phone($part);
        if ($chatId !== '') {
            $chatIds[$chatId] = true;
        }
    }
    return array_keys($chatIds);
}

function waapi_send_text_message($conn, $chatIds, $message)
{
    $settings = waapi_get_settings($conn);
    if ($settings['instance_id'] === '' || $settings['api_key'] === '') {
        return ['ok' => false, 'sent' => 0, 'failed' => [], 'message' => 'WhatsApp API settings are missing. Add Instance ID and API key in Super Admin.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'sent' => 0, 'failed' => [], 'message' => 'PHP cURL extension is required to send WhatsApp messages.'];
    }

    $chatIds = is_array($chatIds) ? array_values(array_unique(array_filter($chatIds))) : [];
    if (!$chatIds) {
        return ['ok' => false, 'sent' => 0, 'failed' => [], 'message' => 'Customer mobile number is missing.'];
    }

    $url = 'https://waapi.app/api/v1/instances/' . rawurlencode($settings['instance_id']) . '/client/action/send-message';
    $sent = 0;
    $failed = [];
    foreach ($chatIds as $chatId) {
        $payload = [
            'chatId' => $chatId,
            'message' => (string) $message,
            'previewLink' => false,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $settings['api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 35,
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

    return [
        'ok' => $sent > 0,
        'sent' => $sent,
        'failed' => $failed,
        'message' => $sent > 0
            ? ($failed ? 'WhatsApp sent to ' . $sent . ' number(s). Failed: ' . count($failed) . '.' : 'WhatsApp sent to ' . $sent . ' number(s).')
            : 'WhatsApp send failed for all mobile numbers.',
    ];
}
?>
