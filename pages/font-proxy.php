<?php
require_once 'auth.php';
auth_require_login();

$fontKey = strtolower(trim((string) ($_GET['font'] ?? '')));
$arialNovaCondLight = __DIR__ . '/assets/fonts/ArialNovaCondLight.ttf';
$fonts = [
    'arialnova' => $arialNovaCondLight,
    'arialn' => $arialNovaCondLight,
    'arialnb' => $arialNovaCondLight,
];

if (!isset($fonts[$fontKey]) || !is_file($fonts[$fontKey])) {
    http_response_code(404);
    exit;
}

header('Content-Type: font/ttf');
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
readfile($fonts[$fontKey]);
exit;
