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
?>
