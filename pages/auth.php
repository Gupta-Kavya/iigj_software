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

function app_normalize_gst($gst)
{
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $gst));
}

function app_valid_gst($gst)
{
    $gst = app_normalize_gst($gst);
    return $gst === '' || (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gst);
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
