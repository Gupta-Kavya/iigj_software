<?php
require_once 'user_branch_helper.php';

function agreement_index_exists($conn, $table, $index)
{
    $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
    $index = $conn->real_escape_string((string) $index);
    $result = @$conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");
    return $result && $result->num_rows > 0;
}

function agreement_table_ready($conn)
{
    $ready = (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_stone_agreements` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `agreement_no` int(11) NOT NULL,
        `docket_no` varchar(60) DEFAULT NULL,
        `customer_name` varchar(160) NOT NULL,
        `depositor_name` varchar(160) DEFAULT NULL,
        `member_status` varchar(20) DEFAULT NULL,
        `mou_cdc` varchar(120) DEFAULT NULL,
        `category` varchar(80) DEFAULT NULL,
        `gst_no` varchar(40) DEFAULT NULL,
        `address` text,
        `mobile_no` varchar(40) DEFAULT NULL,
        `email` varchar(120) DEFAULT NULL,
        `id_no` varchar(80) DEFAULT NULL,
        `agreement_date` date DEFAULT NULL,
        `agreement_time` varchar(20) DEFAULT NULL,
        `delivery_date` date DEFAULT NULL,
        `delivery_time` varchar(20) DEFAULT NULL,
        `delivered` tinyint(1) NOT NULL DEFAULT 0,
        `items_json` longtext,
        `pcs_total` int(11) NOT NULL DEFAULT 0,
        `testing_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
        `payment_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
        `payment_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
        `payment_neft` decimal(12,2) NOT NULL DEFAULT 0.00,
        `payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
        `payment_tds` decimal(12,2) NOT NULL DEFAULT 0.00,
        `cheque_no` varchar(80) DEFAULT NULL,
        `due_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `prepared_by` varchar(120) DEFAULT NULL,
        `remarks` text,
        `signature_mode` varchar(20) NOT NULL DEFAULT 'manual',
        `customer_signature` longtext,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sm_stone_agreements_user_no` (`user_id`,`agreement_no`),
        KEY `idx_sm_stone_agreements_user_created` (`user_id`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$ready) {
        return false;
    }

    $columns = [
        'signature_mode' => "ALTER TABLE `sm_stone_agreements` ADD `signature_mode` varchar(20) NOT NULL DEFAULT 'manual' AFTER `remarks`",
        'customer_signature' => "ALTER TABLE `sm_stone_agreements` ADD `customer_signature` longtext AFTER `signature_mode`",
    ];
    foreach ($columns as $column => $sql) {
        $exists = @$conn->query("SHOW COLUMNS FROM `sm_stone_agreements` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($exists && $exists->num_rows === 0) {
            @$conn->query($sql);
        }
    }

    return agreement_items_table_ready($conn) && form_master_table_ready($conn);
}

function agreement_items_table_ready($conn)
{
    $ready = (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_stone_agreement_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `agreement_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `agreement_no` int(11) NOT NULL,
        `item_order` int(11) NOT NULL DEFAULT 0,
        `ref_no` varchar(60) DEFAULT NULL,
        `category` varchar(80) DEFAULT NULL,
        `particulars` varchar(140) DEFAULT NULL,
        `color` varchar(60) DEFAULT NULL,
        `gross_wt` varchar(40) DEFAULT NULL,
        `gross_wt_unit` varchar(10) NOT NULL DEFAULT 'ct',
        `stone_wt` varchar(40) DEFAULT NULL,
        `stone_wt_unit` varchar(10) NOT NULL DEFAULT 'ct',
        `dia_wt` varchar(40) DEFAULT NULL,
        `dia_wt_unit` varchar(10) NOT NULL DEFAULT 'ct',
        `bead_length` varchar(40) DEFAULT NULL,
        `pcs` int(11) NOT NULL DEFAULT 0,
        `a4_card` varchar(20) DEFAULT NULL,
        `topup` tinyint(1) NOT NULL DEFAULT 0,
        `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
        `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
        `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sm_stone_agreement_items_order` (`agreement_id`,`item_order`),
        KEY `idx_sm_stone_agreement_items_agreement` (`agreement_id`),
        KEY `idx_sm_stone_agreement_items_user_date` (`user_id`,`agreement_no`),
        KEY `idx_sm_stone_agreement_items_ref_no` (`ref_no`),
        KEY `idx_sm_stone_agreement_items_category` (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$ready) {
        return false;
    }

    $columns = [
        'topup' => "ALTER TABLE `sm_stone_agreement_items` ADD `topup` tinyint(1) NOT NULL DEFAULT 0 AFTER `a4_card`",
        'gross_wt_unit' => "ALTER TABLE `sm_stone_agreement_items` ADD `gross_wt_unit` varchar(10) NOT NULL DEFAULT 'ct' AFTER `gross_wt`",
        'stone_wt_unit' => "ALTER TABLE `sm_stone_agreement_items` ADD `stone_wt_unit` varchar(10) NOT NULL DEFAULT 'ct' AFTER `stone_wt`",
        'dia_wt_unit' => "ALTER TABLE `sm_stone_agreement_items` ADD `dia_wt_unit` varchar(10) NOT NULL DEFAULT 'ct' AFTER `dia_wt`",
        'discount_percent' => "ALTER TABLE `sm_stone_agreement_items` ADD `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `rate`",
        'discount_amount' => "ALTER TABLE `sm_stone_agreement_items` ADD `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `discount_percent`",
    ];
    foreach ($columns as $column => $sql) {
        $exists = @$conn->query("SHOW COLUMNS FROM `sm_stone_agreement_items` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($exists && $exists->num_rows === 0) {
            @$conn->query($sql);
        }
    }

    return true;
}

function form_master_table_ready($conn)
{
    $ready = (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_form_masters` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `agreement_id` int(11) NOT NULL,
        `agreement_no` int(11) NOT NULL,
        `agreement_item_id` int(11) DEFAULT NULL,
        `item_order` int(11) NOT NULL DEFAULT 0,
        `ref_no` varchar(60) DEFAULT NULL,
        `certi_no` int(11) NOT NULL,
        `report_no` varchar(60) DEFAULT NULL,
        `category` varchar(80) DEFAULT NULL,
        `particulars` varchar(140) DEFAULT NULL,
        `color` varchar(60) DEFAULT NULL,
        `gross_wt` varchar(40) DEFAULT NULL,
        `gross_wt_unit` varchar(10) NOT NULL DEFAULT 'ct',
        `stone_wt` varchar(40) DEFAULT NULL,
        `stone_wt_unit` varchar(10) NOT NULL DEFAULT 'ct',
        `dia_wt` varchar(40) DEFAULT NULL,
        `dia_wt_unit` varchar(10) NOT NULL DEFAULT 'ct',
        `bead_length` varchar(40) DEFAULT NULL,
        `pcs` int(11) NOT NULL DEFAULT 0,
        `a4_card` varchar(20) DEFAULT NULL,
        `topup` tinyint(1) NOT NULL DEFAULT 0,
        `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
        `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `status` varchar(20) NOT NULL DEFAULT 'booked',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sm_form_masters_user_agreement_certi` (`user_id`,`agreement_no`,`certi_no`),
        UNIQUE KEY `uniq_sm_form_masters_ref` (`user_id`,`ref_no`),
        KEY `idx_sm_form_masters_agreement` (`user_id`,`agreement_no`),
        KEY `idx_sm_form_masters_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$ready) {
        return false;
    }
    $columns = [
        'gross_wt_unit' => "ALTER TABLE `sm_form_masters` ADD `gross_wt_unit` varchar(10) NOT NULL DEFAULT 'ct' AFTER `gross_wt`",
        'stone_wt_unit' => "ALTER TABLE `sm_form_masters` ADD `stone_wt_unit` varchar(10) NOT NULL DEFAULT 'ct' AFTER `stone_wt`",
        'dia_wt_unit' => "ALTER TABLE `sm_form_masters` ADD `dia_wt_unit` varchar(10) NOT NULL DEFAULT 'ct' AFTER `dia_wt`",
    ];
    foreach ($columns as $column => $sql) {
        $exists = @$conn->query("SHOW COLUMNS FROM `sm_form_masters` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($exists && $exists->num_rows === 0) {
            @$conn->query($sql);
        }
    }
    if (agreement_index_exists($conn, 'sm_form_masters', 'uniq_sm_form_masters_user_certi')) {
        if (!@$conn->query("ALTER TABLE `sm_form_masters` DROP INDEX `uniq_sm_form_masters_user_certi`")) {
            return false;
        }
    }
    if (!agreement_index_exists($conn, 'sm_form_masters', 'uniq_sm_form_masters_user_agreement_certi')) {
        if (!@$conn->query("ALTER TABLE `sm_form_masters` ADD UNIQUE KEY `uniq_sm_form_masters_user_agreement_certi` (`user_id`,`agreement_no`,`certi_no`)")) {
            return false;
        }
    }
    return true;
}

function agreement_ref_certificate_no($refNo)
{
    if (preg_match('/(\d+)$/', (string) $refNo, $match)) {
        return (int) $match[1];
    }
    return 0;
}

function agreement_ensure_form_master_for_certificate($conn, $userId, $agreementNo, $certiNo)
{
    if (!form_master_table_ready($conn) || !agreement_items_table_ready($conn)) {
        return null;
    }
    $scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
    $stmt = $conn->prepare("SELECT * FROM sm_form_masters WHERE {$scopeSql} AND agreement_no = ? AND certi_no = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ii', $agreementNo, $certiNo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return $row;
        }
    }

    $itemStmt = $conn->prepare("SELECT * FROM sm_stone_agreement_items WHERE {$scopeSql} AND agreement_no = ? ORDER BY item_order ASC, id ASC");
    if (!$itemStmt) {
        return null;
    }
    $itemStmt->bind_param('i', $agreementNo);
    $itemStmt->execute();
    $items = $itemStmt->get_result();
    $matched = null;
    while ($item = $items->fetch_assoc()) {
        if (agreement_ref_certificate_no($item['ref_no'] ?? '') === (int) $certiNo) {
            $matched = $item;
            break;
        }
    }
    $itemStmt->close();
    if (!$matched) {
        return null;
    }

    $reportNo = (string) ($matched['ref_no'] ?? '');
    $status = 'booked';
    $insert = $conn->prepare("INSERT IGNORE INTO sm_form_masters
        (user_id, agreement_id, agreement_no, agreement_item_id, item_order, ref_no, certi_no, report_no, category, particulars, color, gross_wt, gross_wt_unit, stone_wt, stone_wt_unit, dia_wt, dia_wt_unit, bead_length, pcs, a4_card, topup, rate, amount, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$insert) {
        return null;
    }
    $agreementId = (int) ($matched['agreement_id'] ?? 0);
    $agreementItemId = (int) ($matched['id'] ?? 0);
    $itemOrder = (int) ($matched['item_order'] ?? 0);
    $refNo = (string) ($matched['ref_no'] ?? '');
    $category = (string) ($matched['category'] ?? '');
    $particulars = (string) ($matched['particulars'] ?? '');
    $color = (string) ($matched['color'] ?? '');
    $grossWt = (string) ($matched['gross_wt'] ?? '');
    $grossWtUnit = agreement_weight_unit($matched['gross_wt_unit'] ?? 'ct');
    $stoneWt = (string) ($matched['stone_wt'] ?? '');
    $stoneWtUnit = agreement_weight_unit($matched['stone_wt_unit'] ?? 'ct');
    $diaWt = (string) ($matched['dia_wt'] ?? '');
    $diaWtUnit = 'ct';
    $beadLength = (string) ($matched['bead_length'] ?? '');
    $pcs = (int) ($matched['pcs'] ?? 0);
    $a4Card = (string) ($matched['a4_card'] ?? '');
    $topup = (int) ($matched['topup'] ?? 0);
    $rate = agreement_item_decimal($matched['rate'] ?? 0);
    $amount = agreement_item_decimal($matched['amount'] ?? 0);
    $insert->bind_param(
        'iiiiisisssssssssssisidds',
        $userId,
        $agreementId,
        $agreementNo,
        $agreementItemId,
        $itemOrder,
        $refNo,
        $certiNo,
        $reportNo,
        $category,
        $particulars,
        $color,
        $grossWt,
        $grossWtUnit,
        $stoneWt,
        $stoneWtUnit,
        $diaWt,
        $diaWtUnit,
        $beadLength,
        $pcs,
        $a4Card,
        $topup,
        $rate,
        $amount,
        $status
    );
    $insert->execute();
    $insert->close();

    $stmt = $conn->prepare("SELECT * FROM sm_form_masters WHERE {$scopeSql} AND agreement_no = ? AND certi_no = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $agreementNo, $certiNo);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function agreement_next_no($conn, $userId)
{
    agreement_table_ready($conn);
    $scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');
    $result = @$conn->query("SELECT MAX(agreement_no) AS last_no FROM sm_stone_agreements WHERE {$scopeSql}");
    $row = $result ? $result->fetch_assoc() : null;
    return max(1, ((int) ($row['last_no'] ?? 0)) + 1);
}

function agreement_money($value)
{
    return number_format((float) $value, 2, '.', '');
}

function agreement_mou_discount_tiers()
{
    return [
        'SILVER' => ['label' => 'Silver', 'percent' => 20.0],
        'GOLD' => ['label' => 'Gold', 'percent' => 25.0],
        'PLATINUM' => ['label' => 'Platinum', 'percent' => 30.0],
    ];
}

function agreement_mou_tier_code($value)
{
    $value = preg_replace('/[^A-Z]/', '', strtoupper(trim((string) $value)));
    if (array_key_exists($value, agreement_mou_discount_tiers())) {
        return $value;
    }
    foreach (array_keys(agreement_mou_discount_tiers()) as $code) {
        if (strpos($value, $code) !== false) {
            return $code;
        }
    }
    return '';
}

function agreement_mou_discount_percent($value)
{
    $code = agreement_mou_tier_code($value);
    $tiers = agreement_mou_discount_tiers();
    return $code !== '' ? (float) $tiers[$code]['percent'] : 0.0;
}

function agreement_mou_options($selected)
{
    $selected = agreement_mou_tier_code($selected);
    $html = '<option value="">None</option>';
    foreach (agreement_mou_discount_tiers() as $code => $tier) {
        $percent = rtrim(rtrim(number_format((float) $tier['percent'], 2, '.', ''), '0'), '.');
        $label = $tier['label'] . ' - ' . $percent . '% Discount';
        $html .= '<option value="' . agreement_h($code) . '" ' . ($code === $selected ? 'selected' : '') . '>' . agreement_h($label) . '</option>';
    }
    return $html;
}

function agreement_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function agreement_date_display($value)
{
    if (!$value || $value === '0000-00-00') return '';
    $time = strtotime((string) $value);
    return $time ? date('d-m-Y', $time) : (string) $value;
}

function agreement_decode_items($row)
{
    $items = json_decode((string) ($row['items_json'] ?? ''), true);
    return is_array($items) ? $items : [];
}

function agreement_item_decimal($value)
{
    $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
    return is_numeric($value) ? (float) $value : 0.0;
}

function agreement_weight_unit($value)
{
    $value = strtolower(trim((string) $value));
    return in_array($value, ['ct', 'gms', 'kg'], true) ? $value : 'ct';
}

function agreement_weight_display($value, $unit)
{
    $value = trim((string) $value);
    return $value === '' ? '' : trim($value . ' ' . agreement_weight_unit($unit));
}

function agreement_save_items($conn, $agreementId, $userId, $agreementNo, array $items)
{
    if (!agreement_items_table_ready($conn) || !form_master_table_ready($conn)) {
        return false;
    }

    $delete = $conn->prepare('DELETE FROM sm_stone_agreement_items WHERE agreement_id = ?');
    if (!$delete) {
        return false;
    }
    $delete->bind_param('i', $agreementId);
    if (!$delete->execute()) {
        $delete->close();
        return false;
    }
    $delete->close();

    $deleteMaster = $conn->prepare('DELETE FROM sm_form_masters WHERE agreement_id = ?');
    if (!$deleteMaster) {
        return false;
    }
    $deleteMaster->bind_param('i', $agreementId);
    if (!$deleteMaster->execute()) {
        $deleteMaster->close();
        return false;
    }
    $deleteMaster->close();

    $stmt = $conn->prepare("INSERT INTO sm_stone_agreement_items
        (agreement_id, user_id, agreement_no, item_order, ref_no, category, particulars, color, gross_wt, gross_wt_unit, stone_wt, stone_wt_unit, dia_wt, dia_wt_unit, bead_length, pcs, a4_card, topup, rate, discount_percent, discount_amount, amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $masterStmt = $conn->prepare("INSERT INTO sm_form_masters
        (user_id, agreement_id, agreement_no, agreement_item_id, item_order, ref_no, certi_no, report_no, category, particulars, color, gross_wt, gross_wt_unit, stone_wt, stone_wt_unit, dia_wt, dia_wt_unit, bead_length, pcs, a4_card, topup, rate, amount, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'booked')");
    if (!$masterStmt) {
        $stmt->close();
        return false;
    }

    foreach ($items as $index => $item) {
        $itemOrder = $index + 1;
        $refNo = substr(trim((string) ($item['ref_no'] ?? '')), 0, 60);
        $category = substr(trim((string) ($item['category'] ?? '')), 0, 80);
        $particulars = substr(trim((string) ($item['particulars'] ?? '')), 0, 140);
        $color = substr(trim((string) ($item['color'] ?? '')), 0, 60);
        $grossWt = substr(trim((string) ($item['gross_wt'] ?? '')), 0, 40);
        $grossWtUnit = agreement_weight_unit($item['gross_wt_unit'] ?? 'ct');
        $stoneWt = substr(trim((string) ($item['stone_wt'] ?? '')), 0, 40);
        $stoneWtUnit = agreement_weight_unit($item['stone_wt_unit'] ?? 'ct');
        $diaWt = substr(trim((string) ($item['dia_wt'] ?? '')), 0, 40);
        $diaWtUnit = 'ct';
        $beadLength = substr(trim((string) ($item['bead_length'] ?? '')), 0, 40);
        $pcs = max(0, (int) ($item['pcs'] ?? 0));
        $a4Card = substr(trim((string) ($item['a4_card'] ?? '')), 0, 20);
        $topup = !empty($item['topup']) ? 1 : 0;
        $rate = agreement_item_decimal($item['rate'] ?? 0);
        $discountPercent = agreement_item_decimal($item['discount_percent'] ?? 0);
        $discountAmount = agreement_item_decimal($item['discount_amount'] ?? 0);
        $amount = agreement_item_decimal($item['amount'] ?? 0);

        $stmt->bind_param(
            'iiiisssssssssssisidddd',
            $agreementId,
            $userId,
            $agreementNo,
            $itemOrder,
            $refNo,
            $category,
            $particulars,
            $color,
            $grossWt,
            $grossWtUnit,
            $stoneWt,
            $stoneWtUnit,
            $diaWt,
            $diaWtUnit,
            $beadLength,
            $pcs,
            $a4Card,
            $topup,
            $rate,
            $discountPercent,
            $discountAmount,
            $amount
        );
        if (!$stmt->execute()) {
            $masterStmt->close();
            $stmt->close();
            return false;
        }
        $agreementItemId = (int) $stmt->insert_id;
        $certiNo = agreement_ref_certificate_no($refNo);
        if ($certiNo > 0) {
            $reportNo = $refNo;
            $masterStmt->bind_param(
                'iiiiisisssssssssssisidd',
                $userId,
                $agreementId,
                $agreementNo,
                $agreementItemId,
                $itemOrder,
                $refNo,
                $certiNo,
                $reportNo,
                $category,
                $particulars,
                $color,
                $grossWt,
                $grossWtUnit,
                $stoneWt,
                $stoneWtUnit,
                $diaWt,
                $diaWtUnit,
                $beadLength,
                $pcs,
                $a4Card,
                $topup,
                $rate,
                $amount
            );
            if (!$masterStmt->execute()) {
                $masterStmt->close();
                $stmt->close();
                return false;
            }
        }
    }

    $masterStmt->close();
    $stmt->close();
    return true;
}

function agreement_get_items($conn, $agreementId, $fallbackRow = null)
{
    if (!agreement_items_table_ready($conn)) {
        return is_array($fallbackRow) ? agreement_decode_items($fallbackRow) : [];
    }

    $stmt = $conn->prepare('SELECT ref_no, category, particulars, color, gross_wt, gross_wt_unit, stone_wt, stone_wt_unit, dia_wt, dia_wt_unit, bead_length, pcs, a4_card, topup, rate, discount_percent, discount_amount, amount FROM sm_stone_agreement_items WHERE agreement_id = ? ORDER BY item_order ASC, id ASC');
    if (!$stmt) {
        return is_array($fallbackRow) ? agreement_decode_items($fallbackRow) : [];
    }
    $stmt->bind_param('i', $agreementId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $row['pcs'] = (string) (int) $row['pcs'];
        $row['rate'] = agreement_money($row['rate']);
        $row['discount_percent'] = agreement_money($row['discount_percent'] ?? 0);
        $row['discount_amount'] = agreement_money($row['discount_amount'] ?? 0);
        $row['amount'] = agreement_money($row['amount']);
        $row['gross_wt_unit'] = agreement_weight_unit($row['gross_wt_unit'] ?? 'ct');
        $row['stone_wt_unit'] = agreement_weight_unit($row['stone_wt_unit'] ?? 'ct');
        $row['dia_wt_unit'] = agreement_weight_unit($row['dia_wt_unit'] ?? 'ct');
        $items[] = $row;
    }
    $stmt->close();

    if ($items) {
        return $items;
    }

    return is_array($fallbackRow) ? agreement_decode_items($fallbackRow) : [];
}

function agreement_backfill_items_from_json($conn, $limit = 0)
{
    if (!agreement_table_ready($conn)) {
        return ['checked' => 0, 'backfilled' => 0, 'failed' => 0];
    }

    $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';
    $result = $conn->query("SELECT a.id, a.user_id, a.agreement_no, a.items_json
        FROM sm_stone_agreements a
        LEFT JOIN sm_stone_agreement_items i ON i.agreement_id = a.id
        WHERE i.id IS NULL
            AND a.items_json IS NOT NULL
            AND a.items_json <> ''
        ORDER BY a.id ASC" . $limitSql);
    if (!$result) {
        return ['checked' => 0, 'backfilled' => 0, 'failed' => 0];
    }

    $checked = 0;
    $backfilled = 0;
    $failed = 0;
    while ($agreement = $result->fetch_assoc()) {
        $checked++;
        $items = agreement_decode_items($agreement);
        if (!$items) {
            continue;
        }
        if (agreement_save_items($conn, (int) $agreement['id'], (int) $agreement['user_id'], (int) $agreement['agreement_no'], $items)) {
            $backfilled++;
        } else {
            $failed++;
        }
    }

    return ['checked' => $checked, 'backfilled' => $backfilled, 'failed' => $failed];
}
?>
