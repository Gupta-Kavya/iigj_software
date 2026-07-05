<?php
function toast_redirect_page($message, $redirectUrl, $type = 'success')
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeRedirect = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');
    $safeType = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="2;url=' . $safeRedirect . '">';
    echo '<link href="../css/app-toast.css" rel="stylesheet">';
    echo '<link href="../css/font-awesome.min.css" rel="stylesheet">';
    echo '</head><body style="background:#fafafa;margin:0;">';
    echo '<script src="../js/app-toast.js"></script>';
    echo '<script>AppToast.' . $safeType . '("' . $safeMessage . '");';
    echo 'setTimeout(function(){ window.location.href = "' . $safeRedirect . '"; }, 1500);</script>';
    echo '</body></html>';
}
