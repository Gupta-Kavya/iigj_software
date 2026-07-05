<?php
require_once 'user_branch_helper.php';

function customer_master_table_ready($conn)
{
    $ready = (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_customer_master` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL DEFAULT 0,
        `customer_name` varchar(180) NOT NULL,
        `depositor_name` varchar(180) DEFAULT NULL,
        `address` text,
        `mobile_no` varchar(60) DEFAULT NULL,
        `email` varchar(160) DEFAULT NULL,
        `member_status` varchar(20) DEFAULT NULL,
        `mou_cdc` varchar(120) DEFAULT NULL,
        `id_no` varchar(100) DEFAULT NULL,
        `gst_no` varchar(60) DEFAULT NULL,
        `source_key` char(40) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sm_customer_master_source` (`source_key`),
        KEY `idx_sm_customer_master_user_name` (`user_id`,`customer_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$ready) {
        return false;
    }

    $columns = [
        'depositor_name' => "ALTER TABLE `sm_customer_master` ADD `depositor_name` varchar(180) DEFAULT NULL AFTER `customer_name`",
        'source_key' => "ALTER TABLE `sm_customer_master` ADD `source_key` char(40) DEFAULT NULL AFTER `gst_no`",
    ];
    foreach ($columns as $column => $sql) {
        $exists = @$conn->query("SHOW COLUMNS FROM `sm_customer_master` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($exists && $exists->num_rows === 0) {
            @$conn->query($sql);
        }
    }

    return true;
}

function customer_master_clean($value, $max = 255)
{
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    return mb_substr($value, 0, $max);
}

function customer_master_address($address)
{
    $lines = preg_split('/\R/', (string) $address);
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
    return customer_master_clean(implode("\n", $lines), 2000);
}

function customer_master_upsert($conn, $userId, array $data)
{
    if (!customer_master_table_ready($conn)) {
        return false;
    }

    $customerName = customer_master_clean($data['customer_name'] ?? '', 180);
    if ($customerName === '') {
        return false;
    }

    $mobileNo = customer_master_clean($data['mobile_no'] ?? '', 60);
    $email = customer_master_clean($data['email'] ?? '', 160);
    $gstNo = customer_master_clean($data['gst_no'] ?? '', 60);
    $sourceKey = $data['source_key'] ?? '';
    if ($sourceKey === '') {
        $scopeKey = user_branch_storage_code($conn, $userId);
        $sourceKey = sha1('manual|' . $scopeKey . '|' . mb_strtolower($customerName) . '|' . $mobileNo . '|' . $gstNo);
    }

    $payload = [
        'user_id' => (int) $userId,
        'customer_name' => $customerName,
        'depositor_name' => customer_master_clean($data['depositor_name'] ?? '', 180),
        'address' => customer_master_address($data['address'] ?? ''),
        'mobile_no' => $mobileNo,
        'email' => $email,
        'member_status' => in_array(($data['member_status'] ?? ''), ['Member', 'Non Member'], true) ? $data['member_status'] : 'Non Member',
        'mou_cdc' => customer_master_clean($data['mou_cdc'] ?? '', 120),
        'id_no' => customer_master_clean($data['id_no'] ?? '', 100),
        'gst_no' => $gstNo,
        'source_key' => $sourceKey,
    ];

    $stmt = $conn->prepare("INSERT INTO sm_customer_master
        (user_id, customer_name, depositor_name, address, mobile_no, email, member_status, mou_cdc, id_no, gst_no, source_key, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            depositor_name = VALUES(depositor_name),
            address = VALUES(address),
            mobile_no = VALUES(mobile_no),
            email = VALUES(email),
            member_status = VALUES(member_status),
            mou_cdc = VALUES(mou_cdc),
            id_no = VALUES(id_no),
            gst_no = VALUES(gst_no),
            updated_at = NOW()");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'issssssssss',
        $payload['user_id'],
        $payload['customer_name'],
        $payload['depositor_name'],
        $payload['address'],
        $payload['mobile_no'],
        $payload['email'],
        $payload['member_status'],
        $payload['mou_cdc'],
        $payload['id_no'],
        $payload['gst_no'],
        $payload['source_key']
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
?>
