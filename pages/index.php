<?php
require_once 'db_connect.php';
require_once 'atm_config.php';
require_once 'agreement_helper.php';

date_default_timezone_set('Asia/Kolkata');

$userId = auth_current_user_id();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$dailyStart = date('Y-m-d', strtotime('-13 days'));
$monthlyStart = date('Y-m-01', strtotime('-5 months'));
$dateExpr = "COALESCE(NULLIF(DATE(`date`), '0000-00-00'), DATE(STR_TO_DATE(`date`, '%Y-%m-%d')), DATE(STR_TO_DATE(`date`, '%d-%m-%Y')), DATE(STR_TO_DATE(`date`, '%d/%m/%Y')), DATE(STR_TO_DATE(`date`, '%d.%m.%Y')), DATE(STR_TO_DATE(`date`, '%m/%d/%Y')))";
$branchScopeSql = user_branch_location_scope_sql($conn, $userId, 'location');
agreement_table_ready($conn);

function dash_count($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($types !== '') dash_bind_params($stmt, $types, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) array_values($row)[0] : 0;
}

function dash_rows($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types !== '') dash_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function dash_sum($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0.0;
    if ($types !== '') dash_bind_params($stmt, $types, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (float) array_values($row)[0] : 0.0;
}

function dash_table_exists($conn, $table)
{
    $table = $conn->real_escape_string((string) $table);
    $result = @$conn->query("SHOW TABLES LIKE '{$table}'");
    return $result && $result->num_rows > 0;
}

function dash_bind_params($stmt, $types, $params)
{
    $refs = [];
    $refs[] = $types;
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

function dash_report_type_label($type)
{
    $type = strtoupper(trim((string) $type));
    if ($type === 'S') return 'Colour Stone';
    if ($type === 'P') return 'Pearl';
    if ($type === 'D') return 'Diamond';
    if ($type === 'DS') return 'Diamond Screening';
    if ($type === 'J') return 'Jewellery';
    if ($type === 'R') return 'Rudraksha';
    return $type === '' ? 'Unknown' : $type;
}

function dash_num($number)
{
    return number_format((int) $number);
}

function dash_money($number)
{
    return 'Rs. ' . number_format((float) $number, 2);
}

function dash_pct($part, $total)
{
    return $total > 0 ? round(($part / $total) * 100) : 0;
}

function dash_image_count_folder($folder)
{
    $dir = atm_user_image_dir($folder);
    $count = 0;
    foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $ext) {
        $files = glob($dir . '/*.' . $ext);
        $count += is_array($files) ? count($files) : 0;
    }
    return $count;
}

function dash_stone_image_count()
{
    return dash_image_count_folder('st_images');
}

$totalReports = dash_count($conn, "SELECT COUNT(*) FROM sm_form_data WHERE {$branchScopeSql}");
$todayReports = dash_count($conn, "SELECT COUNT(*) FROM sm_form_data WHERE {$branchScopeSql} AND $dateExpr = ?", 's', [$today]);
$monthReports = dash_count($conn, "SELECT COUNT(*) FROM sm_form_data WHERE {$branchScopeSql} AND $dateExpr BETWEEN ? AND ?", 'ss', [$monthStart, $monthEnd]);
$nextCertificateInfo = atm_next_certificate_number($conn, $userId);
$nextCertificate = (int) $nextCertificateInfo['certi_no'];
$totalImages = dash_stone_image_count();

$allCertificates = dash_rows($conn, "SELECT certi_no FROM sm_form_data WHERE {$branchScopeSql}");
$missingImages = 0;
foreach ($allCertificates as $certificate) {
    $certiNo = $certificate['certi_no'];
    $hasImage = false;
    foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $ext) {
        if (is_file(atm_user_stone_dir() . '/' . $certiNo . '.' . $ext)) {
            $hasImage = true;
            break;
        }
    }
    if (!$hasImage) $missingImages++;
}
$imageCoverage = dash_pct(max(0, $totalReports - $missingImages), $totalReports);

$typeRows = dash_rows($conn, "SELECT COALESCE(NULLIF(`type`, ''), 'Unknown') AS report_type, COUNT(*) AS total FROM sm_form_data WHERE {$branchScopeSql} GROUP BY COALESCE(NULLIF(`type`, ''), 'Unknown') ORDER BY total DESC");
$stoneRows = dash_rows($conn, "SELECT COALESCE(NULLIF(stone_name, ''), 'Not specified') AS label, COUNT(*) AS total FROM sm_form_data WHERE {$branchScopeSql} GROUP BY COALESCE(NULLIF(stone_name, ''), 'Not specified') ORDER BY total DESC LIMIT 7");
$colourRows = dash_rows($conn, "SELECT COALESCE(NULLIF(color, ''), 'Not specified') AS label, COUNT(*) AS total FROM sm_form_data WHERE {$branchScopeSql} GROUP BY COALESCE(NULLIF(color, ''), 'Not specified') ORDER BY total DESC LIMIT 7");
$recentRows = dash_rows($conn, "SELECT certi_no, report_no, `date`, stone_name, stone_wt1 AS stone_wt, color, `type` FROM sm_form_data WHERE {$branchScopeSql} ORDER BY certi_no DESC LIMIT 8");

$agreementScopeSql = auth_is_super_admin() ? '1=1' : user_branch_scope_sql($conn, $userId, 'user_id');
$agreementDateExpr = "DATE(agreement_date)";
$totalAgreements = dash_count($conn, "SELECT COUNT(*) FROM sm_stone_agreements WHERE {$agreementScopeSql}");
$todayAgreements = dash_count($conn, "SELECT COUNT(*) FROM sm_stone_agreements WHERE {$agreementScopeSql} AND {$agreementDateExpr} = ?", 's', [$today]);
$monthAgreements = dash_count($conn, "SELECT COUNT(*) FROM sm_stone_agreements WHERE {$agreementScopeSql} AND {$agreementDateExpr} BETWEEN ? AND ?", 'ss', [$monthStart, $monthEnd]);
$deliveredAgreements = dash_count($conn, "SELECT COUNT(*) FROM sm_stone_agreements WHERE {$agreementScopeSql} AND agreement_status = 'DELIVERED'");
$readyAgreements = dash_count($conn, "SELECT COUNT(*) FROM sm_stone_agreements WHERE {$agreementScopeSql} AND agreement_status = 'READY_FOR_DELIVERY'");
$inProcessAgreements = dash_count($conn, "SELECT COUNT(*) FROM sm_stone_agreements WHERE {$agreementScopeSql} AND agreement_status = 'IN_PROCESS'");
$cancelledAgreements = dash_count($conn, "SELECT COUNT(*) FROM sm_stone_agreements WHERE {$agreementScopeSql} AND agreement_status = 'CANCELLED'");
$totalTestingCharges = dash_sum($conn, "SELECT COALESCE(SUM(testing_charges), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}");
$totalDueAmount = dash_sum($conn, "SELECT COALESCE(SUM(due_amount), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}");
$totalPaidAmount = dash_sum($conn, "SELECT COALESCE(SUM(payment_cash + payment_cheque + payment_neft + payment_card + payment_tds), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}");
$monthTestingCharges = dash_sum($conn, "SELECT COALESCE(SUM(testing_charges), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql} AND {$agreementDateExpr} BETWEEN ? AND ?", 'ss', [$monthStart, $monthEnd]);
$bookedStones = dash_count($conn, "SELECT COUNT(*) FROM sm_form_masters WHERE {$agreementScopeSql}");
$pendingBookedStones = dash_count($conn, "SELECT COUNT(*) FROM sm_form_masters WHERE {$agreementScopeSql} AND status = 'booked'");
$generatedBookedStones = dash_count($conn, "SELECT COUNT(*) FROM sm_form_masters WHERE {$agreementScopeSql} AND status = 'generated'");
$statusRows = dash_rows($conn, "SELECT agreement_status AS label, COUNT(*) AS total FROM sm_stone_agreements WHERE {$agreementScopeSql} GROUP BY agreement_status ORDER BY total DESC");
$bookingStatusRows = dash_rows($conn, "SELECT status AS label, COUNT(*) AS total FROM sm_form_masters WHERE {$agreementScopeSql} GROUP BY status ORDER BY total DESC");
$customerRows = dash_rows($conn, "SELECT COALESCE(NULLIF(customer_name, ''), 'Not specified') AS label, COUNT(*) AS total, COALESCE(SUM(testing_charges), 0) AS amount FROM sm_stone_agreements WHERE {$agreementScopeSql} GROUP BY COALESCE(NULLIF(customer_name, ''), 'Not specified') ORDER BY total DESC, amount DESC LIMIT 7");
$categoryRows = dash_rows($conn, "SELECT COALESCE(NULLIF(category, ''), 'Not specified') AS label, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount FROM sm_stone_agreement_items WHERE {$agreementScopeSql} GROUP BY COALESCE(NULLIF(category, ''), 'Not specified') ORDER BY total DESC, amount DESC LIMIT 8");
$paymentRows = [
    ['label' => 'Cash', 'total' => dash_sum($conn, "SELECT COALESCE(SUM(payment_cash), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}")],
    ['label' => 'Cheque', 'total' => dash_sum($conn, "SELECT COALESCE(SUM(payment_cheque), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}")],
    ['label' => 'NEFT/UPI', 'total' => dash_sum($conn, "SELECT COALESCE(SUM(payment_neft), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}")],
    ['label' => 'Card', 'total' => dash_sum($conn, "SELECT COALESCE(SUM(payment_card), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}")],
    ['label' => 'TDS', 'total' => dash_sum($conn, "SELECT COALESCE(SUM(payment_tds), 0) FROM sm_stone_agreements WHERE {$agreementScopeSql}")],
];
$imageFolderRows = [
    ['label' => 'Stone', 'total' => dash_image_count_folder('st_images')],
    ['label' => 'Symbols', 'total' => dash_image_count_folder('symbol_images')],
    ['label' => 'Clarity', 'total' => dash_image_count_folder('clarity_images')],
    ['label' => 'Proportion', 'total' => dash_image_count_folder('proportion_images')],
];
$maxCustomer = $customerRows ? max(1, max(array_map('intval', array_column($customerRows, 'total')))) : 1;
$maxCategory = $categoryRows ? max(1, max(array_map('intval', array_column($categoryRows, 'total')))) : 1;
$maxPayment = max(1, (float) max(array_column($paymentRows, 'total')));
$maxImageFolder = max(1, (int) max(array_column($imageFolderRows, 'total')));
$deliveryCompletion = dash_pct($deliveredAgreements, max(1, $totalAgreements));
$generationCompletion = dash_pct($generatedBookedStones, max(1, $bookedStones));

$dailyRaw = dash_rows($conn, "SELECT $dateExpr AS report_day, COUNT(*) AS total FROM sm_form_data WHERE {$branchScopeSql} AND $dateExpr BETWEEN ? AND ? GROUP BY $dateExpr ORDER BY report_day ASC", 'ss', [$dailyStart, $today]);
$dailyLookup = [];
foreach ($dailyRaw as $row) $dailyLookup[$row['report_day']] = (int) $row['total'];
$dailyRows = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime('-' . $i . ' days'));
    $dailyRows[] = [
        'label' => date('d M', strtotime($day)),
        'total' => isset($dailyLookup[$day]) ? $dailyLookup[$day] : 0,
    ];
}

$monthlyRaw = dash_rows($conn, "SELECT DATE_FORMAT($dateExpr, '%Y-%m') AS month_key, COUNT(*) AS total FROM sm_form_data WHERE {$branchScopeSql} AND $dateExpr >= ? GROUP BY DATE_FORMAT($dateExpr, '%Y-%m') ORDER BY month_key ASC", 's', [$monthlyStart]);
$monthlyLookup = [];
foreach ($monthlyRaw as $row) $monthlyLookup[$row['month_key']] = (int) $row['total'];
$monthlyRows = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime('-' . $i . ' months'));
    $monthlyRows[] = [
        'label' => date('M y', strtotime($key . '-01')),
        'total' => isset($monthlyLookup[$key]) ? $monthlyLookup[$key] : 0,
    ];
}

$maxDaily = max(1, max(array_column($dailyRows, 'total')));
$maxMonthly = max(1, max(array_column($monthlyRows, 'total')));
$maxStone = $stoneRows ? max(1, max(array_map('intval', array_column($stoneRows, 'total')))) : 1;

include "assets/navbar.php";
?>

<style>
.dashboard-page {
    --dash-ink: #101820;
    --dash-ink-soft: #233241;
    --dash-gold: #c8a85d;
    --dash-gold-soft: #f6f0df;
    --dash-stone: #6f7d8a;
    --dash-stone-soft: #eef2f5;
    --dash-line: #e5e9ee;
    --dash-fill: #f7f8fa;
}
.dashboard-page { padding-bottom: 22px; }
.dashboard-hero {
    border-bottom: 1px solid var(--app-border, #ececf1);
    margin: 0 0 14px;
    padding: 0 0 12px;
}
.dashboard-hero h1 { border: 0; color: var(--app-text, #171717); font-size: 21px; font-weight: 600; margin: 0 0 3px; padding: 0; }
.dashboard-hero p { color: var(--app-muted, #737373); font-size: 12px; margin: 0; max-width: 780px; }
.dash-grid { display: grid; gap: 10px; }
.metric-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.ops-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 10px; }
.chart-grid { grid-template-columns: minmax(0, 1.25fr) minmax(0, .75fr); margin-top: 10px; }
.insight-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 10px; }
.three-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 10px; }
.dash-card {
    background: #fff;
    border: 1px solid var(--dash-line);
    border-radius: 10px;
    overflow: hidden;
}
.metric-card { padding: 11px 12px; position: relative; }
.metric-card .icon {
    align-items: center;
    background: var(--dash-stone-soft);
    border-radius: 8px;
    color: var(--dash-ink-soft);
    display: flex;
    font-size: 15px;
    height: 32px;
    justify-content: center;
    margin-bottom: 8px;
    width: 32px;
}
.metric-card h3 { color: var(--dash-stone); font-size: 11px; font-weight: 600; margin: 0 0 4px; text-transform: none; }
.metric-card .value { color: var(--dash-ink); font-size: 21px; font-weight: 700; line-height: 1; }
.metric-card .hint { color: var(--dash-stone); font-size: 10.5px; margin-top: 5px; }
.blue .icon,
.green .icon,
.amber .icon,
.rose .icon,
.violet .icon,
.cyan .icon { background: var(--dash-stone-soft); color: var(--dash-ink-soft); }
.metric-card.amber .icon,
.metric-card.cyan .icon { background: var(--dash-gold-soft); color: #8a6b20; }
.card-head { border-bottom: 1px solid var(--dash-line); padding: 10px 12px; }
.card-head h3 { color: var(--dash-ink); font-size: 13px; font-weight: 700; margin: 0; }
.card-head p { color: var(--dash-stone); font-size: 10.5px; margin: 2px 0 0; }
.card-body { padding: 12px; }
.bar-chart { align-items: end; display: flex; gap: 5px; height: 160px; padding-top: 6px; }
.bar-item { align-items: center; display: flex; flex: 1; flex-direction: column; gap: 8px; height: 100%; justify-content: flex-end; min-width: 0; }
.bar {
    background: linear-gradient(180deg, var(--dash-ink-soft), var(--dash-ink));
    border-radius: 6px 6px 2px 2px;
    min-height: 6px;
    width: 100%;
}
.bar-value { color: var(--dash-ink-soft); font-size: 10px; font-weight: 700; }
.bar-label { color: var(--dash-stone); font-size: 9px; text-align: center; transform: rotate(-35deg); white-space: nowrap; }
.mini-bars .bar-chart { height: 120px; }
.progress-row { margin-bottom: 9px; }
.progress-top { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
.progress-top span:first-child { color: var(--dash-ink-soft); font-size: 11.5px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.progress-top span:last-child { color: var(--dash-stone); font-size: 10.5px; font-weight: 600; }
.progress-track { background: var(--dash-stone-soft); border-radius: 999px; height: 6px; overflow: hidden; }
.progress-fill { background: var(--dash-ink-soft); border-radius: 999px; height: 100%; }
.progress-fill.green,
.progress-fill.amber,
.progress-fill.rose,
.progress-fill.cyan { background: var(--dash-ink-soft); }
.type-list { display: grid; gap: 7px; }
.type-pill { align-items: center; background: var(--dash-fill); border: 1px solid var(--dash-line); border-radius: 8px; display: flex; font-size: 11.5px; justify-content: space-between; padding: 8px 10px; }
.type-pill strong { color: var(--dash-ink); font-weight: 600; }
.type-pill span { color: var(--dash-ink-soft); font-weight: 700; }
.status-grid { display: grid; gap: 7px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.status-card { background: var(--dash-fill); border: 1px solid var(--dash-line); border-radius: 8px; padding: 8px; }
.status-card span { color: var(--dash-stone); display: block; font-size: 10.5px; margin-bottom: 3px; }
.status-card strong { color: var(--dash-ink); display: block; font-size: 18px; line-height: 1; }
.money-grid { display: grid; gap: 8px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.money-box { border: 1px solid var(--dash-line); border-radius: 8px; padding: 9px; }
.money-box span { color: var(--dash-stone); display: block; font-size: 10.5px; margin-bottom: 4px; }
.money-box strong { color: var(--dash-ink); display: block; font-size: 14px; line-height: 1.15; overflow-wrap: anywhere; }
.money-box.due strong { color: #8a6b20; }
.split-row { align-items: center; display: grid; gap: 9px; grid-template-columns: 116px minmax(0, 1fr) 64px; margin-bottom: 8px; }
.split-row .split-label { color: var(--dash-ink-soft); font-size: 11.5px; font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.split-row .split-value { color: var(--dash-stone); font-size: 10.5px; font-weight: 700; text-align: right; }
.mini-ring-row { align-items: center; display: grid; gap: 10px; grid-template-columns: 112px minmax(0,1fr); }
.quality-ring {
    align-items: center;
    background: conic-gradient(var(--dash-gold) <?php echo $imageCoverage; ?>%, var(--dash-stone-soft) 0);
    border-radius: 50%;
    display: flex;
    height: 112px;
    justify-content: center;
    margin: 4px auto 10px;
    width: 112px;
}
.quality-ring-inner { align-items: center; background: #fff; border-radius: 50%; display: flex; flex-direction: column; height: 78px; justify-content: center; width: 78px; }
.quality-ring-inner strong { color: var(--dash-ink); font-size: 20px; font-weight: 700; }
.quality-ring-inner span { color: var(--dash-stone); font-size: 10px; }
.recent-table { margin: 0; }
.recent-table th { color: var(--dash-stone); font-size: 10.5px; font-weight: 600; text-transform: none; }
.recent-table td { color: var(--dash-ink-soft); font-size: 11.5px; vertical-align: middle !important; }
.empty-state { color: var(--dash-stone); font-size: 11.5px; padding: 14px; text-align: center; }
.quota-summary { display: grid; gap: 8px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 10px; }
.quota-box { background: var(--dash-fill); border: 1px solid var(--dash-line); border-radius: 8px; padding: 8px; }
.quota-box span { color: var(--dash-stone); display: block; font-size: 9.5px; font-weight: 700; margin-bottom: 3px; text-transform: uppercase; }
.quota-box strong { color: var(--dash-ink); display: block; font-size: 16px; line-height: 1; }
.quota-meter { margin: 4px 0 9px; }
.quota-meter .progress-track { height: 8px; }
.quota-meter .progress-fill { background: var(--dash-ink); }
.quota-note { color: var(--dash-stone); font-size: 10.5px; line-height: 1.35; margin: 0; }
.quota-warning { color: #8a6b20; font-weight: 700; }
@media (max-width: 1200px) {
    .metric-grid, .ops-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .chart-grid, .insight-grid, .three-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .metric-grid, .ops-grid, .money-grid, .status-grid, .quota-summary { grid-template-columns: 1fr; }
    .split-row, .mini-ring-row { grid-template-columns: 1fr; }
    .split-row .split-value { text-align: left; }
    .bar-label { transform: none; white-space: normal; }
}
</style>

<div id="page-wrapper">
    <div class="container-fluid dashboard-page">
        <div class="dashboard-hero">
            <h1><i class="fa fa-dashboard"></i> Laboratory Dashboard</h1>
            <p>Live summary for your account: certificate volume, image coverage, report mix, top stones and recent work.</p>
        </div>

        <div class="dash-grid metric-grid">
            <div class="dash-card metric-card blue">
                <div class="icon"><i class="fa fa-file-text-o"></i></div>
                <h3>Total Reports</h3>
                <div class="value"><?php echo dash_num($totalReports); ?></div>
                <div class="hint"><?php echo dash_num($monthReports); ?> created this month</div>
            </div>
            <div class="dash-card metric-card green">
                <div class="icon"><i class="fa fa-calendar-check-o"></i></div>
                <h3>Today</h3>
                <div class="value"><?php echo dash_num($todayReports); ?></div>
                <div class="hint">Reports entered today</div>
            </div>
            <div class="dash-card metric-card amber">
                <div class="icon"><i class="fa fa-picture-o"></i></div>
                <h3>Stone Images</h3>
                <div class="value"><?php echo dash_num($totalImages); ?></div>
                <div class="hint"><?php echo $imageCoverage; ?>% report image coverage</div>
            </div>
            <div class="dash-card metric-card rose">
                <div class="icon"><i class="fa fa-hashtag"></i></div>
                <h3>Next Cert. No.</h3>
                <div class="value"><?php echo dash_num($nextCertificate); ?></div>
                <div class="hint"><?php echo dash_num($missingImages); ?> reports missing images</div>
            </div>
        </div>

        <div class="dash-grid ops-grid">
            <div class="dash-card metric-card violet">
                <div class="icon"><i class="fa fa-file-o"></i></div>
                <h3>Total Agreements</h3>
                <div class="value"><?php echo dash_num($totalAgreements); ?></div>
                <div class="hint"><?php echo dash_num($monthAgreements); ?> this month / <?php echo dash_num($todayAgreements); ?> today</div>
            </div>
            <div class="dash-card metric-card green">
                <div class="icon"><i class="fa fa-check-square-o"></i></div>
                <h3>Generated Stones</h3>
                <div class="value"><?php echo dash_num($generatedBookedStones); ?></div>
                <div class="hint"><?php echo $generationCompletion; ?>% of booked rows generated</div>
            </div>
            <div class="dash-card metric-card amber">
                <div class="icon"><i class="fa fa-clock-o"></i></div>
                <h3>Pending Feeding</h3>
                <div class="value"><?php echo dash_num($pendingBookedStones); ?></div>
                <div class="hint">Booked rows not generated yet</div>
            </div>
            <div class="dash-card metric-card cyan">
                <div class="icon"><i class="fa fa-inr"></i></div>
                <h3>Month Testing</h3>
                <div class="value" style="font-size:17px"><?php echo dash_money($monthTestingCharges); ?></div>
                <div class="hint"><?php echo dash_money($totalTestingCharges); ?> lifetime testing charges</div>
            </div>
        </div>

        <div class="dash-grid chart-grid">
            <div class="dash-card">
                <div class="card-head">
                    <h3>Daily Report Trend</h3>
                    <p>Certificates created in the last 14 days.</p>
                </div>
                <div class="card-body">
                    <div class="bar-chart">
                        <?php foreach ($dailyRows as $row): ?>
                            <?php $height = $row['total'] > 0 ? max(6, round(($row['total'] / $maxDaily) * 100)) : 0; ?>
                            <div class="bar-item">
                                <div class="bar-value"><?php echo (int) $row['total']; ?></div>
                                <div class="bar" style="height: <?php echo $height; ?>%;"></div>
                                <div class="bar-label"><?php echo htmlspecialchars($row['label']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="dash-card">
                <div class="card-head">
                    <h3>Report Composition</h3>
                    <p>Breakdown by report type.</p>
                </div>
                <div class="card-body">
                    <?php if ($typeRows): ?>
                        <div class="type-list">
                            <?php foreach ($typeRows as $row): ?>
                                <div class="type-pill">
                                    <strong><?php echo htmlspecialchars(dash_report_type_label($row['report_type'])); ?></strong>
                                    <span><?php echo dash_num($row['total']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No report data yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dash-grid insight-grid">
            <div class="dash-card">
                <div class="card-head">
                    <h3>Top Stone Names</h3>
                    <p>Most frequently certified stones.</p>
                </div>
                <div class="card-body">
                    <?php if ($stoneRows): foreach ($stoneRows as $row): ?>
                        <div class="progress-row">
                            <div class="progress-top"><span><?php echo htmlspecialchars($row['label']); ?></span><span><?php echo dash_num($row['total']); ?></span></div>
                            <div class="progress-track"><div class="progress-fill" style="width: <?php echo round(((int)$row['total'] / $maxStone) * 100); ?>%;"></div></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">Stone analytics will appear after reports are entered.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dash-card">
                <div class="card-head">
                    <h3>Agreement Workflow</h3>
                    <p>Current agreement delivery and processing position.</p>
                </div>
                <div class="card-body">
                    <div class="mini-ring-row">
                        <div class="quality-ring" style="background:conic-gradient(var(--dash-gold) <?php echo $deliveryCompletion; ?>%, var(--dash-stone-soft) 0);">
                            <div class="quality-ring-inner">
                                <strong><?php echo $deliveryCompletion; ?>%</strong>
                                <span>delivered</span>
                            </div>
                        </div>
                        <div class="status-grid">
                            <div class="status-card"><span>In Process</span><strong><?php echo dash_num($inProcessAgreements); ?></strong></div>
                            <div class="status-card"><span>Ready</span><strong><?php echo dash_num($readyAgreements); ?></strong></div>
                            <div class="status-card"><span>Delivered</span><strong><?php echo dash_num($deliveredAgreements); ?></strong></div>
                            <div class="status-card"><span>Cancelled</span><strong><?php echo dash_num($cancelledAgreements); ?></strong></div>
                        </div>
                    </div>
                    <?php foreach ($statusRows as $row): ?>
                        <?php $statusPercent = dash_pct((int) $row['total'], max(1, $totalAgreements)); ?>
                        <div class="progress-row">
                            <div class="progress-top"><span><?php echo htmlspecialchars(agreement_status_label($row['label'])); ?></span><span><?php echo dash_num($row['total']); ?></span></div>
                            <div class="progress-track"><div class="progress-fill cyan" style="width: <?php echo $statusPercent; ?>%;"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="dash-grid three-grid">
            <div class="dash-card">
                <div class="card-head">
                    <h3>Payment Summary</h3>
                    <p>Testing amount, received amount and dues.</p>
                </div>
                <div class="card-body">
                    <div class="money-grid">
                        <div class="money-box"><span>Testing</span><strong><?php echo dash_money($totalTestingCharges); ?></strong></div>
                        <div class="money-box"><span>Received</span><strong><?php echo dash_money($totalPaidAmount); ?></strong></div>
                        <div class="money-box due"><span>Due</span><strong><?php echo dash_money($totalDueAmount); ?></strong></div>
                    </div>
                    <div style="height:8px"></div>
                    <?php foreach ($paymentRows as $row): ?>
                        <?php $paymentPercent = $row['total'] > 0 ? max(3, round(((float) $row['total'] / $maxPayment) * 100)) : 0; ?>
                        <div class="split-row">
                            <div class="split-label"><?php echo htmlspecialchars($row['label']); ?></div>
                            <div class="progress-track"><div class="progress-fill green" style="width: <?php echo $paymentPercent; ?>%;"></div></div>
                            <div class="split-value"><?php echo dash_money($row['total']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dash-card">
                <div class="card-head">
                    <h3>Booked Certificate Status</h3>
                    <p>Rows created from agreements versus generated reports.</p>
                </div>
                <div class="card-body">
                    <div class="quality-ring" style="background:conic-gradient(var(--dash-gold) <?php echo $generationCompletion; ?>%, var(--dash-stone-soft) 0);">
                        <div class="quality-ring-inner">
                            <strong><?php echo $generationCompletion; ?>%</strong>
                            <span>generated</span>
                        </div>
                    </div>
                    <div class="type-list">
                        <div class="type-pill"><strong>Total booked rows</strong><span><?php echo dash_num($bookedStones); ?></span></div>
                        <?php foreach ($bookingStatusRows as $row): ?>
                            <div class="type-pill"><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $row['label']))); ?></strong><span><?php echo dash_num($row['total']); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="dash-card">
                <div class="card-head">
                    <h3>Image Folders</h3>
                    <p>Files available for reports and builders.</p>
                </div>
                <div class="card-body">
                    <?php foreach ($imageFolderRows as $row): ?>
                        <?php $folderPercent = $row['total'] > 0 ? max(4, round(((int) $row['total'] / $maxImageFolder) * 100)) : 0; ?>
                        <div class="split-row">
                            <div class="split-label"><?php echo htmlspecialchars($row['label']); ?></div>
                            <div class="progress-track"><div class="progress-fill" style="width: <?php echo $folderPercent; ?>%;"></div></div>
                            <div class="split-value"><?php echo dash_num($row['total']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="dash-grid insight-grid">
            <div class="dash-card">
                <div class="card-head">
                    <h3>Top Customers</h3>
                    <p>Customers by agreement count and testing value.</p>
                </div>
                <div class="card-body">
                    <?php if ($customerRows): foreach ($customerRows as $row): ?>
                        <div class="progress-row">
                            <div class="progress-top"><span><?php echo htmlspecialchars($row['label']); ?></span><span><?php echo dash_num($row['total']); ?> / <?php echo dash_money($row['amount']); ?></span></div>
                            <div class="progress-track"><div class="progress-fill amber" style="width: <?php echo round(((int)$row['total'] / $maxCustomer) * 100); ?>%;"></div></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">Customer insights will appear after agreements are created.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dash-card">
                <div class="card-head">
                    <h3>Top Agreement Categories</h3>
                    <p>Most used rate categories in agreement rows.</p>
                </div>
                <div class="card-body">
                    <?php if ($categoryRows): foreach ($categoryRows as $row): ?>
                        <div class="progress-row">
                            <div class="progress-top"><span><?php echo htmlspecialchars($row['label']); ?></span><span><?php echo dash_num($row['total']); ?> / <?php echo dash_money($row['amount']); ?></span></div>
                            <div class="progress-track"><div class="progress-fill rose" style="width: <?php echo round(((int)$row['total'] / $maxCategory) * 100); ?>%;"></div></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">Category insights will appear after agreement rows are added.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dash-grid chart-grid">
            <div class="dash-card mini-bars">
                <div class="card-head">
                    <h3>Monthly Volume</h3>
                    <p>Last six months of report creation.</p>
                </div>
                <div class="card-body">
                    <div class="bar-chart">
                        <?php foreach ($monthlyRows as $row): ?>
                            <?php $height = $row['total'] > 0 ? max(6, round(($row['total'] / $maxMonthly) * 100)) : 0; ?>
                            <div class="bar-item">
                                <div class="bar-value"><?php echo (int) $row['total']; ?></div>
                                <div class="bar" style="height: <?php echo $height; ?>%;"></div>
                                <div class="bar-label"><?php echo htmlspecialchars($row['label']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="dash-card">
                <div class="card-head">
                    <h3>Image Completion</h3>
                    <p>Reports that have matching stone images.</p>
                </div>
                <div class="card-body">
                    <div class="quality-ring">
                        <div class="quality-ring-inner">
                            <strong><?php echo $imageCoverage; ?>%</strong>
                            <span>covered</span>
                        </div>
                    </div>
                    <div class="type-list">
                        <div class="type-pill"><strong>With image</strong><span><?php echo dash_num(max(0, $totalReports - $missingImages)); ?></span></div>
                        <div class="type-pill"><strong>Missing image</strong><span><?php echo dash_num($missingImages); ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dash-card" style="margin-top:10px;">
            <div class="card-head">
                <h3>Recent Certificates</h3>
                <p>Latest reports entered in this account.</p>
            </div>
            <div class="card-body">
                <?php if ($recentRows): ?>
                    <div class="table-responsive">
                        <table class="table table-hover recent-table">
                            <thead>
                                <tr>
                                    <th>Cert No</th>
                                    <th>Report No</th>
                                    <th>Date</th>
                                    <th>Stone</th>
                                    <th>Weight</th>
                                    <th>Colour</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['certi_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['report_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td><?php echo htmlspecialchars($row['stone_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['stone_wt']); ?></td>
                                        <td><?php echo htmlspecialchars($row['color']); ?></td>
                                        <td><?php echo htmlspecialchars(dash_report_type_label($row['type'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No certificates yet. Start feeding reports to populate the dashboard.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include "assets/footer.php"; ?>
