<?php
function rate_condition_types()
{
    return [
        'minimum_pcs' => 'Minimum PCS validation',
        'minimum_bead_length' => 'Minimum bead length validation',
        'amount_pcs_rate' => 'Amount = PCS x Rate',
        'amount_bead_length_10' => 'Amount = Rate x Bead Length / 10',
        'amount_dia_weight' => 'Amount = Rate x Diamond Weight',
        'amount_min_dia_weight' => 'Amount = Rate x minimum diamond weight',
        'minimum_amount_fixed' => 'Minimum amount fixed',
        'minimum_amount_rate' => 'Minimum amount becomes rate',
        'diamond_topup_after_first' => 'Diamond top-up: base rate + extra weight slabs',
    ];
}

function rate_condition_table_ready($conn)
{
    $ready = (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_rate_conditions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `rate_code` varchar(40) NOT NULL,
        `branch_location` varchar(30) NOT NULL DEFAULT 'ALL',
        `rule_type` varchar(60) NOT NULL,
        `value1` decimal(12,3) NOT NULL DEFAULT 0.000,
        `value2` decimal(12,3) NOT NULL DEFAULT 0.000,
        `priority` int(11) NOT NULL DEFAULT 100,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `notes` varchar(255) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_sm_rate_conditions_lookup` (`rate_code`,`branch_location`,`active`,`priority`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if ($ready) {
        rate_condition_seed_defaults($conn);
        rate_condition_seed_missing_defaults($conn);
    }

    return $ready;
}

function rate_condition_seed_defaults($conn)
{
    $count = $conn->query('SELECT COUNT(*) AS total FROM sm_rate_conditions');
    $total = $count ? (int) ($count->fetch_assoc()['total'] ?? 0) : 0;
    if ($total > 0) {
        return;
    }

    $defaults = [];
    foreach ([25, 26, 27, 28, 29, 30] as $code) {
        $defaults[] = [$code, 'ALL', 'minimum_pcs', 5, 0, 10, 'Packet lot minimum quantity'];
    }
    foreach ([1, 2] as $code) {
        $defaults[] = [$code, 'ALL', 'minimum_bead_length', 10, 0, 15, 'Bead string minimum length'];
        $defaults[] = [$code, 'ALL', 'amount_bead_length_10', 0, 0, 20, 'Bead string length calculation'];
    }
    foreach (['SITAPURA', 'COUNCIL'] as $location) {
        $defaults[] = [32, $location, 'amount_min_dia_weight', 10, 0, 20, 'Minimum 10 ct diamond packet screening'];
    }
    $defaults[] = [32, 'DELHI', 'amount_dia_weight', 0, 0, 20, 'Delhi diamond packet screening'];
    $defaults[] = [32, 'DELHI', 'minimum_amount_rate', 200, 0, 30, 'Delhi minimum charge uses rate'];
    $defaults[] = [31, 'ALL', 'amount_dia_weight', 0, 0, 20, 'Diamond packet screening polki cut'];
    $defaults[] = [22, 'ALL', 'diamond_topup_after_first', 50, 0, 30, 'Extra top-up for diamond'];
    $defaults[] = [21, 'ALL', 'amount_dia_weight', 0, 0, 20, 'Mounted jewellery grading'];
    $defaults[] = [21, 'ALL', 'minimum_amount_fixed', 300, 0, 30, 'Minimum charge'];
    $defaults[] = [57, 'ALL', 'amount_dia_weight', 0, 0, 20, 'Mounted jewellery grading'];
    $defaults[] = [57, 'ALL', 'minimum_amount_rate', 0, 0, 30, 'Minimum charge equals rate'];

    $stmt = $conn->prepare('INSERT INTO sm_rate_conditions (rate_code, branch_location, rule_type, value1, value2, priority, active, notes) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
    if (!$stmt) {
        return;
    }
    foreach ($defaults as $rule) {
        [$rateCode, $branchLocation, $ruleType, $value1, $value2, $priority, $notes] = $rule;
        $rateCode = (string) $rateCode;
        $stmt->bind_param('sssddis', $rateCode, $branchLocation, $ruleType, $value1, $value2, $priority, $notes);
        $stmt->execute();
    }
    $stmt->close();
}

function rate_condition_seed_missing_defaults($conn)
{
    $defaults = [];
    foreach ([1, 2] as $code) {
        $defaults[] = [(string) $code, 'ALL', 'minimum_bead_length', 10, 0, 15, 'Bead string minimum length'];
    }

    $check = $conn->prepare('SELECT id FROM sm_rate_conditions WHERE rate_code = ? AND branch_location = ? AND rule_type = ? LIMIT 1');
    $insert = $conn->prepare('INSERT INTO sm_rate_conditions (rate_code, branch_location, rule_type, value1, value2, priority, active, notes) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
    if (!$check || !$insert) {
        if ($check) $check->close();
        if ($insert) $insert->close();
        return;
    }

    foreach ($defaults as $rule) {
        [$rateCode, $branchLocation, $ruleType, $value1, $value2, $priority, $notes] = $rule;
        $check->bind_param('sss', $rateCode, $branchLocation, $ruleType);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        if ($exists) {
            continue;
        }
        $insert->bind_param('sssddis', $rateCode, $branchLocation, $ruleType, $value1, $value2, $priority, $notes);
        $insert->execute();
    }

    $check->close();
    $insert->close();
}

function rate_condition_number($value)
{
    $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
    return is_numeric($value) ? (float) $value : 0.0;
}

function rate_condition_code($value)
{
    return preg_replace('/[^0-9A-Za-z_\-]/', '', strtoupper(trim((string) $value)));
}

function rate_condition_branch($value)
{
    $value = strtoupper(trim((string) $value));
    if ($value === 'ALL') {
        return 'ALL';
    }
    if (function_exists('user_branch_location_normalize')) {
        $value = user_branch_location_normalize($value);
    } else {
        $value = preg_replace('/[^0-9A-Z_\-]/', '', $value);
    }
    return $value !== '' ? $value : 'ALL';
}

function rate_condition_list($conn, $activeOnly = false)
{
    rate_condition_table_ready($conn);
    $where = $activeOnly ? ' WHERE active = 1' : '';
    $result = $conn->query("SELECT * FROM sm_rate_conditions{$where} ORDER BY CAST(rate_code AS UNSIGNED), rate_code, branch_location, priority, id");
    $rules = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['value1'] = (float) $row['value1'];
            $row['value2'] = (float) $row['value2'];
            $row['priority'] = (int) $row['priority'];
            $row['active'] = (int) $row['active'];
            $rules[] = $row;
        }
    }
    return $rules;
}

function rate_condition_matching_rules(array $rules, $rateCode, $branchLocation)
{
    $rateCode = rate_condition_code($rateCode);
    $branchLocation = rate_condition_branch($branchLocation);
    return array_values(array_filter($rules, function ($rule) use ($rateCode, $branchLocation) {
        if ((int) ($rule['active'] ?? 0) !== 1) return false;
        if (rate_condition_code($rule['rate_code'] ?? '') !== $rateCode) return false;
        $ruleLocation = rate_condition_branch($rule['branch_location'] ?? 'ALL');
        return $ruleLocation === 'ALL' || $ruleLocation === $branchLocation;
    }));
}

function rate_condition_calculate_amount(array $item, array $rate, $branchLocation, array $rules)
{
    $rateCode = rate_condition_code($rate['rate_code'] ?? '');
    $rateValue = rate_condition_number($item['rate'] ?? 0);
    $pcs = rate_condition_number($item['pcs'] ?? 0);
    $chargeablePcs = max(1, $pcs);
    $diaWt = rate_condition_number($item['dia_wt'] ?? 0);
    $beadLength = rate_condition_number($item['bead_length'] ?? 0);
    $chargeableBeadLength = $beadLength;
    $amount = $chargeablePcs * $rateValue;
    $warning = '';
    $topupSelected = !empty($item['topup']);

    foreach (rate_condition_matching_rules($rules, $rateCode, $branchLocation) as $rule) {
        $value1 = rate_condition_number($rule['value1'] ?? 0);
        switch ($rule['rule_type']) {
            case 'minimum_pcs':
                if ($pcs < $value1) {
                    $warning = 'Minimum Qty For Packet Lot is ' . (int) $value1 . '.';
                }
                break;
            case 'minimum_bead_length':
                if ($beadLength < $value1) {
                    $warning = 'Minimum bead length is ' . rtrim(rtrim(number_format($value1, 3, '.', ''), '0'), '.') . '.';
                    $chargeableBeadLength = $value1;
                }
                break;
            case 'amount_pcs_rate':
                $amount = $chargeablePcs * $rateValue;
                break;
            case 'amount_bead_length_10':
                $amount = $rateValue * ($chargeableBeadLength / 10);
                break;
            case 'amount_dia_weight':
                $amount = $rateValue * $diaWt;
                break;
            case 'amount_min_dia_weight':
                $amount = $rateValue * max($diaWt, $value1);
                break;
            case 'minimum_amount_fixed':
                if ($amount < $value1) $amount = $value1;
                break;
            case 'minimum_amount_rate':
                if ($amount < $value1) $amount = $rateValue;
                break;
            case 'diamond_topup_after_first':
                if (!$topupSelected) break;
                $wholeCarats = (int) floor($diaWt);
                $fraction = $diaWt - $wholeCarats;
                $extraAmount = $fraction == 0.0 ? ($wholeCarats - 1) * $value1 : $wholeCarats * $value1;
                $amount += max(0, $extraAmount);
                break;
        }
    }

    return ['amount' => max(0, $amount), 'warning' => $warning];
}
?>
