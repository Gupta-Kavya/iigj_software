<?php
function user_branch_location_normalize($value)
{
    return substr(preg_replace('/[^0-9A-Z_\-]/', '', strtoupper(trim((string) $value))), 0, 60);
}

function user_branch_location_default_rows()
{
    return [
        ['SITAPURA', 'Sitapura'],
        ['DELHI', 'Delhi'],
        ['COUNCIL', 'Council'],
    ];
}

function user_branch_location_ready($conn)
{
    $column = @$conn->query("SHOW COLUMNS FROM `sm_users` LIKE 'branch_location'");
    if ($column && $column->num_rows > 0) {
        $row = $column->fetch_assoc();
        if (stripos((string) ($row['Type'] ?? ''), 'varchar(60)') === false) {
            @$conn->query("ALTER TABLE `sm_users` MODIFY `branch_location` varchar(60) DEFAULT NULL");
        }
    } else {
        @$conn->query("ALTER TABLE `sm_users` ADD `branch_location` varchar(60) DEFAULT NULL AFTER `company_name`");
    }

    $ready = (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_branch_locations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(60) NOT NULL,
        `name` varchar(120) NOT NULL,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_sm_branch_locations_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if ($ready) {
        user_branch_location_seed($conn);
    }

    return $ready;
}

function user_branch_location_seed($conn)
{
    $insert = $conn->prepare('INSERT IGNORE INTO sm_branch_locations (code, name, active) VALUES (?, ?, 1)');
    if (!$insert) {
        return;
    }

    foreach (user_branch_location_default_rows() as $row) {
        [$code, $name] = $row;
        $insert->bind_param('ss', $code, $name);
        $insert->execute();
    }

    $result = @$conn->query("SELECT DISTINCT branch_location FROM sm_users WHERE branch_location IS NOT NULL AND branch_location <> ''");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $code = user_branch_location_normalize($row['branch_location'] ?? '');
            if ($code === '') {
                continue;
            }
            $name = ucwords(strtolower(str_replace(['_', '-'], ' ', $code)));
            $insert->bind_param('ss', $code, $name);
            $insert->execute();
        }
    }

    $insert->close();
}

function user_branch_locations($conn = null, $includeEmpty = true)
{
    if ($conn === null && isset($GLOBALS['conn'])) {
        $conn = $GLOBALS['conn'];
    }

    $locations = $includeEmpty ? ['' => 'Not set'] : [];
    if ($conn) {
        user_branch_location_ready($conn);
        $result = @$conn->query('SELECT code, name FROM sm_branch_locations WHERE active = 1 ORDER BY name, code');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $code = user_branch_location_normalize($row['code'] ?? '');
                if ($code !== '') {
                    $locations[$code] = trim((string) ($row['name'] ?? '')) ?: $code;
                }
            }
        }
    }

    if (count($locations) === ($includeEmpty ? 1 : 0)) {
        foreach (user_branch_location_default_rows() as $row) {
            $locations[$row[0]] = $row[1];
        }
    }

    return $locations;
}

function user_branch_location_clean($value, $conn = null)
{
    $value = user_branch_location_normalize($value);
    if ($value === '') {
        return '';
    }
    if ($conn === null && isset($GLOBALS['conn'])) {
        $conn = $GLOBALS['conn'];
    }
    if ($conn) {
        $locations = user_branch_locations($conn, false);
        return array_key_exists($value, $locations) ? $value : '';
    }
    return $value;
}

function user_branch_location_options($selected, $conn = null, $includeEmpty = true)
{
    $selected = user_branch_location_clean($selected, $conn);
    $html = '';
    foreach (user_branch_locations($conn, $includeEmpty) as $value => $label) {
        $html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" ' . ($value === $selected ? 'selected' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

function user_branch_location_label($conn, $code)
{
    $code = user_branch_location_clean($code, $conn);
    $locations = user_branch_locations($conn, false);
    return $locations[$code] ?? $code;
}

function user_branch_location_for_user($conn, $userId)
{
    user_branch_location_ready($conn);
    $stmt = $conn->prepare('SELECT branch_location, company_name FROM sm_users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $branchLocation = user_branch_location_clean($row['branch_location'] ?? '', $conn);
    if ($branchLocation !== '') {
        return $branchLocation;
    }
    $companyName = strtoupper((string) ($row['company_name'] ?? ''));
    foreach (user_branch_locations($conn, false) as $code => $label) {
        if (strpos($companyName, $code) !== false || strpos($companyName, strtoupper($label)) !== false) {
            return $code;
        }
    }
    return '';
}

function user_branch_user_ids($conn, $userId)
{
    user_branch_location_ready($conn);
    $userId = (int) $userId;
    if ($userId <= 0) {
        return [];
    }

    $location = user_branch_location_for_user($conn, $userId);
    if ($location === '') {
        return [$userId];
    }

    $stmt = $conn->prepare('SELECT id FROM sm_users WHERE branch_location = ? ORDER BY id ASC');
    if (!$stmt) {
        return [$userId];
    }
    $stmt->bind_param('s', $location);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $stmt->close();
    if (!$ids) {
        $ids[$userId] = $userId;
    }
    return array_values($ids);
}

function user_branch_scope_sql($conn, $userId, $column = 'user_id')
{
    $column = preg_replace('/[^A-Za-z0-9_\.]/', '', (string) $column);
    if ($column === '') {
        $column = 'user_id';
    }
    $ids = array_map('intval', user_branch_user_ids($conn, $userId));
    $ids = array_values(array_filter($ids, function ($id) {
        return $id > 0;
    }));
    if (!$ids) {
        $ids = [(int) $userId];
    }
    return "`" . str_replace('.', '`.`', $column) . "` IN (" . implode(',', $ids) . ")";
}

function user_branch_storage_code($conn, $userId)
{
    $location = user_branch_location_for_user($conn, $userId);
    if ($location === '') {
        return 'user_' . (int) $userId;
    }
    return 'branch_' . strtolower(user_branch_location_normalize($location));
}
?>
