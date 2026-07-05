<?php
require_once 'db_connect.php';
require_once 'atm_config.php';

date_default_timezone_set('Asia/Kolkata');

$userId = auth_current_user_id();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$dailyStart = date('Y-m-d', strtotime('-13 days'));
$monthlyStart = date('Y-m-01', strtotime('-5 months'));
$dateExpr = "COALESCE(NULLIF(DATE(`date`), '0000-00-00'), DATE(STR_TO_DATE(`date`, '%d-%m-%Y')), DATE(STR_TO_DATE(`date`, '%d/%m/%Y')), DATE(STR_TO_DATE(`date`, '%m/%d/%Y')))";
$branchScopeSql = user_branch_scope_sql($conn, $userId, 'user_id');

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
    if ($type === 'D') return 'Diamond';
    if ($type === 'J') return 'Jewellery';
    if ($type === 'R') return 'Rudraksha';
    return $type === '' ? 'Unknown' : $type;
}

function dash_num($number)
{
    return number_format((int) $number);
}

function dash_pct($part, $total)
{
    return $total > 0 ? round(($part / $total) * 100) : 0;
}

function dash_stone_image_count()
{
    $dir = atm_user_stone_dir();
    $count = 0;
    foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $ext) {
        $files = glob($dir . '/*.' . $ext);
        $count += is_array($files) ? count($files) : 0;
    }
    return $count;
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
$recentRows = dash_rows($conn, "SELECT certi_no, report_no, `date`, stone_name, stone_wt, color, `type` FROM sm_form_data WHERE {$branchScopeSql} ORDER BY certi_no DESC LIMIT 8");

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
.dashboard-page { padding-bottom: 36px; }
.dashboard-hero {
    border-bottom: 1px solid var(--app-border, #ececf1);
    margin: 0 0 24px;
    padding: 0 0 20px;
}
.dashboard-hero h1 { border: 0; color: var(--app-text, #171717); font-size: 26px; font-weight: 600; margin: 0 0 6px; padding: 0; }
.dashboard-hero p { color: var(--app-muted, #737373); margin: 0; max-width: 780px; }
.dash-grid { display: grid; gap: 16px; }
.metric-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.chart-grid { grid-template-columns: minmax(0, 1.25fr) minmax(0, .75fr); margin-top: 16px; }
.insight-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 16px; }
.dash-card {
    background: #fff;
    border: 1px solid #ececf1;
    border-radius: 10px;
    overflow: hidden;
}
.metric-card { padding: 18px; position: relative; }
.metric-card .icon {
    align-items: center;
    background: #f7f7f8;
    border-radius: 8px;
    color: #404040;
    display: flex;
    font-size: 20px;
    height: 44px;
    justify-content: center;
    margin-bottom: 14px;
    width: 44px;
}
.metric-card h3 { color: #737373; font-size: 12px; font-weight: 500; margin: 0 0 6px; text-transform: none; }
.metric-card .value { color: #171717; font-size: 28px; font-weight: 600; line-height: 1; }
.metric-card .hint { color: #737373; font-size: 12px; margin-top: 8px; }
.blue .icon { background: #f7f7f8; color: #404040; }
.green .icon { background: #f0fdf4; color: #16a34a; }
.amber .icon { background: #fffbeb; color: #d97706; }
.rose .icon { background: #fff1f2; color: #e11d48; }
.card-head { border-bottom: 1px solid #ececf1; padding: 16px 18px; }
.card-head h3 { color: #171717; font-size: 15px; font-weight: 600; margin: 0; }
.card-head p { color: #737373; font-size: 12px; margin: 4px 0 0; }
.card-body { padding: 18px; }
.bar-chart { align-items: end; display: flex; gap: 8px; height: 230px; padding-top: 12px; }
.bar-item { align-items: center; display: flex; flex: 1; flex-direction: column; gap: 8px; height: 100%; justify-content: flex-end; min-width: 0; }
.bar {
    background: #d4d4d4;
    border-radius: 6px 6px 2px 2px;
    min-height: 6px;
    width: 100%;
}
.bar-value { color: #404040; font-size: 11px; font-weight: 600; }
.bar-label { color: #737373; font-size: 10px; text-align: center; transform: rotate(-35deg); white-space: nowrap; }
.mini-bars .bar-chart { height: 165px; }
.progress-row { margin-bottom: 14px; }
.progress-top { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.progress-top span:first-child { color: #404040; font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.progress-top span:last-child { color: #737373; font-size: 12px; font-weight: 500; }
.progress-track { background: #ececf1; border-radius: 999px; height: 8px; overflow: hidden; }
.progress-fill { background: #737373; border-radius: 999px; height: 100%; }
.type-list { display: grid; gap: 10px; }
.type-pill { align-items: center; background: #f7f7f8; border: 1px solid #ececf1; border-radius: 8px; display: flex; justify-content: space-between; padding: 12px 14px; }
.type-pill strong { color: #171717; font-weight: 500; }
.type-pill span { color: #404040; font-weight: 600; }
.quality-ring {
    align-items: center;
    background: conic-gradient(#16a34a <?php echo $imageCoverage; ?>%, #ececf1 0);
    border-radius: 50%;
    display: flex;
    height: 150px;
    justify-content: center;
    margin: 8px auto 16px;
    width: 150px;
}
.quality-ring-inner { align-items: center; background: #fff; border-radius: 50%; display: flex; flex-direction: column; height: 108px; justify-content: center; width: 108px; }
.quality-ring-inner strong { color: #171717; font-size: 28px; font-weight: 600; }
.quality-ring-inner span { color: #737373; font-size: 12px; }
.recent-table { margin: 0; }
.recent-table th { color: #737373; font-size: 12px; font-weight: 500; text-transform: none; }
.recent-table td { color: #404040; vertical-align: middle !important; }
.empty-state { color: #737373; padding: 22px; text-align: center; }
.quota-summary { display: grid; gap: 12px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 16px; }
.quota-box { background: #f7f7f8; border: 1px solid #ececf1; border-radius: 10px; padding: 12px; }
.quota-box span { color: #737373; display: block; font-size: 11px; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; }
.quota-box strong { color: #171717; display: block; font-size: 20px; line-height: 1; }
.quota-meter { margin: 6px 0 14px; }
.quota-meter .progress-track { height: 12px; }
.quota-meter .progress-fill { background: #171717; }
.quota-note { color: #737373; font-size: 12px; line-height: 1.5; margin: 0; }
.quota-warning { color: #b45309; font-weight: 700; }
@media (max-width: 1200px) {
    .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .chart-grid, .insight-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .metric-grid { grid-template-columns: 1fr; }
    .quota-summary { grid-template-columns: 1fr; }
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
                    <h3>Certificate Usage</h3>
                    <p>Total certificate records for this branch account.</p>
                </div>
                <div class="card-body">
                    <div class="quota-summary">
                        <div class="quota-box">
                            <span>Created</span>
                            <strong><?php echo dash_num($totalReports); ?></strong>
                        </div>
                        <div class="quota-box">
                            <span>Access</span>
                            <strong>Unlimited</strong>
                        </div>
                        <div class="quota-box">
                            <span>This month</span>
                            <strong><?php echo dash_num($monthReports); ?></strong>
                        </div>
                    </div>
                    <p class="quota-note">This customized installation has unlimited certificate access and no payment-gateway restriction.</p>
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

        <div class="dash-card" style="margin-top:18px;">
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
