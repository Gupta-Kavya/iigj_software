<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'rate_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

if (!rate_master_table_ready($conn)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Rate master is not ready.']);
    exit;
}

$description = rate_master_clean($_POST['description'] ?? '', 255);
$category = rate_master_clean($_POST['category'] ?? '', 180);
if ($category === '') {
    $category = rate_master_clean($description, 180);
}
$rateMember = rate_master_number($_POST['rate_member'] ?? 0);
$rateNonMember = rate_master_number($_POST['rate_non_member'] ?? 0);
$cdc = strtoupper(trim((string) ($_POST['cdc'] ?? ''))) === 'Y' ? 'Y' : 'N';
$remark = rate_master_clean($_POST['remark'] ?? '', 255);

if ($description === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Enter category name.']);
    exit;
}

$max = 0;
$result = @$conn->query("SELECT MAX(CAST(rate_code AS UNSIGNED)) AS max_code FROM sm_rate_master WHERE rate_code REGEXP '^[0-9]+$'");
if ($result) {
    $max = (int) ($result->fetch_assoc()['max_code'] ?? 0);
}
$rateCode = (string) ($max + 1);

$sourceKey = sha1('manual-rate|' . strtoupper($description) . '|' . strtoupper($rateCode));
$ok = rate_master_upsert($conn, [
    'category' => $category,
    'rate_code' => $rateCode,
    'rate_member' => $rateMember,
    'rate_non_member' => $rateNonMember,
    'description' => $description,
    'cdc' => $cdc,
    'remark' => $remark,
    'source_key' => $sourceKey,
]);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save category.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, category, rate_code, rate_member, rate_non_member, description, cdc FROM sm_rate_master WHERE source_key = ? LIMIT 1');
$stmt->bind_param('s', $sourceKey);
$stmt->execute();
$rate = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Category saved.',
    'rate' => [
        'id' => (int) ($rate['id'] ?? 0),
        'category' => (string) ($rate['category'] ?? $category),
        'rate_code' => (string) ($rate['rate_code'] ?? $rateCode),
        'description' => (string) ($rate['description'] ?? $description),
        'rate_member' => (float) ($rate['rate_member'] ?? $rateMember),
        'rate_non_member' => (float) ($rate['rate_non_member'] ?? $rateNonMember),
        'cdc' => (string) ($rate['cdc'] ?? $cdc),
    ],
]);
?>
