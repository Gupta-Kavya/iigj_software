<?php
if (defined('IIGJ_ERROR_BOOTSTRAP_LOADED')) {
    return;
}
define('IIGJ_ERROR_BOOTSTRAP_LOADED', true);

$errorLogDir = dirname(__DIR__) . '/tmp';
if (!is_dir($errorLogDir)) {
    @mkdir($errorLogDir, 0775, true);
}
$errorLogFile = $errorLogDir . '/app-error.log';

ini_set('log_errors', '1');
ini_set('error_log', $errorLogFile);
ini_set('display_errors', '0');

set_exception_handler(function ($exception) {
    error_log('Uncaught exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    http_response_code(500);
    echo 'Application error. Please check server error log.';
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) $error['type'], $fatalTypes, true)) {
        return;
    }
    error_log('Fatal error: ' . ($error['message'] ?? '') . ' in ' . ($error['file'] ?? '') . ':' . ($error['line'] ?? ''));
});
