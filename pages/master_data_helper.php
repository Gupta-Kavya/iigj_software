<?php

if (!function_exists('master_shared_user_id')) {
    function master_shared_user_id()
    {
        return 1;
    }
}

if (!function_exists('master_scope_user_ids')) {
    function master_scope_user_ids($userId)
    {
        $userId = (int) $userId;
        if (isset($GLOBALS['conn'])) {
            require_once 'user_branch_helper.php';
            return array_values(array_unique(array_merge(user_branch_user_ids($GLOBALS['conn'], $userId), [master_shared_user_id()])));
        }
        $sharedUserId = master_shared_user_id();
        if ($userId === $sharedUserId) {
            return [$sharedUserId];
        }
        return [$userId, $sharedUserId];
    }
}

if (!function_exists('master_scope_sql')) {
    function master_scope_sql($userId, $column = 'user_id')
    {
        $userId = (int) $userId;
        $sharedUserId = master_shared_user_id();
        $column = preg_replace('/[^A-Za-z0-9_]/', '', (string) $column);
        if ($column === '') {
            $column = 'user_id';
        }
        if (isset($GLOBALS['conn'])) {
            require_once 'user_branch_helper.php';
            $ids = array_values(array_unique(array_merge(user_branch_user_ids($GLOBALS['conn'], $userId), [$sharedUserId])));
            $ids = implode(',', array_map('intval', $ids));
            return "`{$column}` IN ({$ids})";
        }
        if ($userId === $sharedUserId) {
            return "`{$column}` = {$sharedUserId}";
        }
        return "(`{$column}` = {$userId} OR `{$column}` = {$sharedUserId})";
    }
}

if (!function_exists('master_can_manage_owner')) {
    function master_can_manage_owner($currentUserId, $rowUserId)
    {
        return (int) $currentUserId === (int) $rowUserId;
    }
}

if (!function_exists('master_fetch_rows')) {
    function master_fetch_rows($conn, $table, array $columns, $userId, array $dedupeColumns = [], $sortColumn = '')
    {
        $allowedTables = [
            'sm_master_stone_name',
            'sm_master_shape_cut',
            'sm_master_colour',
            'sm_master_ri',
            'sm_master_magnification',
        ];
        if (!in_array($table, $allowedTables, true)) {
            return [];
        }

        $cleanColumns = [];
        foreach ($columns as $column) {
            $column = preg_replace('/[^A-Za-z0-9_]/', '', (string) $column);
            if ($column !== '') {
                $cleanColumns[] = $column;
            }
        }
        if (!$cleanColumns) {
            return [];
        }

        $sortColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string) $sortColumn);
        if ($sortColumn === '') {
            $sortColumn = $cleanColumns[0];
        }

        $selectColumns = array_unique(array_merge(['id', 'user_id'], $cleanColumns));
        $quoted = array_map(function ($column) {
            return $column === 'group' ? "`group`" : "`{$column}`";
        }, $selectColumns);

        $userId = (int) $userId;
        $sql = "SELECT " . implode(', ', $quoted) . " FROM `{$table}` WHERE " . master_scope_sql($userId)
            . " ORDER BY CASE WHEN `user_id` = {$userId} THEN 0 ELSE 1 END, `{$sortColumn}` ASC, `id` ASC";
        $result = $conn->query($sql);
        if (!$result) {
            return [];
        }

        $rows = [];
        $seen = [];
        while ($row = $result->fetch_assoc()) {
            if ($dedupeColumns) {
                $parts = [];
                foreach ($dedupeColumns as $column) {
                    $column = preg_replace('/[^A-Za-z0-9_]/', '', (string) $column);
                    $value = trim((string) ($row[$column] ?? ''));
                    $parts[] = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
                }
                $dedupeKey = implode('|', $parts);
                if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                    continue;
                }
                if ($dedupeKey !== '') {
                    $seen[$dedupeKey] = true;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
