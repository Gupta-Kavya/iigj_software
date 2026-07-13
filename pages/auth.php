<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('SMARTLINK_TERMS_VERSION')) {
    define('SMARTLINK_TERMS_VERSION', '2026-06-25');
}

function auth_is_logged_in()
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function auth_current_user_name()
{
    return isset($_SESSION['user_name']) && $_SESSION['user_name'] !== ''
        ? $_SESSION['user_name']
        : 'User';
}

function auth_current_user_id()
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

function auth_current_user_role()
{
    return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : 'user';
}

function auth_client_ip()
{
    $ips = auth_client_ip_candidates();
    return $ips[0] ?? trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function auth_client_ip_candidates()
{
    $candidates = [];
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $header) {
        $value = trim((string) ($_SERVER[$header] ?? ''));
        if ($value !== '') $candidates[] = $value;
    }

    $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwardedFor !== '') {
        foreach (explode(',', $forwardedFor) as $part) {
            $value = trim($part);
            if ($value !== '') $candidates[] = $value;
        }
    }

    $valid = [];
    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, $valid, true)) {
            $valid[] = $candidate;
        }
    }
    return $valid;
}

function auth_allowed_ip_table_ready($conn)
{
    if (!$conn) return false;
    return (bool) @$conn->query("CREATE TABLE IF NOT EXISTS `sm_allowed_ips` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `branch_location` varchar(60) DEFAULT 'ALL',
        `ip_pattern` varchar(80) NOT NULL,
        `label` varchar(150) DEFAULT NULL,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_sm_allowed_ips_scope` (`branch_location`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function auth_ip_pattern_matches($clientIp, $pattern)
{
    $clientIp = trim((string) $clientIp);
    $pattern = trim((string) $pattern);
    if ($clientIp === '' || $pattern === '') return false;
    if ($pattern === '*') return true;
    if (strcasecmp($clientIp, $pattern) === 0) return true;
    if (strpos($pattern, '*') !== false) {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
        return (bool) preg_match($regex, $clientIp);
    }
    if (strpos($pattern, '/') !== false && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        [$subnet, $bits] = array_pad(explode('/', $pattern, 2), 2, null);
        $bits = (int) $bits;
        if ($bits >= 0 && $bits <= 32 && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
            return ((ip2long($clientIp) & $mask) === (ip2long($subnet) & $mask));
        }
    }
    return false;
}

function auth_allowed_ip_status($conn, $branchLocation = '', $role = '')
{
    $clientIp = auth_client_ip();
    $clientIps = auth_client_ip_candidates();
    if (!$clientIps && $clientIp !== '') $clientIps = [$clientIp];
    if (!auth_allowed_ip_table_ready($conn)) {
        return ['allowed' => true, 'ip' => $clientIp, 'message' => 'IP security table is not available.'];
    }

    $countResult = @$conn->query("SELECT COUNT(*) AS total FROM sm_allowed_ips WHERE active = 1");
    $activeCount = $countResult ? (int) ($countResult->fetch_assoc()['total'] ?? 0) : 0;
    if ($activeCount === 0) {
        return ['allowed' => true, 'ip' => $clientIp, 'message' => 'No allowed IP rules configured.'];
    }

    $branchLocation = strtoupper(preg_replace('/[^A-Z0-9_\-]/i', '', (string) $branchLocation));
    $scopes = ['ALL'];
    if ($branchLocation !== '') $scopes[] = $branchLocation;
    if ((string) $role === 'super_admin') $scopes[] = 'SUPER_ADMIN';
    $scopes = array_values(array_unique($scopes));

    $placeholders = implode(',', array_fill(0, count($scopes), '?'));
    $sql = "SELECT branch_location, ip_pattern, label FROM sm_allowed_ips WHERE active = 1 AND UPPER(branch_location) IN ({$placeholders})";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['allowed' => false, 'ip' => $clientIp, 'message' => 'Unable to verify allowed IP address.'];
    }
    $types = str_repeat('s', count($scopes));
    $refs = [$types];
    foreach ($scopes as $key => &$scope) $refs[] = &$scope;
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach ($clientIps as $candidateIp) {
            if (auth_ip_pattern_matches($candidateIp, $row['ip_pattern'] ?? '')) {
                $stmt->close();
                return ['allowed' => true, 'ip' => $clientIp, 'matched_ip' => $candidateIp, 'message' => 'Allowed IP matched.'];
            }
        }
    }
    $stmt->close();

    return [
        'allowed' => false,
        'ip' => $clientIp,
        'message' => 'Access denied from this IP address. Contact super admin.',
    ];
}

function auth_render_ip_block($status)
{
    http_response_code(403);
    $ip = htmlspecialchars((string) ($status['ip'] ?? ''), ENT_QUOTES, 'UTF-8');
    $ipList = htmlspecialchars(implode(', ', auth_client_ip_candidates()), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars((string) ($status['message'] ?? 'Access denied.'), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access Denied</title><style>body{align-items:center;background:#f7f7f8;color:#171717;display:flex;font-family:Arial,sans-serif;justify-content:center;margin:0;min-height:100vh}.box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 18px 46px rgba(15,23,42,.10);max-width:480px;padding:30px;text-align:center}.box h1{font-size:22px;margin:0 0 10px}.box p{color:#525252;line-height:1.5;margin:0 0 12px}.ip{background:#f3f4f6;border-radius:8px;font-weight:700;margin-top:8px;padding:10px}.small{color:#737373;font-size:12px;line-height:1.4;margin-top:8px;word-break:break-word}</style></head><body><div class="box"><h1>Access denied</h1><p>' . $message . '</p><div class="ip">Primary IP: ' . $ip . '</div><div class="small">Detected IPs: ' . ($ipList !== '' ? $ipList : 'Not available') . '</div></div></body></html>';
    exit;
}

function auth_enforce_allowed_ip($conn)
{
    if (!auth_is_logged_in()) return;
    $branchLocation = $_SESSION['branch_location'] ?? '';
    $role = auth_current_user_role();
    if ($branchLocation === '') {
        $stmt = @$conn->prepare('SELECT branch_location, role FROM sm_users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $userId = auth_current_user_id();
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $_SESSION['branch_location'] = (string) ($row['branch_location'] ?? '');
                $_SESSION['user_role'] = (string) ($row['role'] ?? $role);
                $branchLocation = $_SESSION['branch_location'];
                $role = $_SESSION['user_role'];
            }
        }
    }
    $status = auth_allowed_ip_status($conn, $branchLocation, $role);
    if (empty($status['allowed'])) {
        auth_render_ip_block($status);
    }
}

function auth_block_demo_action($subject = 'This action', $redirect = 'index.php', $expectsJson = false)
{
    return false;
}

function auth_is_super_admin()
{
    return auth_is_logged_in() && auth_current_user_role() === 'super_admin';
}

function auth_require_super_admin()
{
    auth_require_login();
    if (!auth_is_super_admin()) {
        http_response_code(403);
        header('Location: index.php');
        exit;
    }
}

function auth_safe_next($next, $fallback = 'index.php')
{
    $next = trim((string) $next);
    if (
        $next === '' ||
        strpos($next, "\n") !== false ||
        strpos($next, "\r") !== false ||
        preg_match('/^https?:\/\//i', $next) ||
        strpos($next, '//') === 0 ||
        strpos($next, 'login.php') !== false ||
        strpos($next, 'signup.php') !== false ||
        strpos($next, 'terms.php') !== false
    ) {
        return $fallback;
    }
    return $next;
}

function auth_terms_accepted()
{
    return isset($_SESSION['terms_version']) && hash_equals(SMARTLINK_TERMS_VERSION, (string) $_SESSION['terms_version']);
}

function auth_require_login()
{
    if (auth_is_logged_in()) {
        if (
            !defined('AUTH_ALLOW_TERMS_PENDING') &&
            !auth_terms_accepted()
        ) {
            $next = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'index.php';
            header('Location: terms.php?next=' . urlencode(auth_safe_next($next)));
            exit;
        }
        return;
    }

    $next = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'index.php';
    header('Location: login.php?next=' . urlencode($next));
    exit;
}
?>
