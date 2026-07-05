<?php
function rate_master_table_ready($conn)
{
    return (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_rate_master` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category` varchar(180) DEFAULT NULL,
        `rate_code` varchar(40) DEFAULT NULL,
        `range_from` decimal(12,3) DEFAULT NULL,
        `range_to` decimal(12,3) DEFAULT NULL,
        `rate_member` decimal(12,2) NOT NULL DEFAULT 0.00,
        `rate_non_member` decimal(12,2) NOT NULL DEFAULT 0.00,
        `remark` varchar(255) DEFAULT NULL,
        `description` varchar(255) NOT NULL,
        `cdc` varchar(20) DEFAULT NULL,
        `source_key` char(40) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sm_rate_master_source` (`source_key`),
        KEY `idx_sm_rate_master_description` (`description`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function rate_master_clean($value, $max = 255)
{
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    return mb_substr($value, 0, $max);
}

function rate_master_number($value)
{
    $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
    return is_numeric($value) ? (float) $value : 0.0;
}

function rate_master_upsert($conn, array $data)
{
    if (!rate_master_table_ready($conn)) {
        return false;
    }

    $description = rate_master_clean($data['description'] ?? '', 255);
    if ($description === '') {
        return false;
    }

    $sourceKey = (string) ($data['source_key'] ?? '');
    if ($sourceKey === '') {
        $sourceKey = sha1('rate|' . $description . '|' . ($data['rate_code'] ?? ''));
    }

    $stmt = $conn->prepare("INSERT INTO sm_rate_master
        (category, rate_code, range_from, range_to, rate_member, rate_non_member, remark, description, cdc, source_key, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            category = VALUES(category),
            rate_code = VALUES(rate_code),
            range_from = VALUES(range_from),
            range_to = VALUES(range_to),
            rate_member = VALUES(rate_member),
            rate_non_member = VALUES(rate_non_member),
            remark = VALUES(remark),
            description = VALUES(description),
            cdc = VALUES(cdc),
            updated_at = NOW()");
    if (!$stmt) {
        return false;
    }

    $category = rate_master_clean($data['category'] ?? '', 180);
    $rateCode = rate_master_clean($data['rate_code'] ?? '', 40);
    $rangeFrom = rate_master_number($data['range_from'] ?? 0);
    $rangeTo = rate_master_number($data['range_to'] ?? 0);
    $rateMember = rate_master_number($data['rate_member'] ?? 0);
    $rateNonMember = rate_master_number($data['rate_non_member'] ?? 0);
    $remark = rate_master_clean($data['remark'] ?? '', 255);
    $cdc = rate_master_clean($data['cdc'] ?? '', 20);

    $stmt->bind_param(
        'ssddddssss',
        $category,
        $rateCode,
        $rangeFrom,
        $rangeTo,
        $rateMember,
        $rateNonMember,
        $remark,
        $description,
        $cdc,
        $sourceKey
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
?>
